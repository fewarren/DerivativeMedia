<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;

class DebugManager
{
    const COMPONENT_FORM = 'FORM';
    const COMPONENT_BLOCK = 'BLOCK';
    const COMPONENT_FACTORY = 'FACTORY';
    const COMPONENT_SERVICE = 'SERVICE';
    const COMPONENT_API = 'API';

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $logFile;

    /**
     * @var bool
     */
    protected $debugEnabled;

    /**
     * @var string
     */
    protected $baseLogPath;

    /**
     * Initializes the DebugManager with optional configuration for log file name, debug mode, and log directory.
     *
     * @param array $options Optional configuration:
     *   - 'log_file': Custom log file name (default: 'DerivativeMedia_debug.log').
     *   - 'debug_enabled': Whether debug logging is enabled (default: true).
     *   - 'base_log_path': Custom base directory for logs (default: auto-detected or created).
     */
    public function __construct(array $options = [])
    {
        // Set debug enabled state
        $this->debugEnabled = $options['debug_enabled'] ?? true;

        // Determine base log path
        $this->baseLogPath = $this->determineBaseLogPath($options);

        // Set log file path
        $logFileName = $options['log_file'] ?? 'DerivativeMedia_debug.log';
        $this->logFile = $this->baseLogPath . DIRECTORY_SEPARATOR . $logFileName;

        $this->initializeLogger();
    }

    /**
     * Determines the base directory path for log files based on provided options and environment.
     *
     * Prefers an explicitly provided path if valid, otherwise attempts to detect or create a suitable logs directory within an Omeka S installation, and falls back to a system temporary directory if necessary.
     *
     * @param array $options Optional configuration, may include 'base_log_path'.
     * @return string The resolved base log directory path.
     */
    protected function determineBaseLogPath(array $options): string
    {
        // 1. Use explicitly provided base path
        if (!empty($options['base_log_path']) && is_dir($options['base_log_path'])) {
            return rtrim($options['base_log_path'], DIRECTORY_SEPARATOR);
        }

        // 2. Try to detect Omeka S installation path and use its logs directory
        $omekaLogPaths = [
            // Standard Omeka S log directory
            dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'logs',
            // Alternative: application/logs
            dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'logs',
            // Fallback: system temp directory
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omeka-s-logs',
        ];

        foreach ($omekaLogPaths as $path) {
            if (is_dir($path) && is_writable($path)) {
                return $path;
            }
        }

        // 3. Try to create logs directory in detected Omeka root
        $omekaRoot = dirname(dirname(dirname(dirname(__DIR__))));
        $logsDir = $omekaRoot . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($logsDir)) {
            try {
                if (mkdir($logsDir, 0755, true)) {
                    return $logsDir;
                }
            } catch (\Exception $e) {
                // Continue to fallback options
            }
        } elseif (is_writable($logsDir)) {
            return $logsDir;
        }

        // 4. Fallback to system temp directory
        $tempLogDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omeka-s-derivative-media';
        if (!is_dir($tempLogDir)) {
            mkdir($tempLogDir, 0755, true);
        }

