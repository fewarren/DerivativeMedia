<?php declare(strict_types=1);

namespace DerivativeMedia\Service\Site\BlockLayout;

use DerivativeMedia\Site\BlockLayout\VideoThumbnail;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VideoThumbnailFactory implements FactoryInterface
{
    /**
     * Creates and returns a new VideoThumbnail block layout instance with required dependencies.
     *
     * @param ContainerInterface $services The service container providing dependencies.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional configuration options.
     * @return VideoThumbnail The configured VideoThumbnail block layout instance.
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
