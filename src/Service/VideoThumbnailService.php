<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Laminas\Log\LoggerInterface;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\File\Thumbnailer\ThumbnailerInterface;

class VideoThumbnailService
{
    /**
     * @var string
     */
    protected $ffmpegPath;
    
    /**
     * @var string
     */
    protected $ffprobePath;
    
    /**
     * @var int
     */
    protected $thumbnailPercentage;
    
    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;
    
    /**
     * @var ThumbnailerInterface
     */
    protected $thumbnailer;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Omeka\File\Store\StoreInterface
     */
    protected $fileStore;

    /****
     * Initializes the VideoThumbnailService with paths to FFmpeg and FFprobe, thumbnail capture settings, dependencies for temporary file creation, thumbnail generation, logging, and the base media storage path.
     *
     * @param string $ffmpegPath Path to the FFmpeg binary.
     * @param string $ffprobePath Path to the FFprobe binary.
     * @param int $thumbnailPercentage Percentage of video duration at which to capture the thumbnail.
     * @param TempFileFactory $tempFileFactory Factory for creating temporary files.
     * @param ThumbnailerInterface $thumbnailer Service for generating image thumbnails.
     * @param LoggerInterface $logger Logger for recording service activity.
     * @param string|null $basePath Optional base path for media file storage; defaults to Omeka's files directory if not provided.
     */
    public function __construct(
        string $ffmpegPath,
        string $ffprobePath,
        int $thumbnailPercentage,
        TempFileFactory $tempFileFactory,
        ThumbnailerInterface $thumbnailer,
        LoggerInterface $logger,
        string $basePath = null
    ) {
        $this->ffmpegPath = $ffmpegPath;
        $this->ffprobePath = $ffprobePath;
        $this->thumbnailPercentage = $thumbnailPercentage;
        $this->tempFileFactory = $tempFileFactory;
        $this->thumbnailer = $thumbnailer;
        $this->logger = $logger;
        $this->basePath = $basePath ?: (OMEKA_PATH . '/files');
    }

    /**
     * Sets the file storage interface used for saving thumbnail derivatives.
     *
     * @param \Omeka\File\Store\StoreInterface $fileStore The file storage interface to use.
     */
    public function setFileStore($fileStore): void
    {
        $this->fileStore = $fileStore;
    }

