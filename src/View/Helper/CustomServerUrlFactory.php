<?php
namespace DerivativeMedia\View\Helper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for CustomServerUrl view helper
 */
class CustomServerUrlFactory implements FactoryInterface
{
    /**
     * Create and return CustomServerUrl view helper
     *
     * @param ContainerInterface $services
     * @param string $requestedName
     * @param array|null $options
     * @return CustomServerUrl
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CustomServerUrl();
    }
}
