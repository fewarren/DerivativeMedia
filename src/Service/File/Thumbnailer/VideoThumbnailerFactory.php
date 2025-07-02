<?php declare(strict_types=1);

namespace DerivativeMedia\Service\File\Thumbnailer;

use DerivativeMedia\File\Thumbnailer\VideoThumbnailer;
use DerivativeMedia\Service\DebugManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VideoThumbnailerFactory implements FactoryInterface
{
    /**
     * Creates and returns a configured VideoThumbnailer instance.
     *
     * Retrieves configuration settings for FFmpeg and FFprobe paths and the thumbnail capture percentage from the container, and uses them to instantiate a VideoThumbnailer. Logs the creation process and rethrows any exceptions encountered during instantiation.
     *
     * @param ContainerInterface $services The service container.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional additional options for instantiation.
     * @return VideoThumbnailer The configured VideoThumbnailer instance.
     * @throws \Exception If instantiation of VideoThumbnailer fails.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // Get DebugManager for proper logging
        $debugManager = null;
        try {
            $debugManager = $services->get('DerivativeMedia\Service\DebugManager');
        } catch (\Exception $e) {
            // Fallback: create DebugManager directly if service not available
            $debugManager = new DebugManager();
        }

        $operationId = 'factory-' . uniqid();
        $debugManager->logInfo('Creating VideoThumbnailer instance', DebugManager::COMPONENT_FACTORY, $operationId);

        $settings = $services->get('Omeka\Settings');

        $thumbnailerOptions = [
            'ffmpeg_path' => $settings->get('derivativemedia_ffmpeg_path', '/usr/bin/ffmpeg'),
            'ffprobe_path' => $settings->get('derivativemedia_ffprobe_path', '/usr/bin/ffprobe'),
            'thumbnail_percentage' => $settings->get('derivativemedia_video_thumbnail_percentage', 25),
        ];

        $debugManager->logInfo(
            sprintf('VideoThumbnailer options configured - FFmpeg: %s, FFprobe: %s, Percentage: %d%%',
                $thumbnailerOptions['ffmpeg_path'],
                $thumbnailerOptions['ffprobe_path'],
                $thumbnailerOptions['thumbnail_percentage']
            ),
            DebugManager::COMPONENT_FACTORY,
            $operationId
        );

        try {
            $videoThumbnailer = new VideoThumbnailer($thumbnailerOptions);
            $debugManager->logInfo('VideoThumbnailer instance created successfully', DebugManager::COMPONENT_FACTORY, $operationId);
            return $videoThumbnailer;
        } catch (\Exception $e) {
            $debugManager->logError(
                sprintf('Failed to create VideoThumbnailer: %s', $e->getMessage()),
                DebugManager::COMPONENT_FACTORY,
                $operationId
            );
            throw $e;
        }
    }
}
