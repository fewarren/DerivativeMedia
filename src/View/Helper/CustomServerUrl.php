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
     * Generates a server URL, using a configured base URL if the default result is malformed.
     *
     * If the parent implementation returns a malformed URL (e.g., missing domain), attempts to retrieve a valid base URL from configuration or settings and appends the optional request URI.
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
     * Determines whether the given URL is malformed due to a missing domain.
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
     * Attempts to obtain the base URL from the application configuration array or, if not present, from the Omeka S settings service. Returns null if neither source provides a base URL or if an error occurs during retrieval.
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
