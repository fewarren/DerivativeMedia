<?php
namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\ServerUrl as LaminasServerUrl;

/**
 * Custom ServerUrl Helper
 * 
 * This helper fixes URL generation issues by forcing the use of configured base_url
 * when server environment variables are missing or malformed.
 * 
 * Addresses the issue where ServerUrl() returns "http://" instead of proper URLs.
 */
class CustomServerUrl extends LaminasServerUrl
{
    /**
     * Generates a server URL, falling back to a configured base URL if the parent implementation returns a malformed result.
     *
     * If the parent server URL helper produces a URL missing a domain, this method attempts to use the application's configured `base_url`. The optional request URI is appended to the base URL if provided.
     *
     * @param string|null $requestUri An optional request URI to append to the base URL.
     * @return string The generated server URL.
     */
    public function __invoke($requestUri = null)
    {
        // First, try the parent implementation
        $parentResult = parent::__invoke($requestUri);
        
        // Check if parent result is malformed (missing domain)
        if ($this->isUrlMalformed($parentResult)) {
            // Get the configured base_url from Omeka S
            $baseUrl = $this->getConfiguredBaseUrl();
            
            if ($baseUrl) {
                // Use configured base_url instead
                if ($requestUri !== null) {
                    // If a specific URI was requested, append it to base URL
                    return rtrim($baseUrl, '/') . '/' . ltrim($requestUri, '/');
                } else {
                    // Return just the base URL
                    return $baseUrl;
                }
            }
        }
        
        // If parent result is OK or we can't get base_url, return parent result
        return $parentResult;
    }
    
    /**
     * Determines if the given URL is malformed due to a missing domain.
     *
     * A URL is considered malformed if it is exactly "http://", "https://", or starts with "http:///" or "https:///".
     *
     * @param string $url The URL to evaluate.
     * @return bool True if the URL is malformed; otherwise, false.
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
     * Retrieves the configured base URL from Omeka S configuration or settings.
     *
     * Attempts to obtain the `base_url` from the main configuration array. If not found, falls back to retrieving it from Omeka settings. Returns `null` if no valid base URL is available or if an error occurs during retrieval.
     *
     * @return string|null The configured base URL, or null if not found.
     */
    private function getConfiguredBaseUrl()
    {
        try {
            // Get the view helper manager
            $helperManager = $this->getView()->getHelperPluginManager();
            
            // Get the service manager from the helper manager
            $serviceManager = $helperManager->getServiceLocator();
            
            // Get the configuration
            $config = $serviceManager->get('Config');
            
            // Return the configured base_url if it exists
            if (isset($config['base_url']) && !empty($config['base_url'])) {
                return $config['base_url'];
            }
            
            // Fallback: try to get from settings
            $settings = $serviceManager->get('Omeka\Settings');
            $baseUrl = $settings->get('base_url');
            
            if ($baseUrl && !empty($baseUrl)) {
                return $baseUrl;
            }
            
        } catch (\Exception $e) {
            // If we can't access configuration, log the error but don't break
            error_log("CustomServerUrl: Error accessing configuration: " . $e->getMessage());
        }
        
        return null;
    }
}
