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
     * Instantiates and returns a new CustomServerUrl view helper.
     *
     * @return CustomServerUrl The newly created view helper instance.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CustomServerUrl();
    }
}
