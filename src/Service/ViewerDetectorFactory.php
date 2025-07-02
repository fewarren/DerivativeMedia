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
     * Instantiates and returns a ViewerDetector service with its required dependencies.
     *
     * @param ContainerInterface $services The service container providing dependencies.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional configuration options.
     * @return ViewerDetector The constructed ViewerDetector service.
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
