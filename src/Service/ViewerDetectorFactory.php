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
     * @return ViewerDetector The configured ViewerDetector instance.
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
