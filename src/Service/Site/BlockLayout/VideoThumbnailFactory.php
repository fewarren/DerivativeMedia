<?php declare(strict_types=1);

namespace DerivativeMedia\Service\Site\BlockLayout;

use DerivativeMedia\Site\BlockLayout\VideoThumbnail;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VideoThumbnailFactory implements FactoryInterface
{
    /**
     * Creates and returns a new VideoThumbnail instance with required dependencies from the service container.
     *
     * @param ContainerInterface $services The service container used to retrieve dependencies.
     * @param string $requestedName The name of the requested service (unused).
     * @param array|null $options Optional configuration options (unused).
     * @return VideoThumbnail The constructed VideoThumbnail instance.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new VideoThumbnail(
            $services->get('DerivativeMedia\Service\VideoThumbnailService'),
            $services->get('FormElementManager'),
            $services->get('Omeka\ApiManager'),
            $services->get('DerivativeMedia\Service\DebugManager')
        );
    }
}
