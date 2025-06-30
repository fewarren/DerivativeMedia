<?php
namespace DerivativeMedia\File\Thumbnailer;

use Omeka\File\Thumbnailer\ImageMagick;
use Omeka\File\TempFileFactory;
use Omeka\Stdlib\Cli;

/**
 * Video-Aware Thumbnailer
 * 
 * This thumbnailer extends the default ImageMagick thumbnailer to handle
 * video files using FFmpeg for thumbnail generation at specified percentages
 */
class VideoAwareThumbnailer extends ImageMagick
{
    /**
     * @var array Video MIME types that should use FFmpeg
     */
    protected $videoTypes = [
        'video/mp4',
        'video/mpeg',
        'video/ogg', 
        'video/quicktime',
        'video/webm',
        'video/x-ms-asf',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/mkv',
        'video/m4v'
    ];

    /**
     * @var string Path to FFmpeg binary
     */
    protected $ffmpegPath;

    /**
     * @var string Path to FFprobe binary  
     */
    protected $ffprobePath;

    /**
     * @var int Default thumbnail position percentage
     */
    protected $defaultPercentage = 25;

    /**
     * Initializes the VideoAwareThumbnailer with FFmpeg and FFprobe paths and default thumbnail percentage.
     *
     * @param array $options Optional configuration, including 'ffmpeg_path', 'ffprobe_path', and 'default_percentage'.
     */
    public function __construct(Cli $cli, TempFileFactory $tempFileFactory, array $options = [])
    {
        parent::__construct($cli, $tempFileFactory, $options);

        // Set FFmpeg paths
        $this->ffmpegPath = $options['ffmpeg_path'] ?? '/usr/bin/ffmpeg';
        $this->ffprobePath = $options['ffprobe_path'] ?? '/usr/bin/ffprobe';
        $this->defaultPercentage = $options['default_percentage'] ?? 25;

        // Debug logging
        error_log('VideoAwareThumbnailer: Constructed with FFmpeg: ' . $this->ffmpegPath . ', percentage: ' . $this->defaultPercentage);
    }

