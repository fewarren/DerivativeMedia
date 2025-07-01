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
     * Retrieves the 'DerivativeMedia\Service\ViewerDetector' service from the container and injects it into a new ViewerDetector view helper.
     *
     * @param ContainerInterface $services The service container.
     * @param string $requestedName The requested service name.
     * @param array|null $options Optional configuration options.
     * @return ViewerDetector The created ViewerDetector view helper.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ViewerDetector(
            $services->get('DerivativeMedia\Service\ViewerDetector')
        );
    }
}
