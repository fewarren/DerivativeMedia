<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;

class EventListener
{
    /**
     * @var \Interop\Container\ContainerInterface
     */
    protected $services;

    /**
     * @var array Registry of pending video ingest temp files
     */
    protected static $pendingVideoIngests = [];

    public function __construct($services)
    {
        $this->services = $services;
    }

    /**
     * Get DebugManager instance with fallback
     *
     * @return DebugManager
     */
    protected function getDebugManager(): DebugManager
    {
        try {
            return $this->services->get('DerivativeMedia\Service\DebugManager');
        } catch (\Exception $e) {
            // Fallback: create DebugManager directly if service not available
            return new DebugManager();
        }
    }

    /**
     * Get TempFileFactory instance for secure temporary file creation
     *
     * @return TempFileFactory
     */
    protected function getTempFileFactory(): TempFileFactory
    {
        return $this->services->get('Omeka\File\TempFileFactory');
    }

    /**
     * Attach event listeners
     */
    public function attach(SharedEventManagerInterface $sharedEventManager)
    {
        // Get DebugManager for proper logging
        $debugManager = $this->getDebugManager();
        $operationId = 'event-attach-' . uniqid();

        $debugManager->logInfo('Attaching event listeners - COMPREHENSIVE DETECTION', DebugManager::COMPONENT_SERVICE, $operationId);

        // Attach to ALL possible media-related events
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.create.pre',
            [$this, 'onAnyMediaEvent'],
            1000
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.create.post',
            [$this, 'onAnyMediaEvent'],
            1000
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.pre',
            [$this, 'onAnyMediaEvent'],
            1000
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.post',
            [$this, 'onAnyMediaEvent'],
            1000
        );

        // Also listen for job events
        $sharedEventManager->attach(
            '*',
            'job.status.change',
            [$this, 'onJobEvent'],
            1000
        );

        // Listen for ingest events
        $sharedEventManager->attach(
            '*',
            'media.ingest_file.post',
            [$this, 'onIngestEvent'],
            1000
        );

