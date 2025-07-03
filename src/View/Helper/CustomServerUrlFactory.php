<?php
namespace DerivativeMedia\View\Helper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for CustomServerUrl view helper
 *
 * Refactored for Laminas v3 compatibility with proper dependency injection.
 */
class CustomServerUrlFactory implements FactoryInterface
{
    /**
     * Create and return CustomServerUrl view helper with injected dependencies
     *
     * @param ContainerInterface $services
     * @param string $requestedName
     * @param array|null $options
     * @return CustomServerUrl
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // LAMINAS V3 FIX: Inject dependencies via constructor instead of using service locator
        $configuredBaseUrl = $this->getConfiguredBaseUrl($services);

        // CONFIGURABLE LOGGING FIX: Inject DebugManager for proper logging
        $debugManager = null;
        try {
            $debugManager = $services->get('DerivativeMedia\Service\DebugManager');
        } catch (\Exception $e) {
            // DebugManager not available, continue without logging
        }

        return new CustomServerUrl($configuredBaseUrl, $debugManager);
    }

    /**
     * Get the configured base URL from Omeka S configuration
     *
     * @param ContainerInterface $services
     * @return string|null
     */
    private function getConfiguredBaseUrl(ContainerInterface $services): ?string
    {
        try {
            // Try to get from main configuration first
            $config = $services->get('Config');
            if (isset($config['base_url']) && !empty($config['base_url'])) {
                return $config['base_url'];
            }

            // Fallback: try to get from Omeka S settings
            $settings = $services->get('Omeka\Settings');
            $baseUrl = $settings->get('base_url');

            if ($baseUrl && !empty($baseUrl)) {
                return $baseUrl;
            }

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            try {
                $debugManager = $services->get('DerivativeMedia\Service\DebugManager');
                $debugManager->logError("Error accessing configuration: " . $e->getMessage(), 'HELPER_FACTORY', 'factory-' . uniqid());
            } catch (\Exception $debugException) {
                // Fallback only if DebugManager is not available
                error_log("CustomServerUrlFactory: Error accessing configuration: " . $e->getMessage());
            }
        }

        return null;
    }
}