    /**
     * Creates a thumbnail for the source file, using FFmpeg for video files and ImageMagick for other file types.
     *
     * For video files, generates a thumbnail image at a specific position using FFmpeg. For JPEG thumbnails, performs a direct resize using ImageMagick. For other files, ensures the source file has the correct extension before delegating to the parent ImageMagick thumbnailer.
     *
     * @param string $strategy The thumbnailing strategy (e.g., 'square', 'default').
     * @param int|array $constraint The size constraint for the thumbnail.
     * @param array $options Optional settings for thumbnail generation.
     * @return string Path to the generated thumbnail image.
     * @throws \Exception If the source file does not exist or thumbnail creation fails.
     */
    public function create($strategy, $constraint, array $options = [])
    {
        $mediaType = $this->sourceFile->getMediaType();
        $sourcePath = $this->sourceFile->getTempPath();

        error_log('VideoAwareThumbnailer: create() called for media type: ' . $mediaType . ', strategy: ' . $strategy . ', constraint: ' . $constraint);
        error_log('VideoAwareThumbnailer: source path: ' . $sourcePath);
        error_log('VideoAwareThumbnailer: file exists: ' . (file_exists($sourcePath) ? 'YES' : 'NO'));
        if (file_exists($sourcePath)) {
            error_log('VideoAwareThumbnailer: file size: ' . filesize($sourcePath) . ' bytes');
        }

        // CRITICAL FIX: Check if this is already a processed thumbnail
        // If the source file has a .jpg extension, it's likely a thumbnail that needs resizing
        $hasJpgExtension = pathinfo($sourcePath, PATHINFO_EXTENSION) === 'jpg';

        if ($hasJpgExtension) {
            error_log('VideoAwareThumbnailer: Source has .jpg extension, treating as processed thumbnail');
            error_log('VideoAwareThumbnailer: File exists: ' . (file_exists($sourcePath) ? 'YES' : 'NO'));

            if (!file_exists($sourcePath)) {
                error_log('VideoAwareThumbnailer: CRITICAL - Source file does not exist: ' . $sourcePath);
                throw new \Exception('Source thumbnail file does not exist: ' . $sourcePath);
            }

            // For JPEG thumbnails, create a simple resize using ImageMagick directly
            // Bypass Omeka's ImageMagick thumbnailer which adds [0] frame syntax
            error_log('VideoAwareThumbnailer: Creating direct ImageMagick resize for JPEG thumbnail');
            return $this->createDirectImageResize($sourcePath, $strategy, $constraint, $options);
        }

        // Use FFmpeg for video files (original video sources)
        if (in_array($mediaType, $this->videoTypes)) {
            error_log('VideoAwareThumbnailer: Using FFmpeg for video file: ' . $mediaType);
            return $this->createVideoThumbnail($strategy, $constraint, $options);
        }

        // For non-video files, ensure the source file has proper extension for ImageMagick
        error_log('VideoAwareThumbnailer: Using ImageMagick for non-video file: ' . $mediaType);

        // Check if the source file has an extension
        $hasExtension = pathinfo($sourcePath, PATHINFO_EXTENSION) !== '';
        error_log('VideoAwareThumbnailer: source file has extension: ' . ($hasExtension ? 'YES' : 'NO'));

        if (!$hasExtension) {
            error_log('VideoAwareThumbnailer: Adding extension for ImageMagick compatibility');

            // Add appropriate extension based on media type
            $extension = $this->getExtensionForMediaType($mediaType);
            $sourcePathWithExtension = $sourcePath . '.' . $extension;

            error_log('VideoAwareThumbnailer: Copying to path with extension: ' . $sourcePathWithExtension);

            // Copy the file to include extension
            if (copy($sourcePath, $sourcePathWithExtension)) {
                error_log('VideoAwareThumbnailer: Successfully copied file with extension');

                // Create a new temp file with extension
                $tempFileWithExtension = $this->tempFileFactory->build();
                $tempFileWithExtension->setTempPath($sourcePathWithExtension);
                $tempFileWithExtension->setSourceName($this->sourceFile->getSourceName());
                $tempFileWithExtension->setStorageId($this->sourceFile->getStorageId());

                // Temporarily replace source file
                $originalSourceFile = $this->sourceFile;
                $this->sourceFile = $tempFileWithExtension;

                try {
                    error_log('VideoAwareThumbnailer: Calling parent ImageMagick with extension');
                    $result = parent::create($strategy, $constraint, $options);

                    // Clean up
                    unlink($sourcePathWithExtension);
                    $this->sourceFile = $originalSourceFile;

                    error_log('VideoAwareThumbnailer: Parent ImageMagick succeeded');
                    return $result;
                } catch (\Exception $e) {
                    error_log('VideoAwareThumbnailer: Parent ImageMagick failed: ' . $e->getMessage());
                    // Clean up and restore
                    unlink($sourcePathWithExtension);
                    $this->sourceFile = $originalSourceFile;
                    throw $e;
                }
            } else {
                error_log('VideoAwareThumbnailer: Failed to copy file with extension');
            }
        }

        error_log('VideoAwareThumbnailer: Calling parent ImageMagick directly');
        return parent::create($strategy, $constraint, $options);
    }

    /**
     * Generates a video thumbnail using FFmpeg and returns the path to the created image.
     *
     * Extracts a single frame from the video at a calculated position (based on duration and percentage option or default), applies scaling or cropping according to the specified strategy, and outputs a JPEG thumbnail. The resulting image is copied to a temporary path without an extension for compatibility with downstream processing. Throws an exception if FFmpeg fails or the thumbnail is not created.
     *
     * @param string $strategy The thumbnailing strategy (e.g., 'square', 'default').
     * @param int|array $constraint The size constraint for the thumbnail.
     * @param array $options Optional settings, including 'percentage' for capture position.
     * @return string Path to the generated thumbnail image (without extension).
     * @throws \Exception If FFmpeg fails or the thumbnail cannot be created.
     */
    protected function createVideoThumbnail($strategy, $constraint, array $options = [])
    {
        $sourcePath = $this->sourceFile->getTempPath();
        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath();

        // CRITICAL FIX: Add .jpg extension so FFmpeg knows the output format
        // But keep the original temp path for Omeka's pipeline
        $tempPathWithExtension = $tempPath . '.jpg';

        // Get video duration
        $duration = $this->getVideoDuration($sourcePath);
        if ($duration <= 0) {
            // Fallback to 10 seconds if duration can't be determined
            $position = 10;
        } else {
            // Calculate position based on percentage
            $percentage = $options['percentage'] ?? $this->defaultPercentage;
            $position = ($duration * $percentage) / 100;
        }

        // Build FFmpeg command based on strategy
        $command = $this->buildFFmpegCommand($sourcePath, $tempPathWithExtension, $position, $strategy, $constraint, $options);
        
        // Execute FFmpeg command directly (bypass Omeka CLI wrapper)
        error_log('VideoAwareThumbnailer: FFmpeg command: ' . $command);

        $output = [];
        $result = 0;
        exec($command . ' 2>&1', $output, $result);

        $outputString = implode("\n", $output);
        error_log('VideoAwareThumbnailer: FFmpeg result: ' . $result);
        error_log('VideoAwareThumbnailer: FFmpeg output: ' . $outputString);

        if (0 !== $result) {
            throw new \Exception(sprintf('FFmpeg command failed (exit code %d): %s. Output: %s', $result, $command, $outputString));
        }

        if (!file_exists($tempPathWithExtension) || 0 === filesize($tempPathWithExtension)) {
            throw new \Exception(sprintf('FFmpeg failed to create thumbnail. File exists: %s, Size: %d. Output: %s',
                file_exists($tempPathWithExtension) ? 'yes' : 'no',
                file_exists($tempPathWithExtension) ? filesize($tempPathWithExtension) : 0,
                $outputString
            ));
        }

        error_log('VideoAwareThumbnailer: FFmpeg created thumbnail: ' . $tempPathWithExtension . ' (size: ' . filesize($tempPathWithExtension) . ' bytes)');

        // CRITICAL FIX: Copy the thumbnail to the expected path WITHOUT extension
        // Omeka's thumbnail system expects files without extensions in temp paths
        if (!copy($tempPathWithExtension, $tempPath)) {
            throw new \Exception('Failed to copy generated thumbnail to expected location');
        }

        error_log('VideoAwareThumbnailer: Copied to final path: ' . $tempPath . ' (size: ' . filesize($tempPath) . ' bytes)');

        // Clean up the temporary file with extension
        unlink($tempPathWithExtension);

        error_log('VideoAwareThumbnailer: Final thumbnail ready at: ' . $tempPath . ' (size: ' . filesize($tempPath) . ' bytes)');

        // Return the temp path WITHOUT extension - this is what Omeka expects
        // The parent ImageMagick thumbnailer will handle any further processing
        return $tempPath;
    }

