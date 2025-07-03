<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EventListenerFactory implements FactoryInterface
{
    /**
     * Creates and configures an EventListener instance, attaching it to the shared event manager.
     *
     * @param ContainerInterface $services The service container.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional configuration options.
     * @return EventListener The fully initialized and attached event listener.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $eventListener = new EventListener($services);
        
        // Immediately attach listeners when the service is created
        $sharedEventManager = $services->get('SharedEventManager');
        $eventListener->attach($sharedEventManager);
        
        return $eventListener;
    }
}
