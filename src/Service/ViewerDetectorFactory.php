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
     * Create and return ViewerDetector service
     *
     * @param ContainerInterface $services
     * @param string $requestedName
     * @param array|null $options
     * @return ViewerDetector
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
