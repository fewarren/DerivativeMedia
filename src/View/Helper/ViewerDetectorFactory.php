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
     * Create and return ViewerDetector view helper
     *
     * @param ContainerInterface $services
     * @param string $requestedName
     * @param array|null $options
     * @return ViewerDetector
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ViewerDetector(
            $services->get('DerivativeMedia\Service\ViewerDetector')
        );
    }
}