        return $tempLogDir;
    }

    /**
     * Initializes the logger instance and configures it to write to the specified log file.
     *
     * Ensures the log directory exists and sets up a stream writer for file logging. If initialization fails, errors are reported using PHP's error_log.
     */
    protected function initializeLogger(): void
    {
        $this->logger = new Logger();
        
        try {
            // Ensure log directory exists
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $writer = new Stream($this->logFile);
            $this->logger->addWriter($writer);
        } catch (\Exception $e) {
            // Fallback to error_log if file logging fails
            error_log("DerivativeMedia DebugManager: Failed to initialize file logger: " . $e->getMessage());
        }
    }

    /**
     * Logs an informational message if debug logging is enabled.
     *
     * The message is formatted with optional component and operation ID tags before being written to the log file.
     *
     * @param string $message The informational message to log.
     * @param string $component Optional component tag for categorizing the log entry.
     * @param string $operationId Optional operation identifier for tracing related log entries.
     */
    public function logInfo(string $message, string $component = '', string $operationId = ''): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $formattedMessage = $this->formatMessage($message, $component, $operationId, 'INFO');
        
        try {
            $this->logger->info($formattedMessage);
        } catch (\Exception $e) {
            error_log("DerivativeMedia: " . $formattedMessage);
        }
    }

    /**
     * Logs an error message, including optional component and operation ID tags.
     *
     * Errors are always logged regardless of debug mode. If logging fails and debug mode is enabled, the message is also sent to PHP's error log.
     *
     * @param string $message The error message to log.
     * @param string $component Optional component tag for categorizing the error.
     * @param string $operationId Optional operation identifier for tracing the error.
     */
    public function logError(string $message, string $component = '', string $operationId = ''): void
    {
        // Always log errors regardless of debug mode for critical issues
        // But respect debug mode for non-critical error logging
        $formattedMessage = $this->formatMessage($message, $component, $operationId, 'ERROR');

        try {
            $this->logger->err($formattedMessage);
        } catch (\Exception $e) {
            // Only use error_log fallback if debug is enabled or this is a critical error
            if ($this->debugEnabled) {
                error_log("DerivativeMedia ERROR: " . $formattedMessage);
            }
        }
    }

    /**
     * Logs a warning message if debug logging is enabled.
     *
     * The message is formatted with optional component and operation ID tags before being logged at the warning level.
     */
    public function logWarning(string $message, string $component = '', string $operationId = ''): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $formattedMessage = $this->formatMessage($message, $component, $operationId, 'WARNING');
        
        try {
            $this->logger->warn($formattedMessage);
        } catch (\Exception $e) {
            error_log("DerivativeMedia WARNING: " . $formattedMessage);
        }
    }

    /**
     * Logs a debug-level message if debug logging is enabled.
     *
     * The message is formatted with optional component and operation ID tags before being logged.
     *
     * @param string $message The debug message to log.
     * @param string $component Optional component tag for categorizing the log entry.
     * @param string $operationId Optional operation identifier for tracing specific actions.
     */
    public function logDebug(string $message, string $component = '', string $operationId = ''): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $formattedMessage = $this->formatMessage($message, $component, $operationId, 'DEBUG');
        
        try {
            $this->logger->debug($formattedMessage);
        } catch (\Exception $e) {
            error_log("DerivativeMedia DEBUG: " . $formattedMessage);
        }
    }

    /**
     * Formats a log message with timestamp, log level, component, and operation ID.
     *
     * Constructs a standardized log entry string including the current timestamp, log level, optional component and operation ID tags, and the message content.
     *
     * @param string $message The log message content.
     * @param string $component Optional component identifier.
     * @param string $operationId Optional operation or request identifier.
     * @param string $level The log level (e.g., info, error, debug).
     * @return string The formatted log message.
     */
    protected function formatMessage(string $message, string $component, string $operationId, string $level): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $parts = [$timestamp, $level];
        
        if ($component) {
            $parts[] = "[$component]";
        }
        
        if ($operationId) {
            $parts[] = "[$operationId]";
        }
        
        $parts[] = $message;
        
        return implode(' ', $parts);
    }

    /**
     * Logs an informational message when a form factory is invoked, including contextual data.
     *
     * @param string $operationId Identifier for the operation being traced.
     * @param array $context Optional contextual information to include in the log.
     */
    public function traceFormFactory(string $operationId, array $context = []): void
    {
        $this->logInfo(
            sprintf('Form factory invoked - Context: %s', json_encode($context)),
            self::COMPONENT_FACTORY,
            $operationId
        );
    }

    /**
     * Logs an informational message about the rendering of a block form, including the block ID if available.
     *
     * @param string $operationId Identifier for the current operation or request.
     * @param mixed $block Optional block object; if provided, its ID is included in the log.
     */
    public function traceBlockForm(string $operationId, $block = null): void
    {
        $blockInfo = $block ? sprintf('Block ID: %s', $block->id() ?? 'new') : 'New block';
        $this->logInfo(
            sprintf('Block form rendering - %s', $blockInfo),
            self::COMPONENT_BLOCK,
            $operationId
        );
    }

    /**
     * Logs an informational message tracing an API call with the specified resource and parameters.
     *
     * @param string $operationId Identifier for the API operation being traced.
     * @param string $resource The name of the API resource involved in the call.
     * @param array $params Optional parameters passed to the API call.
     */
    public function traceApiCall(string $operationId, string $resource, array $params = []): void
    {
        $this->logInfo(
            sprintf('API call - Resource: %s, Params: %s', $resource, json_encode($params)),
            self::COMPONENT_API,
            $operationId
        );
    }

    /**
     * Enables or disables debug logging.
     *
     * @param bool $enabled True to enable debug logging, false to disable it.
     */
    public function setDebugEnabled(bool $enabled): void
    {
        $this->debugEnabled = $enabled;
    }

    /**
     * Checks whether debug logging is currently enabled.
     *
     * @return bool True if debug logging is enabled, false otherwise.
     */
    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    /**
     * Returns the full path to the current log file used for debug logging.
     *
     * @return string The absolute path to the log file.
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Returns the base directory path where log files are stored.
     *
     * @return string The absolute path to the log directory.
     */
    public function getBaseLogPath(): string
    {
        return $this->baseLogPath;
    }

    /**
     * Returns an array with the current debug logging configuration and log file status.
     *
     * The returned array includes the log file path, base log directory, debug enabled state, log file existence, writability of the log directory, and the log file size in bytes.
     *
     * @return array Associative array with keys: 'log_file', 'base_log_path', 'debug_enabled', 'log_file_exists', 'log_file_writable', and 'log_file_size'.
     */
    public function getConfigInfo(): array
    {
        return [
            'log_file' => $this->logFile,
            'base_log_path' => $this->baseLogPath,
            'debug_enabled' => $this->debugEnabled,
            'log_file_exists' => file_exists($this->logFile),
            'log_file_writable' => is_writable(dirname($this->logFile)),
            'log_file_size' => file_exists($this->logFile) ? filesize($this->logFile) : 0,
        ];
    }
}
