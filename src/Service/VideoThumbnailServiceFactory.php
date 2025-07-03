<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\File\Store\StoreInterface;

class VideoThumbnailServiceFactory implements FactoryInterface
{
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

        // Inject the file store dependency - VideoThumbnailService implements FileStoreAwareInterface
        /** @var StoreInterface $fileStore */
        $fileStore = $services->get('Omeka\File\Store');
        $service->setFileStore($fileStore);

        return $service;
    }
}
