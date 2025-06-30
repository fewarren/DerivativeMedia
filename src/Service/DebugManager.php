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
     * Initializes the DebugManager with default settings and prepares the logger.
     *
     * Sets the default log file path, enables debug logging, and initializes the logger instance.
     */
    public function __construct()
    {
        $this->logFile = '/var/www/omeka-s/logs/DerivativeMedia_debug.log';
        $this->debugEnabled = true;
        $this->initializeLogger();
    }

    /**
     * Initializes the logger instance and configures it to write to the specified log file.
     *
     * Ensures the log directory exists, creating it if necessary. If logger setup fails, logs the error using PHP's error_log.
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
     * The message is formatted with a timestamp, log level, optional component, and operation ID for contextual tracing.
     *
     * @param string $message The informational message to log.
     * @param string $component Optional component identifier for categorizing the log entry.
     * @param string $operationId Optional operation ID for correlating related log entries.
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
     * Logs an error message with optional component and operation ID context.
     *
     * This method records error-level messages regardless of the debug flag state. If logging to the configured logger fails, the message is written to PHP's error log.
     *
     * @param string $message The error message to log.
     * @param string $component Optional component identifier for contextual tagging.
     * @param string $operationId Optional operation ID for traceability.
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
     * Logs a warning message with optional component and operation ID tags if debug logging is enabled.
     *
     * The message is formatted to include a timestamp, log level, component, and operation ID for contextual tracing.
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
     * The message is formatted with a timestamp, log level, optional component, and operation ID.
     *
     * @param string $message The debug message to log.
     * @param string $component Optional component identifier for contextual tagging.
     * @param string $operationId Optional operation ID for traceability.
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
     * Formats a log message with timestamp, log level, optional component and operation ID tags.
     *
     * @param string $message The message content.
     * @param string $component Optional component identifier for context.
     * @param string $operationId Optional operation ID for traceability.
     * @param string $level The log severity level.
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
     * Logs an informational message indicating that a form factory has been invoked, including the provided context.
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
     * @param string $operationId Identifier for the operation being traced.
     * @param mixed $block Optional block object; if provided, its ID is included in the log message.
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
     * Logs an informational message indicating an API call with the specified resource and parameters.
     *
     * @param string $operationId Identifier for the operation being traced.
     * @param string $resource The name of the API resource being accessed.
     * @param array $params Optional parameters associated with the API call.
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
}
