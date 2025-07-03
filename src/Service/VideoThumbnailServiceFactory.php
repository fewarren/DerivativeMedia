<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VideoThumbnailServiceFactory implements FactoryInterface
{
    /**
     * Creates and configures a VideoThumbnailService instance using settings and dependencies from the service container.
     *
     * Retrieves paths for ffmpeg and ffprobe, the thumbnail percentage, and the base file storage path from configuration and settings.
     * Injects required dependencies including temporary file factory, thumbnailer, and logger. If supported, also injects the file store.
     *
     * @return VideoThumbnailService The configured video thumbnail service instance.
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
