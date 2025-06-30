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
     * Initializes the DebugManager with default log file path and enables debug logging.
     *
     * Sets up the logger to write to the specified log file and ensures debug logging is active by default.
     */
    public function __construct()
    {
        $this->logFile = '/var/www/omeka-s/logs/DerivativeMedia_debug.log';
        $this->debugEnabled = true;
        $this->initializeLogger();
    }

    /**
     * Initializes the logger to write log entries to the specified log file.
     *
     * Ensures the log directory exists and configures the logger with a stream writer. If initialization fails, logs the error using PHP's error_log.
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
     * The message is formatted with timestamp, log level, optional component, and operation ID.
     *
     * @param string $message The message to log.
     * @param string $component Optional component identifier for categorizing the log entry.
     * @param string $operationId Optional operation ID for correlating log entries.
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
     * Logs an error-level message, including optional component and operation ID tags.
     *
     * This method logs the message regardless of whether debug logging is enabled. If logging to the configured logger fails, the message is written to PHP's error log.
     *
     * @param string $message The error message to log.
     * @param string $component Optional component identifier for categorizing the log entry.
     * @param string $operationId Optional operation ID for correlating log entries.
     */
    public function logError(string $message, string $component = '', string $operationId = ''): void
    {
        $formattedMessage = $this->formatMessage($message, $component, $operationId, 'ERROR');
        
        try {
            $this->logger->err($formattedMessage);
        } catch (\Exception $e) {
            error_log("DerivativeMedia ERROR: " . $formattedMessage);
        }
    }

    /**
     * Logs a warning-level message if debug logging is enabled.
     *
     * Formats the message with optional component and operation ID tags before logging.
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
     * The message is formatted with timestamp, log level, optional component, and operation ID.
     * Falls back to PHP's error_log if logging fails.
     *
     * @param string $message The debug message to log.
     * @param string $component Optional component identifier for categorizing the log entry.
     * @param string $operationId Optional operation ID for correlating log entries.
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
     * @param string $component The component identifier (optional).
     * @param string $operationId The operation ID for traceability (optional).
     * @param string $level The log level (e.g., INFO, ERROR).
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
     * Logs an informational message indicating that a form factory has been invoked, including optional context data.
     *
     * @param string $operationId Identifier for the operation being traced.
     * @param array $context Optional contextual data to include in the log entry.
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
     * @param string $operationId Identifier for the operation or request.
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
     * Logs an informational message about an API call, including the resource name and parameters.
     *
     * @param string $operationId Identifier for the API operation.
     * @param string $resource Name of the API resource being accessed.
     * @param array $params Parameters passed to the API call.
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
     * Enables or disables debug-level logging.
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
}
