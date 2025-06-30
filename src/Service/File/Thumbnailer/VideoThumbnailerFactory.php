<?php declare(strict_types=1);

namespace DerivativeMedia\Service\File\Thumbnailer;

use DerivativeMedia\File\Thumbnailer\VideoThumbnailer;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VideoThumbnailerFactory implements FactoryInterface
{
    /**
     * Creates and returns a VideoThumbnailer instance configured with application settings.
     *
     * Retrieves ffmpeg and ffprobe binary paths and the thumbnail percentage from application settings,
     * applies default values if not set, and passes these options to the VideoThumbnailer constructor.
     *
     * @return VideoThumbnailer The configured VideoThumbnailer instance.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        error_log('DerivativeMedia VideoThumbnailerFactory: Creating VideoThumbnailer');
        
        $settings = $services->get('Omeka\Settings');
        
        $thumbnailerOptions = [
            'ffmpeg_path' => $settings->get('derivativemedia_ffmpeg_path', '/usr/bin/ffmpeg'),
            'ffprobe_path' => $settings->get('derivativemedia_ffprobe_path', '/usr/bin/ffprobe'),
            'thumbnail_percentage' => $settings->get('derivativemedia_video_thumbnail_percentage', 25),
        ];
        
        error_log('DerivativeMedia VideoThumbnailerFactory: Options: ' . print_r($thumbnailerOptions, true));
        
        return new VideoThumbnailer($thumbnailerOptions);
    }
}
