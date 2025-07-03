<?php declare(strict_types=1);

namespace DerivativeMedia\File\Thumbnailer;

use DerivativeMedia\Service\DebugManager;
use Omeka\File\Thumbnailer\ThumbnailerInterface;
use Omeka\Stdlib\ErrorStore;

class VideoThumbnailer implements ThumbnailerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var DebugManager
     */
    protected $debugManager;

    public function __construct(array $options = [], DebugManager $debugManager = null)
    {
        $this->options = $options;
        $this->debugManager = $debugManager ?: new DebugManager(); // Fallback if not injected

        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
        $this->debugManager->logInfo('VideoThumbnailer constructor called', DebugManager::COMPONENT_THUMBNAILER);
    }

    /**
     * Create thumbnails for video files
     */
    public function createThumbnails($sourcePath, $destPaths, ErrorStore $errorStore = null): bool
    {
        $operationId = 'thumbnail-' . uniqid();

        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
        $this->debugManager->logInfo('createThumbnails called', DebugManager::COMPONENT_THUMBNAILER, $operationId);
        $this->debugManager->logInfo("Source: $sourcePath", DebugManager::COMPONENT_THUMBNAILER, $operationId);
        $this->debugManager->logInfo("Destinations: " . json_encode($destPaths), DebugManager::COMPONENT_THUMBNAILER, $operationId);

        try {
            // Get FFmpeg path from options or use default
            $ffmpegPath = $this->options['ffmpeg_path'] ?? '/usr/bin/ffmpeg';
            $ffprobePath = $this->options['ffprobe_path'] ?? '/usr/bin/ffprobe';
            $thumbnailPercentage = $this->options['thumbnail_percentage'] ?? 25;

            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $this->debugManager->logInfo("Using FFmpeg: $ffmpegPath", DebugManager::COMPONENT_THUMBNAILER, $operationId);

            // Check if source file exists
            if (!file_exists($sourcePath)) {
                $this->debugManager->logError("Source file not found: $sourcePath", DebugManager::COMPONENT_THUMBNAILER, $operationId);
                if ($errorStore) {
                    $errorStore->addError('source', 'Source video file not found');
                }
                return false;
            }

            // Get video duration
            $durationCmd = sprintf(
                '%s -v quiet -show_entries format=duration -of csv="p=0" %s 2>/dev/null',
                escapeshellarg($ffprobePath),
                escapeshellarg($sourcePath)
            );
            
            $duration = (float) shell_exec($durationCmd);
            if ($duration <= 0) {
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $this->debugManager->logWarning("Could not determine video duration, using 1 second", DebugManager::COMPONENT_THUMBNAILER, $operationId);
                $position = 1;
            } else {
                $position = ($duration * $thumbnailPercentage) / 100;
                $this->debugManager->logInfo("Video duration: {$duration}s, thumbnail at: {$position}s", DebugManager::COMPONENT_THUMBNAILER, $operationId);
            }

            $success = true;

            // Create each required thumbnail size
            foreach ($destPaths as $size => $destPath) {
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $this->debugManager->logInfo("Creating $size thumbnail: $destPath", DebugManager::COMPONENT_THUMBNAILER, $operationId);

                // Ensure destination directory exists
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                    $this->debugManager->logInfo("Created directory: $destDir", DebugManager::COMPONENT_THUMBNAILER, $operationId);
                }

                // Determine dimensions based on size
                $dimensions = $this->getDimensionsForSize($size);
                
                // Create FFmpeg command
                if ($size === 'square') {
                    // For square thumbnails, crop to square
                    $cmd = sprintf(
                        '%s -i %s -ss %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d" -q:v 2 %s 2>&1',
                        escapeshellarg($ffmpegPath),
                        escapeshellarg($sourcePath),
                        escapeshellarg((string) $position),
                        $dimensions, $dimensions,
                        $dimensions, $dimensions,
                        escapeshellarg($destPath)
                    );
                } else {
                    // For other sizes, scale proportionally
                    $cmd = sprintf(
                        '%s -i %s -ss %s -vframes 1 -vf "scale=%d:-1" -q:v 2 %s 2>&1',
                        escapeshellarg($ffmpegPath),
                        escapeshellarg($sourcePath),
                        escapeshellarg((string) $position),
                        $dimensions,
                        escapeshellarg($destPath)
                    );
                }

                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $this->debugManager->logInfo("FFmpeg command: $cmd", DebugManager::COMPONENT_THUMBNAILER, $operationId);

                $output = shell_exec($cmd);
                $this->debugManager->logInfo("FFmpeg output: $output", DebugManager::COMPONENT_THUMBNAILER, $operationId);

                // Check if thumbnail was created successfully
                if (file_exists($destPath) && filesize($destPath) > 0) {
                    $this->debugManager->logInfo("Successfully created $size thumbnail: $destPath", DebugManager::COMPONENT_THUMBNAILER, $operationId);
                } else {
                    $this->debugManager->logError("Failed to create $size thumbnail: $destPath", DebugManager::COMPONENT_THUMBNAILER, $operationId);
                    if ($errorStore) {
                        $errorStore->addError($size, "Failed to create $size thumbnail");
                    }
                    $success = false;
                }
            }

            if ($success) {
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $this->debugManager->logInfo("*** ALL THUMBNAILS CREATED SUCCESSFULLY ***", DebugManager::COMPONENT_THUMBNAILER, $operationId);
            } else {
                $this->debugManager->logError("*** SOME THUMBNAILS FAILED ***", DebugManager::COMPONENT_THUMBNAILER, $operationId);
            }

            return $success;

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $this->debugManager->logError('Exception: ' . $e->getMessage(), DebugManager::COMPONENT_THUMBNAILER, $operationId);
            if ($errorStore) {
                $errorStore->addError('exception', $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Check if this thumbnailer supports the given media type
     */
    public function supports($mediaType): bool
    {
        $isVideo = strpos($mediaType, 'video/') === 0;
        return $isVideo;
    }

    /**
     * Get dimensions for thumbnail size
     */
    protected function getDimensionsForSize(string $size): int
    {
        switch ($size) {
            case 'large':
                return 800;
            case 'medium':
                return 400;
            case 'square':
                return 200;
            default:
                return 400;
        }
    }
}
