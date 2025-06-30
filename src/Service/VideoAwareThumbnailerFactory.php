<?php
namespace DerivativeMedia\Service;

use DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for VideoAwareThumbnailer
 */
class VideoAwareThumbnailerFactory implements FactoryInterface
{
    /**
     * Creates and returns a configured instance of VideoAwareThumbnailer.
     *
     * Retrieves required services and settings from the container, assembles options for video thumbnail generation, merges with global thumbnailer options if available, and returns a new VideoAwareThumbnailer instance.
     *
     * @param ContainerInterface $services The service container.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional additional options.
     * @return VideoAwareThumbnailer The configured video-aware thumbnailer instance.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $cli = $services->get('Omeka\Cli');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $settings = $services->get('Omeka\Settings');
        
        // Get configuration options from module settings
        $thumbnailerOptions = [
            'ffmpeg_path' => $settings->get('derivativemedia_ffmpeg_path', '/usr/bin/ffmpeg'),
            'ffprobe_path' => $settings->get('derivativemedia_ffprobe_path', '/usr/bin/ffprobe'),
            'default_percentage' => $settings->get('derivativemedia_video_thumbnail_percentage', 25),
        ];
        
        // Merge with any ImageMagick options from global config
        $config = $services->get('Config');
        if (isset($config['thumbnails']['thumbnailer_options'])) {
            $thumbnailerOptions = array_merge($thumbnailerOptions, $config['thumbnails']['thumbnailer_options']);
        }
        
        return new VideoAwareThumbnailer($cli, $tempFileFactory, $thumbnailerOptions);
    }
}
