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
     * Creates and returns a new instance of the CustomServerUrl view helper.
     *
     * @return CustomServerUrl The newly created CustomServerUrl view helper.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CustomServerUrl();
    }
}
