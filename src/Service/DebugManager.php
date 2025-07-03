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
    const COMPONENT_RENDERER = 'RENDERER';
    const COMPONENT_HELPER = 'HELPER';
    const COMPONENT_MODULE = 'MODULE';
    const COMPONENT_THUMBNAILER = 'THUMBNAILER';

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
     * Constructor with configurable options
     *
     * @param array $options Configuration options including:
     *   - log_file: Log file name (default: 'DerivativeMedia_debug.log')
     *   - debug_enabled: Enable/disable debug logging (default: true)
     *   - base_log_path: Base directory for logs (default: auto-detected)
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
     * Determine the base log path based on environment and configuration
     *
     * @param array $options Configuration options
     * @return string Base log directory path
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

    public function traceFormFactory(string $operationId, array $context = []): void
    {
        $this->logInfo(
            sprintf('Form factory invoked - Context: %s', json_encode($context)),
            self::COMPONENT_FACTORY,
            $operationId
        );
    }

    public function traceBlockForm(string $operationId, $block = null): void
    {
        $blockInfo = $block ? sprintf('Block ID: %s', $block->id() ?? 'new') : 'New block';
        $this->logInfo(
            sprintf('Block form rendering - %s', $blockInfo),
            self::COMPONENT_BLOCK,
            $operationId
        );
    }

    public function traceApiCall(string $operationId, string $resource, array $params = []): void
    {
        $this->logInfo(
            sprintf('API call - Resource: %s, Params: %s', $resource, json_encode($params)),
            self::COMPONENT_API,
            $operationId
        );
    }

    public function setDebugEnabled(bool $enabled): void
    {
        $this->debugEnabled = $enabled;
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    /**
     * Get the current log file path
     *
     * @return string
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Get the base log directory path
     *
     * @return string
     */
    public function getBaseLogPath(): string
    {
        return $this->baseLogPath;
    }

    /**
     * Get configuration information for debugging
     *
     * @return array
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
