<?php
namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\ServerUrl as LaminasServerUrl;
use DerivativeMedia\Service\DebugManager;

/**
 * Custom ServerUrl Helper
 *
 * This helper fixes URL generation issues by forcing the use of configured base_url
 * when server environment variables are missing or malformed.
 *
 * Addresses the issue where ServerUrl() returns "http://" instead of proper URLs.
 *
 * Refactored for Laminas v3 compatibility with constructor injection.
 */
class CustomServerUrl extends LaminasServerUrl
{
    /**
     * @var string|null
     */
    private $configuredBaseUrl;

    /**
     * @var DebugManager|null
     */
    private $debugManager;

    /**
     * Constructor with dependency injection
     *
     * @param string|null $configuredBaseUrl The configured base URL from Omeka S
     * @param DebugManager|null $debugManager The debug manager for logging
     */
    public function __construct(?string $configuredBaseUrl = null, ?DebugManager $debugManager = null)
    {
        parent::__construct();
        $this->configuredBaseUrl = $configuredBaseUrl;
        $this->debugManager = $debugManager;
    }
    /**
     * Generate a server URL
     * 
     * @param string|null $requestUri Optional request URI
     * @return string The server URL
     */
    public function __invoke($requestUri = null)
    {
        // First, try the parent implementation
        $parentResult = parent::__invoke($requestUri);
        
        // Check if parent result is malformed (missing domain)
        if ($this->isUrlMalformed($parentResult)) {
            // CONFIGURABLE LOGGING: Log malformed URL detection
            $this->logDebug("Malformed URL detected: '$parentResult', attempting to use configured base URL");

            // Get the configured base_url from Omeka S
            $baseUrl = $this->getConfiguredBaseUrl();

            if ($baseUrl) {
                // Use configured base_url instead
                if ($requestUri !== null) {
                    // If a specific URI was requested, append it to base URL
                    $fixedUrl = rtrim($baseUrl, '/') . '/' . ltrim($requestUri, '/');
                    $this->logDebug("Fixed malformed URL: '$parentResult' -> '$fixedUrl'");
                    return $fixedUrl;
                } else {
                    // Return just the base URL
                    $this->logDebug("Using configured base URL: '$baseUrl'");
                    return $baseUrl;
                }
            } else {
                $this->logWarning("Malformed URL detected but no configured base URL available");
            }
        }
        
        // If parent result is OK or we can't get base_url, return parent result
        return $parentResult;
    }
    
    /**
     * Check if a URL is malformed (missing domain)
     * 
     * @param string $url The URL to check
     * @return bool True if URL is malformed
     */
    private function isUrlMalformed($url)
    {
        // Check for common malformed patterns
        return (
            $url === 'http://' ||           // Missing domain entirely
            $url === 'https://' ||          // Missing domain entirely
            strpos($url, 'http:///') === 0 || // Triple slash (missing domain)
            strpos($url, 'https:///') === 0   // Triple slash (missing domain)
        );
    }
    
    /**
     * Get the configured base_url (injected via constructor)
     *
     * @return string|null The configured base URL or null if not found
     */
    private function getConfiguredBaseUrl()
    {
        // LAMINAS V3 FIX: Use injected dependency instead of deprecated service locator
        return $this->configuredBaseUrl;
    }

    /**
     * Log debug message using injected DebugManager
     *
     * @param string $message The message to log
     */
    private function logDebug(string $message): void
    {
        if ($this->debugManager) {
            $this->debugManager->logDebug($message, DebugManager::COMPONENT_HELPER, 'serverurl-' . uniqid());
        }
    }

    /**
     * Log warning message using injected DebugManager
     *
     * @param string $message The message to log
     */
    private function logWarning(string $message): void
    {
        if ($this->debugManager) {
            $this->debugManager->logWarning($message, DebugManager::COMPONENT_HELPER, 'serverurl-' . uniqid());
        }
    }

    /**
     * Log error message using injected DebugManager
     *
     * @param string $message The message to log
     */
    private function logError(string $message): void
    {
        if ($this->debugManager) {
            $this->debugManager->logError($message, DebugManager::COMPONENT_HELPER, 'serverurl-' . uniqid());
        }
    }
}
