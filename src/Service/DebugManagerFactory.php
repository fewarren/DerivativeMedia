<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class DebugManagerFactory implements FactoryInterface
{
    /**
     * Creates and returns a new instance of DebugManager.
     *
     * @return DebugManager The newly created DebugManager instance.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new DebugManager();
    }
}
