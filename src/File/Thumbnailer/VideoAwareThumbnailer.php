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
     * Create thumbnail using FFmpeg for videos, ImageMagick for everything else
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
     * Create video thumbnail using FFmpeg
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
     * Get video duration using FFprobe
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
     * Build FFmpeg command for thumbnail creation
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
     * Check if this thumbnailer can handle the given file
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
     * Check if FFmpeg is available
     */
    protected function checkFFmpegAvailable()
    {
        return file_exists($this->ffmpegPath) && is_executable($this->ffmpegPath);
    }

    /**
     * Get appropriate file extension for media type
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
     * Create direct ImageMagick resize for JPEG thumbnails
     * This bypasses Omeka's ImageMagick thumbnailer which adds problematic [0] frame syntax
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