        $debugManager->logInfo('ALL event listeners attached successfully', DebugManager::COMPONENT_SERVICE, $operationId);
    }

    /**
     * Handle ANY media-related events - comprehensive detection
     */
    public function onAnyMediaEvent(Event $event)
    {
        $eventName = $event->getName();
        $debugManager = $this->getDebugManager();
        $operationId = 'media-event-' . uniqid();

        $debugManager->logInfo("*** MEDIA EVENT DETECTED: $eventName ***", DebugManager::COMPONENT_SERVICE, $operationId);

        try {
            $logger = $this->services->get('Omeka\Logger');
            $settings = $this->services->get('Omeka\Settings');

            $logger->info("DerivativeMedia EventListener: Media event triggered: $eventName");

            $request = $event->getParam('request');
            $response = $event->getParam('response');

            if ($request) {
                $resource = $request->getResource();
                $debugManager->logInfo("Event resource: $resource", DebugManager::COMPONENT_SERVICE, $operationId);
            }

            if ($response) {
                $content = $response->getContent();
                if ($content && method_exists($content, 'getMediaType')) {
                    $mediaId = method_exists($content, 'getId') ? $content->getId() : 'UNKNOWN';
                    $mediaType = $content->getMediaType();

                    $debugManager->logInfo("Media found - ID: $mediaId, Type: $mediaType", DebugManager::COMPONENT_SERVICE, $operationId);
                    $logger->info("DerivativeMedia EventListener: Processing media #{$mediaId} - Type: {$mediaType}");

                    // Check if this is a video media
                    if ($mediaType && strpos($mediaType, 'video/') === 0) {
                        $debugManager->logInfo("*** VIDEO DETECTED in $eventName! Media #$mediaId ***", DebugManager::COMPONENT_SERVICE, $operationId);
                        $logger->info("DerivativeMedia EventListener: Video media detected! Generating thumbnail for media #{$mediaId}");

                        // Check if video thumbnail generation is enabled
                        $thumbnailEnabled = $settings->get('derivativemedia_video_thumbnail_enabled', true);
                        if (!$thumbnailEnabled) {
                            $logger->info('DerivativeMedia EventListener: Video thumbnail generation disabled');
                            return;
                        }

                        // Generate video thumbnail
                        $this->generateVideoThumbnail($content);
                    } else {
                        $debugManager->logInfo("Non-video media ($mediaType) in $eventName", DebugManager::COMPONENT_SERVICE, $operationId);
                    }
                } else {
                    $debugManager->logInfo("No media content in $eventName response", DebugManager::COMPONENT_SERVICE, $operationId);
                }
            } else {
                $debugManager->logInfo("No response in $eventName event", DebugManager::COMPONENT_SERVICE, $operationId);
            }

            // Also check for any stored video ingest information
            $this->processStoredVideoIngests();

        } catch (\Exception $e) {
            $debugManager->logError("Exception in $eventName: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $operationId);
            if (isset($logger)) {
                $logger->err("DerivativeMedia EventListener: Exception in $eventName: " . $e->getMessage());
                $logger->err('DerivativeMedia EventListener: Stack trace: ' . $e->getTraceAsString());
            }
        }
    }

    /**
     * Handle job events
     */
    public function onJobEvent(Event $event)
    {
        $debugManager = $this->getDebugManager();
        $operationId = 'job-event-' . uniqid();

        $eventName = $event->getName();
        $job = $event->getTarget();

        if ($job && method_exists($job, 'getJobClass')) {
            $jobClass = $job->getJobClass();
            $debugManager->logInfo("JOB EVENT: $eventName, Class: $jobClass", DebugManager::COMPONENT_SERVICE, $operationId);
        } else {
            $debugManager->logInfo("JOB EVENT: $eventName", DebugManager::COMPONENT_SERVICE, $operationId);
        }
    }

    /**
     * Handle ingest events
     */
    public function onIngestEvent(Event $event)
    {
        $debugManager = $this->getDebugManager();
        $operationId = 'ingest-event-' . uniqid();

        $eventName = $event->getName();
        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
        $debugManager->logInfo("INGEST EVENT: $eventName", DebugManager::COMPONENT_SERVICE, $operationId);

        $params = $event->getParams();
        $debugManager->logInfo("Ingest params: " . print_r(array_keys($params), true), DebugManager::COMPONENT_SERVICE, $operationId);

        // Try to extract information about the uploaded file
        try {
            $tempFile = $event->getParam('tempFile');
            $request = $event->getParam('request');

            if ($tempFile && method_exists($tempFile, 'getTempPath')) {
                $tempPath = $tempFile->getTempPath();
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $debugManager->logInfo("Temp file path: $tempPath", DebugManager::COMPONENT_SERVICE, $operationId);

                // Try to detect file type
                if (file_exists($tempPath)) {
                    $mimeType = mime_content_type($tempPath);
                    $fileSize = filesize($tempPath);
                    $debugManager->logInfo("File detected - MIME: $mimeType, Size: $fileSize bytes", DebugManager::COMPONENT_SERVICE, $operationId);

                    if ($mimeType && strpos($mimeType, 'video/') === 0) {
                        $debugManager->logInfo("*** VIDEO FILE DETECTED in ingest! MIME: $mimeType ***", DebugManager::COMPONENT_SERVICE, $operationId);
                        $debugManager->logInfo("Video file path: $tempPath", DebugManager::COMPONENT_SERVICE, $operationId);

                        // Store information for later processing
                        $this->storeVideoIngestInfo($tempPath, $mimeType, $request);
                    }
                }
            }

            if ($request && method_exists($request, 'getContent')) {
                $content = $request->getContent();
                if (is_array($content)) {
                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    $debugManager->logInfo("Request content keys: " . print_r(array_keys($content), true), DebugManager::COMPONENT_SERVICE, $operationId);
                }
            }

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager->logError("Exception in ingest event: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $operationId);
        }
    }

    /**
     * Store video ingest information for later processing
     */
    protected function storeVideoIngestInfo($tempPath, $mimeType, $request)
    {
        try {
            // Create a temporary file to store video ingest information
            $ingestInfo = [
                'temp_path' => $tempPath,
                'mime_type' => $mimeType,
                'timestamp' => time(),
                'request_id' => uniqid('video_ingest_', true)
            ];

            // SECURITY FIX: Use TempFileFactory for secure temporary file creation
            $tempFileFactory = $this->getTempFileFactory();
            $tempFile = $tempFileFactory->build();
            $infoFile = $tempFile->getTempPath();

            // Write ingest info to secure temporary file
            file_put_contents($infoFile, json_encode($ingestInfo));

            // SECURITY FIX: Register the temp file in our secure registry
            self::$pendingVideoIngests[$ingestInfo['request_id']] = [
                'temp_file' => $tempFile,
                'file_path' => $infoFile,
                'ingest_info' => $ingestInfo,
                'created_at' => time()
            ];

            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager = $this->getDebugManager();
            $debugManager->logInfo("Stored video ingest info: $infoFile", DebugManager::COMPONENT_SERVICE, 'store-ingest-' . uniqid());

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager = $this->getDebugManager();
            $debugManager->logError("Failed to store video ingest info: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, 'store-ingest-' . uniqid());
        }
    }

    /**
     * Process any stored video ingest information
     * SECURITY FIX: Use secure registry instead of glob on /tmp
     */
    protected function processStoredVideoIngests()
    {
        $debugManager = $this->getDebugManager();
        $operationId = 'process-ingests-' . uniqid();

        try {
            if (empty(self::$pendingVideoIngests)) {
                return;
            }

            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager->logInfo("Found " . count(self::$pendingVideoIngests) . " stored video ingests to process", DebugManager::COMPONENT_SERVICE, $operationId);

            $currentTime = time();
            $processedIds = [];

            foreach (self::$pendingVideoIngests as $requestId => $registryEntry) {
                $ingestInfo = $registryEntry['ingest_info'];
                $age = $currentTime - $registryEntry['created_at'];

                // Process files that are at least 5 seconds old (allow time for media creation)
                if ($age >= 5) {
                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    $debugManager->logInfo("Processing stored video ingest: " . $requestId, DebugManager::COMPONENT_SERVICE, $operationId);

                    // Try to find the media entity that was created from this ingest
                    $this->findAndProcessVideoMedia($ingestInfo);

                    // Mark for removal from registry
                    $processedIds[] = $requestId;
                } else {
                    $debugManager->logInfo("Video ingest too recent, waiting: " . $requestId, DebugManager::COMPONENT_SERVICE, $operationId);
                }

                // Clean up old entries (older than 5 minutes)
                if ($age > 300) {
                    $debugManager->logInfo("Cleaning up old video ingest entry: " . $requestId, DebugManager::COMPONENT_SERVICE, $operationId);
                    $processedIds[] = $requestId;
                }
            }

            // SECURITY FIX: Clean up processed entries from registry
            foreach ($processedIds as $requestId) {
                if (isset(self::$pendingVideoIngests[$requestId])) {
                    // TempFile will be automatically cleaned up when object is destroyed
                    unset(self::$pendingVideoIngests[$requestId]);
                }
            }

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager->logError("Exception processing stored video ingests: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $operationId);
        }
    }

    /**
     * Find and process video media that was created from an ingest
     */
    protected function findAndProcessVideoMedia($ingestInfo)
    {
        $debugManager = $this->getDebugManager();
        $operationId = 'find-media-' . uniqid();

        try {
            $api = $this->services->get('Omeka\ApiManager');

            // Search for recent video media
            $response = $api->search('media', [
                'media_type' => $ingestInfo['mime_type'],
                'sort_by' => 'id',
                'sort_order' => 'desc',
                'limit' => 10
            ]);

            $mediaItems = $response->getContent();
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager->logInfo("Found " . count($mediaItems) . " media items with type " . $ingestInfo['mime_type'], DebugManager::COMPONENT_SERVICE, $operationId);

            foreach ($mediaItems as $media) {
                $mediaId = $media->id();
                $mediaType = $media->mediaType();

                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $debugManager->logInfo("Checking media #$mediaId - Type: $mediaType", DebugManager::COMPONENT_SERVICE, $operationId);

                if ($mediaType && strpos($mediaType, 'video/') === 0) {
                    $debugManager->logInfo("*** FOUND VIDEO MEDIA #$mediaId from ingest! ***", DebugManager::COMPONENT_SERVICE, $operationId);

                    // Get the actual media entity
                    $mediaEntity = $api->read('media', $mediaId)->getContent();

                    // Generate video thumbnail
                    $this->generateVideoThumbnail($mediaEntity);

                    return true; // Found and processed
                }
            }

            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager->logWarning("No matching video media found for ingest: " . $ingestInfo['request_id'], DebugManager::COMPONENT_SERVICE, $operationId);
            return false;

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager->logError("Exception finding video media: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $operationId);
            return false;
        }
    }

    /**
     * Generate video thumbnail
     */
    protected function generateVideoThumbnail($media)
    {
        $logger = $this->services->get('Omeka\Logger');
        $settings = $this->services->get('Omeka\Settings');
        
        try {
            // Get the VideoThumbnailService
            $videoThumbnailService = $this->services->get('DerivativeMedia\Service\VideoThumbnailService');
            
            // Get the configured thumbnail percentage
            $percentage = (int) $settings->get('derivativemedia_video_thumbnail_percentage', 25);
            
            $logger->info("DerivativeMedia EventListener: Starting video thumbnail generation for media #{$media->getId()} at {$percentage}% position");
            
            // Generate the thumbnail
            $success = $videoThumbnailService->generateThumbnail($media, $percentage);
            
            if ($success) {
                $logger->info("DerivativeMedia EventListener: Successfully generated video thumbnail for media #{$media->getId()}");
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $debugManager = $this->getDebugManager();
                $debugManager->logInfo("SUCCESS - Video thumbnail generated for media #{$media->getId()}", DebugManager::COMPONENT_SERVICE, 'process-video-' . uniqid());
            } else {
                $logger->err("DerivativeMedia EventListener: Failed to generate video thumbnail for media #{$media->getId()}");
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                $debugManager = $this->getDebugManager();
                $debugManager->logError("FAILED - Video thumbnail generation failed for media #{$media->getId()}", DebugManager::COMPONENT_SERVICE, 'process-video-' . uniqid());
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $logger->err("DerivativeMedia EventListener: Exception during video thumbnail generation for media #{$media->getId()}: " . $e->getMessage());
            $logger->err('DerivativeMedia EventListener: Stack trace: ' . $e->getTraceAsString());
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            $debugManager = $this->getDebugManager();
            $debugManager->logError("EXCEPTION - " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, 'process-video-' . uniqid());
            return false;
        }
    }
}
