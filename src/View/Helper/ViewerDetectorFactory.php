<?php
namespace DerivativeMedia\View\Helper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ViewerDetector view helper
 */
class ViewerDetectorFactory implements FactoryInterface
{
    /**
     * Creates and returns a ViewerDetector view helper instance.
     *
     * Retrieves the ViewerDetector service from the container and injects it into a new ViewerDetector helper.
     *
     * @param ContainerInterface $services The service container.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional configuration options.
     * @return ViewerDetector The instantiated ViewerDetector view helper.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ViewerDetector(
            $services->get('DerivativeMedia\Service\ViewerDetector')
        );
    }
}
