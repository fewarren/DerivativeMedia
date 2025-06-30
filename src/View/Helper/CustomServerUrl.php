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
     * Get the configured base_url from Omeka S configuration
     * 
     * @return string|null The configured base URL or null if not found
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