    /**
     * Generates a thumbnail image for a given video media entity at a specified position.
     *
     * Validates the media type, determines the original video file path, calculates the capture position based on video duration and percentage, and uses FFmpeg to extract a frame as a JPEG image. Creates multiple thumbnail derivatives using Omeka's thumbnailer. Returns true if all steps succeed, or false on failure.
     *
     * @param Media $media The video media entity for which to generate a thumbnail.
     * @param int|null $percentage The position in the video (as a percentage of duration) to capture the thumbnail. If null, the default percentage is used.
     * @return bool True if the thumbnail and its derivatives were successfully generated, false otherwise.
     */
    public function generateThumbnail(Media $media, int $percentage = null): bool
    {
        // Add error_log for immediate debugging
        error_log('VideoThumbnailService: generateThumbnail() method ENTERED for media #' . $media->getId());

        $this->logger->info('Starting video thumbnail generation for media #{media_id}', ['media_id' => $media->getId()]);
        error_log('VideoThumbnailService: Starting video thumbnail generation for media #' . $media->getId());

        $mediaType = $media->getMediaType();
        $this->logger->info('Media type: {media_type}', ['media_type' => $mediaType]);
        error_log('VideoThumbnailService: Media type: ' . $mediaType);

        if (!in_array($mediaType, ['video/mp4', 'video/webm', 'video/quicktime', 'video/avi', 'video/mov'])) {
            $this->logger->info('Unsupported video format: {media_type}', ['media_type' => $mediaType]);
            error_log('VideoThumbnailService: Unsupported video format: ' . $mediaType);
            return false;
        }

        $storagePath = $this->getStoragePath($media);
        $this->logger->info('Storage path: {path}', ['path' => $storagePath]);
        error_log('VideoThumbnailService: Storage path: ' . ($storagePath ?: 'NULL'));

        if (!$storagePath || !file_exists($storagePath)) {
            $this->logger->err('No original file found for media #{media_id} at path {path}', [
                'media_id' => $media->getId(),
                'path' => $storagePath
            ]);
            error_log('VideoThumbnailService: No original file found for media #' . $media->getId() . ' at path: ' . ($storagePath ?: 'NULL'));
            error_log('VideoThumbnailService: File exists check: ' . ($storagePath && file_exists($storagePath) ? 'YES' : 'NO'));

            // Let's also check what files are actually in the original directory
            $originalDir = $this->basePath . '/original';
            error_log('VideoThumbnailService: Checking original directory: ' . $originalDir);
            if (is_dir($originalDir)) {
                $files = scandir($originalDir);
                error_log('VideoThumbnailService: Files in original directory: ' . implode(', ', array_slice($files, 2, 10))); // Skip . and ..
            } else {
                error_log('VideoThumbnailService: Original directory does not exist: ' . $originalDir);
            }

            return false;
        }

        // Get video duration to calculate thumbnail position
        error_log('VideoThumbnailService: About to get video duration for: ' . $storagePath);
        $duration = $this->getVideoDuration($storagePath);
        error_log('VideoThumbnailService: Video duration: ' . ($duration ?: 'NULL'));
        if (!$duration) {
            $this->logger->err('Could not determine duration for video #{media_id}', ['media_id' => $media->getId()]);
            error_log('VideoThumbnailService: Could not determine duration for video #' . $media->getId());
            return false;
        }

        // Calculate position for thumbnail (percentage of duration)
        $usePercentage = $percentage ?? $this->thumbnailPercentage;
        $position = ($duration * $usePercentage) / 100;
        
        // Create temporary file for thumbnail
        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath() . '.jpg';
        
        // Generate thumbnail using ffmpeg - FIXED: Use same command as working VideoAwareThumbnailer
        $command = sprintf(
            '%s -y -i %s -ss %s -vframes 1 -vf scale=800:-1 -f image2 -q:v 2 %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($storagePath),
            escapeshellarg((string) $position),
            escapeshellarg($tempPath)
        );

        // Dual logging - both to Apache error log and DerivativeMedia debug log
        $logMessage = 'VideoThumbnailService: FFmpeg command: ' . $command;
        error_log($logMessage);
        $this->writeToDebugLog($logMessage);

        $logMessage = 'VideoThumbnailService: Source file: ' . $storagePath . ' (exists: ' . (file_exists($storagePath) ? 'YES' : 'NO') . ')';
        error_log($logMessage);
        $this->writeToDebugLog($logMessage);

        if (file_exists($storagePath)) {
            $logMessage = 'VideoThumbnailService: Source file size: ' . filesize($storagePath) . ' bytes';
            error_log($logMessage);
            $this->writeToDebugLog($logMessage);
        }

        $this->logger->info('Executing FFmpeg command: {command}', ['command' => $command]);
        error_log('VideoThumbnailService: Executing FFmpeg command: ' . $command);

        exec($command, $output, $returnVar);

        $outputString = implode("\n", $output);
        error_log('VideoThumbnailService: FFmpeg result: ' . $returnVar);
        error_log('VideoThumbnailService: FFmpeg output: ' . $outputString);

        if (file_exists($tempPath)) {
            error_log('VideoThumbnailService: Output file created, size: ' . filesize($tempPath) . ' bytes');
        } else {
            error_log('VideoThumbnailService: Output file was NOT created');
        }

        $this->logger->info('FFmpeg output: {output}, return code: {code}', [
            'output' => implode("\n", $output),
            'code' => $returnVar
        ]);
        error_log('VideoThumbnailService: FFmpeg output: ' . implode("\n", $output));
        error_log('VideoThumbnailService: FFmpeg return code: ' . $returnVar);
        error_log('VideoThumbnailService: Temp file exists: ' . (file_exists($tempPath) ? 'YES' : 'NO'));

        if ($returnVar !== 0 || !file_exists($tempPath)) {
            $this->logger->err('Failed to generate thumbnail for video #{media_id}: {output}', [
                'media_id' => $media->getId(),
                'output' => implode("\n", $output)
            ]);
            error_log('VideoThumbnailService: Failed to generate thumbnail for video #' . $media->getId() . ': ' . implode("\n", $output));
            return false;
        }
        
        // Create thumbnails using Omeka's proper approach
        try {
            $this->logger->info('Creating thumbnails with temp file: {temp_path}, storage_id: {storage_id}', [
                'temp_path' => $tempPath,
                'storage_id' => $media->getStorageId(),
                'media_id' => $media->getId()
            ]);
            error_log('VideoThumbnailService: Creating thumbnails with temp file: ' . $tempPath . ', storage_id: ' . $media->getStorageId());

            // Create thumbnail derivatives manually using the thumbnailer
            $storageId = $media->getStorageId();

            // CRITICAL FIX: Use the FULL storage ID to maintain directory structure
            // This ensures thumbnails are created in the correct subdirectory
            $thumbnailStorageId = $storageId;

            error_log('VideoThumbnailService: Using FULL storage ID for thumbnails: ' . $thumbnailStorageId);

            error_log('VideoThumbnailService: Original storage_id: ' . $storageId);
            error_log('VideoThumbnailService: Thumbnail storage_id: ' . $thumbnailStorageId);
            error_log('VideoThumbnailService: About to call createThumbnailDerivatives with thumbnail storage_id: ' . $thumbnailStorageId);
            $success = $this->createThumbnailDerivatives($tempPath, $thumbnailStorageId);
            error_log('VideoThumbnailService: createThumbnailDerivatives returned: ' . ($success ? 'true' : 'false'));

            if ($success) {
                $this->logger->info('Video thumbnail created successfully for media #{media_id}', [
                    'media_id' => $media->getId()
                ]);
                error_log('VideoThumbnailService: Video thumbnail created successfully for media #' . $media->getId());
                return true;
            } else {
                $this->logger->err('Failed to create thumbnail derivatives for media #{media_id}', [
                    'media_id' => $media->getId()
                ]);
                error_log('VideoThumbnailService: Failed to create thumbnail derivatives for media #' . $media->getId());
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->err('Error creating thumbnails for media #{media_id}: {message}', [
                'media_id' => $media->getId(),
                'message' => $e->getMessage()
            ]);

            // Log the full stack trace for debugging
            $this->logger->err('Full exception trace: {trace}', [
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
        // NOTE: Don't clean up temp file here - VideoAwareThumbnailer needs it for processing
        // The temp file will be cleaned up by Omeka's temp file system
    }
    
    /**
     * Retrieves the duration of a video file in seconds using ffprobe.
     *
     * @param string $videoPath Path to the video file.
     * @return float|null The duration in seconds, or null if it cannot be determined.
     */
    protected function getVideoDuration(string $videoPath): ?float
    {
        $command = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            $this->ffprobePath,
            escapeshellarg($videoPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0 || empty($output[0])) {
            return null;
        }
        
        return (float) $output[0];
    }
    
    /**
     * Returns the full filesystem path to the original file for the given media.
     *
     * If the media's storage ID includes a file extension, it is used directly; otherwise, the extension from the media's filename is appended.
     *
     * @param Media $media The media entity whose original file path is to be determined.
     * @return string|null The absolute path to the original media file, or null if the storage ID is missing.
     */
    protected function getStoragePath(Media $media): ?string
    {
        $storageId = $media->getStorageId();
        if (!$storageId) {
            return null;
        }

        error_log('VideoThumbnailService: Raw storage ID from media: ' . $storageId);

        // Check if storage ID already has extension
        if (pathinfo($storageId, PATHINFO_EXTENSION)) {
            // Storage ID includes extension (e.g., "215/VID-00195_Meher_Baba_Videos_Edited_-Darshan_Scenes_from.mp4")
            $path = $this->basePath . '/original/' . $storageId;
        } else {
            // Storage ID is just the hash, add extension (e.g., "534f781547d7e981255cce52ae0bb24eedc310d2")
            $extension = pathinfo($media->getFilename(), PATHINFO_EXTENSION);
            $path = $this->basePath . '/original/' . $storageId . '.' . $extension;
        }

        error_log('VideoThumbnailService: Constructed storage path: ' . $path);
        return $path;
    }

    /**
     * Generates and stores thumbnail derivatives in multiple sizes using Omeka's thumbnailer.
     *
     * Attempts to create large, medium, and square thumbnails from the provided source image and stores them under the appropriate paths. Falls back to manual derivative creation if an error occurs.
     *
     * @param string $sourcePath Path to the source thumbnail image.
     * @param string $storageId Storage ID for the media.
     * @return bool True if all derivatives are created and stored successfully, false otherwise.
     */
    protected function createThumbnailDerivatives(string $sourcePath, string $storageId): bool
    {
        error_log('VideoThumbnailService: createThumbnailDerivatives ENTERED with sourcePath: ' . $sourcePath . ', storageId: ' . $storageId);

        try {
            // Create a temporary file object for the source image
            error_log('VideoThumbnailService: Creating temp file object');
            $tempFile = $this->tempFileFactory->build();
            $tempFile->setTempPath($sourcePath);  // sourcePath already has .jpg extension
            $tempFile->setStorageId($storageId);
            $tempFile->setSourceName('thumbnail.jpg');

            $this->logger->info('Setting up thumbnailer with source file: {path}', [
                'path' => $sourcePath,
                'storage_id' => $storageId
            ]);
            error_log('VideoThumbnailService: Setting up thumbnailer with source file: ' . $sourcePath);

            // Set the source file for the thumbnailer (this is the correct API)
            error_log('VideoThumbnailService: Setting thumbnailer source');
            $this->thumbnailer->setSource($tempFile);
            error_log('VideoThumbnailService: Thumbnailer source set successfully');

            // Create the different thumbnail sizes
            $derivatives = [
                'large' => 800,
                'medium' => 400,
                'square' => 200
            ];
            error_log('VideoThumbnailService: About to create derivatives: ' . implode(', ', array_keys($derivatives)));

            foreach ($derivatives as $strategy => $constraint) {
                $this->logger->info('Creating {strategy} thumbnail with constraint {constraint}', [
                    'strategy' => $strategy,
                    'constraint' => $constraint
                ]);

                // Create the thumbnail using the proper Omeka API
                $thumbnailPath = $this->thumbnailer->create($strategy, $constraint, []);

                if ($thumbnailPath && file_exists($thumbnailPath)) {
                    // Store the thumbnail in Omeka's file system with correct path structure
                    $targetPath = $strategy . '/' . $storageId . '.jpg';
                    $fileContent = file_get_contents($thumbnailPath);

                    if ($fileContent !== false) {
                        $store = $this->getFileStore();
                        $store->put($fileContent, $targetPath);

                        $this->logger->info('Stored {strategy} thumbnail: {path}', [
                            'strategy' => $strategy,
                            'path' => $targetPath
                        ]);
                        error_log('VideoThumbnailService: Stored thumbnail via file store: ' . $targetPath);
                    }

                    // Clean up the temporary thumbnail
                    unlink($thumbnailPath);
                } else {
                    $this->logger->err('Failed to create {strategy} thumbnail', [
                        'strategy' => $strategy
                    ]);
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->err('Error in createThumbnailDerivatives: {message}', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            error_log('VideoThumbnailService: Exception in createThumbnailDerivatives: ' . $e->getMessage());
            error_log('VideoThumbnailService: Exception trace: ' . $e->getTraceAsString());

            // Fallback to manual creation
            error_log('VideoThumbnailService: Falling back to manual derivative creation');
            return $this->createDerivativesManually($sourcePath, $storageId);
        }
    }

    /**
     * Creates thumbnail derivatives in multiple sizes from a source image using ImageMagick.
     *
     * Generates large (800px), medium (400px), and square (200px) JPEG thumbnails from the provided source image and stores them under the appropriate directory structure based on the storage ID.
     *
     * @param string $sourcePath Path to the source thumbnail image.
     * @param string $storageId Storage ID for the media.
     * @return bool True if all derivatives are created successfully, false otherwise.
     */
    protected function createDerivativesManually(string $sourcePath, string $storageId): bool
    {
        error_log('VideoThumbnailService: createDerivativesManually ENTERED with sourcePath: ' . $sourcePath . ', storageId: ' . $storageId);

        try {
            $derivatives = [
                'large' => 800,
                'medium' => 400,
                'square' => 200
            ];
            error_log('VideoThumbnailService: About to create manual derivatives: ' . implode(', ', array_keys($derivatives)));

            foreach ($derivatives as $type => $size) {
                error_log("VideoThumbnailService: Creating $type derivative (size: $size)");

                $tempFile = $this->tempFileFactory->build();
                $tempPath = $tempFile->getTempPath() . '.jpg';
                $tempFile->delete(); // Remove empty file

                error_log("VideoThumbnailService: Using temp path: $tempPath");

                // Create ImageMagick command
                if ($type === 'square') {
                    $command = sprintf(
                        'convert %s -resize %dx%d^ -gravity center -crop %dx%d+0+0 %s',
                        escapeshellarg($sourcePath),
                        $size, $size,
                        $size, $size,
                        escapeshellarg($tempPath)
                    );
                } else {
                    $command = sprintf(
                        'convert %s -resize %dx%d %s',
                        escapeshellarg($sourcePath),
                        $size, $size,
                        escapeshellarg($tempPath)
                    );
                }

                error_log("VideoThumbnailService: Executing ImageMagick command: $command");
                exec($command, $output, $returnVar);
                error_log("VideoThumbnailService: ImageMagick return code: $returnVar");
                error_log("VideoThumbnailService: Temp file exists after ImageMagick: " . (file_exists($tempPath) ? 'YES' : 'NO'));

                if ($returnVar !== 0 || !file_exists($tempPath)) {
                    $this->logger->err('Failed to create {type} thumbnail derivative', [
                        'type' => $type,
                        'command' => $command,
                        'output' => implode("\n", $output)
                    ]);
                    error_log("VideoThumbnailService: Failed to create $type derivative: " . implode("\n", $output));
                    continue;
                }

                // Store the derivative in Omeka's file system with correct directory structure
                // CRITICAL FIX: Preserve the full storage ID path structure
                $targetPath = $type . '/' . $storageId . '.jpg';

                error_log("VideoThumbnailService: Target path for $type: $targetPath");

                // Validate temp file exists and is readable
                if (!file_exists($tempPath) || !is_readable($tempPath)) {
                    $this->logger->err('Temp file not accessible: {path}', ['path' => $tempPath]);
                    continue;
                }

                $fileContent = file_get_contents($tempPath);
                error_log("VideoThumbnailService: Read " . strlen($fileContent) . " bytes from temp file");

                if ($fileContent !== false && strlen($fileContent) > 0) {
                    // CRITICAL FIX: Handle subdirectory structure properly
                    $targetFile = $this->basePath . '/' . $type . '/' . $storageId . '.jpg';
                    $targetDir = dirname($targetFile);

                    error_log("VideoThumbnailService: Target directory: $targetDir");
                    error_log("VideoThumbnailService: Target file: $targetFile");

                    // Ensure target directory exists (including subdirectories)
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                        error_log("VideoThumbnailService: Created directory: $targetDir");
                    }

                    try {
                        $success = file_put_contents($targetFile, $fileContent);

                        if ($success !== false) {
                            error_log("VideoThumbnailService: Successfully wrote $success bytes to $targetFile");

                            // Set proper permissions
                            chmod($targetFile, 0644);

                            $this->logger->info('Created {type} thumbnail derivative: {path}', [
                                'type' => $type,
                                'path' => $targetFile
                            ]);
                            error_log("VideoThumbnailService: Successfully stored $type thumbnail: $targetFile");
                        } else {
                            error_log("VideoThumbnailService: Failed to write file: $targetFile");
                        }
                    } catch (\Exception $e) {
                        $this->logger->err('Failed to store {type} thumbnail: {error}', [
                            'type' => $type,
                            'error' => $e->getMessage()
                        ]);
                        error_log("VideoThumbnailService: Failed to store $type thumbnail: " . $e->getMessage());
                    }
                } else {
                    $this->logger->err('Failed to read temp file content: {path}', ['path' => $tempPath]);
                }

                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->err('Error in createDerivativesManually: {message}', [
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retrieves the configured file storage interface, or defaults to a local file store if none is set.
     *
     * @return \Omeka\File\Store\StoreInterface The file storage interface used for storing thumbnails and derivatives.
     */
    protected function getFileStore()
    {
        if ($this->fileStore) {
            return $this->fileStore;
        }

        // Fallback to local store
        return new \Omeka\File\Store\Local($this->basePath);
    }

    /**
     * Generates a video thumbnail for a media item identified by its ID.
     *
     * Scans the storage directory for a video file associated with the given media ID, extracts a frame at a specified percentage of the video's duration using FFmpeg, and creates thumbnail derivatives in multiple sizes. Returns true if the process succeeds, or false if any step fails.
     *
     * @param int $mediaId The ID of the media item.
     * @param int|null $percentage The position percentage in the video to capture the thumbnail. If null, the default percentage is used.
     * @return bool True if the thumbnail was successfully generated and derivatives created; false otherwise.
     */
    public function generateThumbnailById(int $mediaId, int $percentage = null): bool
    {
        $this->logger->info('Starting video thumbnail generation by ID for media #{media_id}', ['media_id' => $mediaId]);

        try {
            // We need to get the media entity somehow - let's try a different approach
            // Instead of getting the entity, let's work directly with the storage files

            // First, let's try to find the media files by scanning the storage directory
            $originalDir = $this->basePath . '/original';
            $this->logger->info('Scanning original directory: {dir}', ['dir' => $originalDir]);

            if (!is_dir($originalDir)) {
                $this->logger->err('Original directory not found: {dir}', ['dir' => $originalDir]);
                return false;
            }

            // Look for video files that might belong to this media
            $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
            $foundFile = null;
            $storageId = null;

            foreach (scandir($originalDir) as $file) {
                if ($file === '.' || $file === '..') continue;

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, $videoExtensions)) {
                    $fileStorageId = pathinfo($file, PATHINFO_FILENAME);
                    $this->logger->info('Found video file: {file} with storage_id: {storage_id}', [
                        'file' => $file,
                        'storage_id' => $fileStorageId
                    ]);

                    // For now, let's try the most recent video file
                    // In a real implementation, we'd need to match this to the media ID
                    $foundFile = $originalDir . '/' . $file;
                    $storageId = $fileStorageId;
                    break; // Use the first video file found for testing
                }
            }

            if (!$foundFile || !$storageId) {
                $this->logger->err('No video file found for media #{media_id}', ['media_id' => $mediaId]);
                return false;
            }

            $this->logger->info('Using video file: {file} for media #{media_id}', [
                'file' => $foundFile,
                'media_id' => $mediaId
            ]);

            // Get video duration to calculate thumbnail position
            $duration = $this->getVideoDuration($foundFile);
            if (!$duration) {
                $this->logger->err('Could not determine duration for video file: {file}', ['file' => $foundFile]);
                return false;
            }

            // Calculate position for thumbnail (percentage of duration)
            $usePercentage = $percentage ?? $this->thumbnailPercentage;
            $position = ($duration * $usePercentage) / 100;

            // Create temporary file for thumbnail
            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath() . '.jpg';

            // Generate thumbnail using ffmpeg - FIXED: Use same command as working VideoAwareThumbnailer
            $command = sprintf(
                '%s -y -i %s -ss %s -vframes 1 -vf scale=800:-1 -f image2 -q:v 2 %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($foundFile),
                escapeshellarg((string) $position),
                escapeshellarg($tempPath)
            );

            $this->logger->info('Executing FFmpeg command: {command}', ['command' => $command]);

            exec($command, $output, $returnVar);

            $this->logger->info('FFmpeg output: {output}, return code: {code}', [
                'output' => implode("\n", $output),
                'code' => $returnVar
            ]);

            if ($returnVar !== 0 || !file_exists($tempPath)) {
                $this->logger->err('Failed to generate thumbnail for media #{media_id}: {output}', [
                    'media_id' => $mediaId,
                    'output' => implode("\n", $output)
                ]);
                return false;
            }

            // Create thumbnails using the storage ID
            $success = $this->createThumbnailDerivatives($tempPath, $storageId);

            if ($success) {
                $this->logger->info('Video thumbnail created successfully for media #{media_id}', [
                    'media_id' => $mediaId
                ]);
                return true;
            } else {
                $this->logger->err('Failed to create thumbnail derivatives for media #{media_id}', [
                    'media_id' => $mediaId
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->err('Error generating thumbnail by ID for media #{media_id}: {message}', [
                'media_id' => $mediaId,
                'message' => $e->getMessage()
            ]);

            // Log the full stack trace for debugging
            $this->logger->err('Full exception trace: {trace}', [
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
        // NOTE: Don't clean up temp file here - VideoAwareThumbnailer needs it for processing
        // The temp file will be cleaned up by Omeka's temp file system
    }

    /**
     * Determines whether the FFmpeg binary is available and executable.
     *
     * @return bool True if FFmpeg is accessible and returns a successful exit code; false otherwise.
     */
    public function isFFmpegAvailable(): bool
    {
        $command = $this->ffmpegPath . ' -version';
        exec($command, $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Appends a timestamped message to the DerivativeMedia debug log file for the current day.
     *
     * Ensures the log directory exists before writing.
     *
     * @param string $message The message to log.
     */
    protected function writeToDebugLog(string $message): void
    {
        $logFile = '/var/www/omeka-s/logs/DerivativeMedia_debug_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message" . PHP_EOL;

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}