<?php declare(strict_types=1);

namespace DerivativeMedia\File\Thumbnailer;

use Omeka\File\Thumbnailer\ThumbnailerInterface;
use Omeka\Stdlib\ErrorStore;

class VideoThumbnailer implements ThumbnailerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * Initializes the VideoThumbnailer with optional configuration settings.
     *
     * @param array $options Optional configuration options such as FFmpeg/FFprobe paths and thumbnail timing percentage.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
        error_log('DerivativeMedia VideoThumbnailer: Constructor called');
    }

    /**
     * Generates thumbnails from a video file at specified destination paths and sizes.
     *
     * For each requested size, extracts a frame from the video at a calculated position (based on a configurable percentage of the video's duration or defaults to 1 second if duration is unavailable) and saves it as a thumbnail image. Square thumbnails are cropped to a square aspect ratio, while others are scaled proportionally. Returns true if all thumbnails are created successfully; otherwise, returns false. Errors encountered during processing can be recorded in the provided error store.
     *
     * @param string $sourcePath Path to the source video file.
     * @param array $destPaths Associative array mapping size labels to destination file paths.
     * @param ErrorStore|null $errorStore Optional error store for recording processing errors.
     * @return bool True if all thumbnails are created successfully, false otherwise.
     */
    public function createThumbnails($sourcePath, $destPaths, ErrorStore $errorStore = null): bool
    {
        error_log("DerivativeMedia VideoThumbnailer: createThumbnails called");
        error_log("DerivativeMedia VideoThumbnailer: Source: $sourcePath");
        error_log("DerivativeMedia VideoThumbnailer: Destinations: " . print_r($destPaths, true));

        try {
            // Get FFmpeg path from options or use default
            $ffmpegPath = $this->options['ffmpeg_path'] ?? '/usr/bin/ffmpeg';
            $ffprobePath = $this->options['ffprobe_path'] ?? '/usr/bin/ffprobe';
            $thumbnailPercentage = $this->options['thumbnail_percentage'] ?? 25;

            error_log("DerivativeMedia VideoThumbnailer: Using FFmpeg: $ffmpegPath");

            // Check if source file exists
            if (!file_exists($sourcePath)) {
                error_log("DerivativeMedia VideoThumbnailer: Source file not found: $sourcePath");
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
                error_log("DerivativeMedia VideoThumbnailer: Could not determine video duration, using 1 second");
                $position = 1;
            } else {
                $position = ($duration * $thumbnailPercentage) / 100;
                error_log("DerivativeMedia VideoThumbnailer: Video duration: {$duration}s, thumbnail at: {$position}s");
            }

            $success = true;

            // Create each required thumbnail size
            foreach ($destPaths as $size => $destPath) {
                error_log("DerivativeMedia VideoThumbnailer: Creating $size thumbnail: $destPath");

                // Ensure destination directory exists
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                    error_log("DerivativeMedia VideoThumbnailer: Created directory: $destDir");
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

                error_log("DerivativeMedia VideoThumbnailer: FFmpeg command: $cmd");
                
                $output = shell_exec($cmd);
                error_log("DerivativeMedia VideoThumbnailer: FFmpeg output: $output");

                // Check if thumbnail was created successfully
                if (file_exists($destPath) && filesize($destPath) > 0) {
                    error_log("DerivativeMedia VideoThumbnailer: Successfully created $size thumbnail: $destPath");
                } else {
                    error_log("DerivativeMedia VideoThumbnailer: Failed to create $size thumbnail: $destPath");
                    if ($errorStore) {
                        $errorStore->addError($size, "Failed to create $size thumbnail");
                    }
                    $success = false;
                }
            }

            if ($success) {
                error_log("DerivativeMedia VideoThumbnailer: *** ALL THUMBNAILS CREATED SUCCESSFULLY ***");
            } else {
                error_log("DerivativeMedia VideoThumbnailer: *** SOME THUMBNAILS FAILED ***");
            }

            return $success;

        } catch (\Exception $e) {
            error_log('DerivativeMedia VideoThumbnailer: Exception: ' . $e->getMessage());
            if ($errorStore) {
                $errorStore->addError('exception', $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Determines whether the thumbnailer supports the specified media type.
     *
     * @param string $mediaType The media type to check.
     * @return bool True if the media type is a video, otherwise false.
     */
    public function supports($mediaType): bool
    {
        $isVideo = strpos($mediaType, 'video/') === 0;
        error_log("DerivativeMedia VideoThumbnailer: supports($mediaType) = " . ($isVideo ? 'true' : 'false'));
        return $isVideo;
    }

    /**
     * Returns the pixel dimension corresponding to the specified thumbnail size.
     *
     * @param string $size The size label ('large', 'medium', 'square', or other).
     * @return int The pixel dimension for the given size.
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