    /**
     * Retrieves the duration of a video file in seconds using FFprobe.
     *
     * @param string $videoPath Path to the video file.
     * @return float Duration of the video in seconds, or 0 if unavailable.
     */
    protected function getVideoDuration($videoPath)
    {
        $command = sprintf(
            '%s -v quiet -show_entries format=duration -of csv="p=0" %s 2>/dev/null',
            escapeshellarg($this->ffprobePath),
            escapeshellarg($videoPath)
        );

        $output = shell_exec($command);
        return $output ? (float) trim($output) : 0;
    }

    /**
     * Constructs the FFmpeg command to generate a video thumbnail image at a specified position and size.
     *
     * The command is built according to the provided strategy: 'square' applies cropping for a square thumbnail, while other strategies scale the image to fit within the given constraint.
     *
     * @param string $sourcePath Path to the source video file.
     * @param string $outputPath Path where the generated thumbnail image will be saved.
     * @param float $position Timestamp (in seconds) at which to capture the thumbnail frame.
     * @param string $strategy Thumbnailing strategy ('square' for cropped square, otherwise scaled).
     * @param int $constraint Size constraint for the thumbnail (dimension for 'square', width otherwise).
     * @param array $options Additional options for thumbnail generation.
     * @return string The complete FFmpeg command to execute.
     */
    protected function buildFFmpegCommand($sourcePath, $outputPath, $position, $strategy, $constraint, $options)
    {
        // Build command as array for proper escaping
        $args = [
            escapeshellarg($this->ffmpegPath),
            '-y', // Overwrite output file
            '-i', escapeshellarg($sourcePath),
            '-ss', escapeshellarg(sprintf('%.2f', $position)), // Format position to 2 decimal places
            '-vframes', '1'
        ];

        // Apply video filters based on strategy
        if ($strategy === 'square') {
            // Square thumbnail with cropping
            $size = $constraint; // For square, constraint is the dimension
            $args[] = '-vf';
            $args[] = escapeshellarg("scale={$size}:{$size}:force_original_aspect_ratio=increase,crop={$size}:{$size}");
        } else {
            // Default strategy - scale to fit within constraint
            $args[] = '-vf';
            $args[] = escapeshellarg("scale={$constraint}:-1");
        }

        // CRITICAL FIX: Add format specification like the working manual command
        $args[] = '-f';
        $args[] = 'image2';

        // Quality settings (use same as manual command)
        $args[] = '-q:v';
        $args[] = '2'; // High quality

        // Output path
        $args[] = escapeshellarg($outputPath);

        return implode(' ', $args);
    }

