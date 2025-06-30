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

    public function __construct()
    {
        $this->logFile = '/var/www/omeka-s/logs/DerivativeMedia_debug.log';
        $this->debugEnabled = true;
        $this->initializeLogger();
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
        $formattedMessage = $this->formatMessage($message, $component, $operationId, 'ERROR');
        
        try {
            $this->logger->err($formattedMessage);
        } catch (\Exception $e) {
            error_log("DerivativeMedia ERROR: " . $formattedMessage);
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
}
