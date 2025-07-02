<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class DebugManagerFactory implements FactoryInterface
{
    /**
     * Creates and returns a DebugManager instance configured with application settings and environment variable overrides.
     *
     * Retrieves debug-related configuration from the application's settings service, allowing environment variables to override default values for debug enablement, log file name, and base log path.
     *
     * @param ContainerInterface $container The service container providing application settings.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional additional options (unused).
     * @return DebugManager The configured DebugManager instance.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $settings = $container->get('Omeka\Settings');

        // Build configuration options from Omeka S settings
        $debugOptions = [
            'debug_enabled' => (bool) $settings->get('derivativemedia_debug_enabled', true),
            'log_file' => $settings->get('derivativemedia_debug_log_file', 'DerivativeMedia_debug.log'),
            'base_log_path' => $settings->get('derivativemedia_debug_log_path', null),
        ];

        // Allow environment variable overrides for deployment flexibility
        if ($envLogPath = getenv('OMEKA_LOG_PATH')) {
            $debugOptions['base_log_path'] = $envLogPath;
        }

        if ($envLogFile = getenv('DERIVATIVEMEDIA_LOG_FILE')) {
            $debugOptions['log_file'] = $envLogFile;
        }

        if (getenv('DERIVATIVEMEDIA_DEBUG') !== false) {
            $debugOptions['debug_enabled'] = filter_var(getenv('DERIVATIVEMEDIA_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        }

        return new DebugManager($debugOptions);
    }
}