    /**
     * Determines if the thumbnailer can process the current file.
     *
     * Returns true if the file is a supported video type and FFmpeg is available, or if the parent thumbnailer can handle the file.
     *
     * @return bool True if the file can be thumbnailed, false otherwise.
     */
    public function canThumbnail()
    {
        $mediaType = $this->sourceFile->getMediaType();
        
        // Can handle videos with FFmpeg or anything ImageMagick can handle
        if (in_array($mediaType, $this->videoTypes)) {
            return $this->checkFFmpegAvailable();
        }
        
        return parent::canThumbnail();
    }

    /**
     * Determines whether the FFmpeg binary is present and executable.
     *
     * @return bool True if FFmpeg is available; otherwise, false.
     */
    protected function checkFFmpegAvailable()
    {
        return file_exists($this->ffmpegPath) && is_executable($this->ffmpegPath);
    }

    /**
     * Returns the appropriate file extension for a given media type.
     *
     * Defaults to 'jpg' if the media type is not recognized.
     *
     * @param string $mediaType The MIME type of the media.
     * @return string The corresponding file extension.
     */
    protected function getExtensionForMediaType($mediaType)
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        return $extensions[$mediaType] ?? 'jpg'; // Default to jpg
    }

    /**
     * Resizes a JPEG image directly using ImageMagick, bypassing frame syntax.
     *
     * Creates a resized or cropped thumbnail from a JPEG source file using ImageMagick's `convert` command, avoiding the `[0]` frame syntax that can cause issues with certain JPEGs. Supports both square cropping and proportional resizing strategies.
     *
     * @param string $sourcePath Path to the source JPEG file.
     * @param string $strategy Thumbnailing strategy, e.g., 'square' for cropped square thumbnails.
     * @param int $constraint Size constraint for the thumbnail (width/height in pixels).
     * @param array $options Optional additional options for thumbnailing.
     * @return string Path to the generated thumbnail image.
     * @throws \Exception If the source file is missing, empty, or if ImageMagick fails to create the thumbnail.
     */
    protected function createDirectImageResize($sourcePath, $strategy, $constraint, array $options = [])
    {
        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath();

        error_log('VideoAwareThumbnailer: Direct resize from ' . $sourcePath . ' to ' . $tempPath);

        // Build ImageMagick command without [0] frame syntax
        if ($strategy === 'square') {
            // Square thumbnail with cropping
            $size = $constraint;
            $command = sprintf(
                'convert %s -auto-orient -background white +repage -alpha remove -resize %dx%d^ -gravity center -crop %dx%d+0+0 %s',
                escapeshellarg($sourcePath),
                $size, $size,
                $size, $size,
                escapeshellarg($tempPath)
            );
        } else {
            // Default strategy - scale to fit within constraint
            $command = sprintf(
                'convert %s -auto-orient -background white +repage -alpha remove -thumbnail %dx%d> %s',
                escapeshellarg($sourcePath),
                $constraint, $constraint,
                escapeshellarg($tempPath)
            );
        }

        error_log('VideoAwareThumbnailer: ImageMagick command: ' . $command);

        // Check source file before running command
        if (!file_exists($sourcePath)) {
            throw new \Exception('Source file does not exist: ' . $sourcePath);
        }

        $sourceSize = filesize($sourcePath);
        error_log('VideoAwareThumbnailer: Source file size: ' . $sourceSize . ' bytes');

        if ($sourceSize === 0) {
            throw new \Exception('Source file is empty: ' . $sourcePath);
        }

        // Execute ImageMagick command
        $output = [];
        $result = 0;
        exec($command . ' 2>&1', $output, $result);

        $outputString = implode("\n", $output);
        error_log('VideoAwareThumbnailer: ImageMagick result: ' . $result);
        error_log('VideoAwareThumbnailer: ImageMagick output: ' . $outputString);

        // Check if output file was created
        if (file_exists($tempPath)) {
            error_log('VideoAwareThumbnailer: Output file created, size: ' . filesize($tempPath) . ' bytes');
        } else {
            error_log('VideoAwareThumbnailer: Output file was NOT created');
        }

        if (0 !== $result) {
            throw new \Exception(sprintf('ImageMagick command failed (exit code %d): %s. Output: %s', $result, $command, $outputString));
        }

        if (!file_exists($tempPath) || 0 === filesize($tempPath)) {
            throw new \Exception(sprintf('ImageMagick failed to create thumbnail. File exists: %s, Size: %d. Output: %s',
                file_exists($tempPath) ? 'yes' : 'no',
                file_exists($tempPath) ? filesize($tempPath) : 0,
                $outputString
            ));
        }

        error_log('VideoAwareThumbnailer: Direct resize successful, created: ' . $tempPath . ' (size: ' . filesize($tempPath) . ' bytes)');

        return $tempPath;
    }
}
