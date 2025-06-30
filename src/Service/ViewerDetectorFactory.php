<?php
namespace DerivativeMedia\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ViewerDetector service
 */
class ViewerDetectorFactory implements FactoryInterface
{
    /**
     * Creates and returns a new ViewerDetector service instance.
     *
     * Retrieves required dependencies from the service container and injects them into the ViewerDetector constructor.
     *
     * @param ContainerInterface $services The service container.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional configuration options.
     * @return ViewerDetector The instantiated ViewerDetector service.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ViewerDetector(
            $services->get('Omeka\ModuleManager'),
            $services->get('Omeka\Settings'),
            $services->get('Omeka\Settings\Site')
        );
    }
}
