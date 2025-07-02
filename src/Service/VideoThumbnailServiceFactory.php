<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VideoThumbnailServiceFactory implements FactoryInterface
{
    /**
     * Creates and configures a VideoThumbnailService instance using settings and dependencies from the service container.
     *
     * Retrieves configuration values such as ffmpeg and ffprobe paths, thumbnail capture percentage, and file storage base path. Injects required dependencies and optionally sets the file store if supported by the service.
     *
     * @param ContainerInterface $services The service container providing dependencies and configuration.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional creation options.
     * @return VideoThumbnailService The fully configured video thumbnail service instance.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');

        $ffmpegPath = $settings->get('derivativemedia_ffmpeg_path', '/usr/bin/ffmpeg');
        $ffprobePath = $settings->get('derivativemedia_ffprobe_path', '/usr/bin/ffprobe');
        $thumbnailPercentage = (int) $settings->get('derivativemedia_video_thumbnail_percentage', 25);
        $basePath = $config['file_store']['local']['base_path'] ?? (OMEKA_PATH . '/files');

        $service = new VideoThumbnailService(
            $ffmpegPath,
            $ffprobePath,
            $thumbnailPercentage,
            $services->get('Omeka\File\TempFileFactory'),
            $services->get('Omeka\File\Thumbnailer'),
            $services->get('Omeka\Logger'),
            $basePath
        );

        // Inject the file store if we need it
        if (method_exists($service, 'setFileStore')) {
            $service->setFileStore($services->get('Omeka\File\Store'));
        }

        return $service;
    }
}
