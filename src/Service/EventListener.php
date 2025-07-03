<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Entity\Media;

class EventListener
{
    /**
     * @var \Interop\Container\ContainerInterface
     */
    protected $services;

    /**
     * Initializes the EventListener with the provided service container.
     *
     * @param \Interop\Container\ContainerInterface $services The service container for dependency retrieval.
     */
    public function __construct($services)
    {
        $this->services = $services;
    }

    /**
     * Retrieves a DebugManager instance from the service container, or creates a new one if unavailable.
     *
     * @return DebugManager The DebugManager instance for logging and debugging.
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
     * Attaches event listeners for media API events, job status changes, and media ingest events.
     *
     * Registers handlers for media creation and update events, job status changes, and media ingest completion to enable comprehensive detection and processing of media-related actions.
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
     * Handles any media-related event, detecting video media and triggering thumbnail generation if enabled.
     *
     * Logs event details and inspects the response for media content. If a video media is detected and thumbnail generation is enabled in settings, initiates thumbnail creation. Also processes any previously stored video ingest information for deferred thumbnail generation.
     *
     * @param Event $event The triggered media-related event.
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
     * Handles job-related events by logging the event name and, if available, the job class.
     *
     * @param Event $event The job event to handle.
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
     * Handles media ingest events by detecting uploaded video files and storing their ingest information for deferred processing.
     *
     * If a video file is detected during the ingest event, its temporary file path, MIME type, and request data are recorded for later thumbnail generation.
     *
     * @param Event $event The ingest event containing file and request information.
     */
    public function onIngestEvent(Event $event)
    {
        $eventName = $event->getName();
        error_log("DerivativeMedia EventListener: INGEST EVENT: $eventName");

        $params = $event->getParams();
        error_log("DerivativeMedia EventListener: Ingest params: " . print_r(array_keys($params), true));

        // Try to extract information about the uploaded file
        try {
            $tempFile = $event->getParam('tempFile');
            $request = $event->getParam('request');

            if ($tempFile && method_exists($tempFile, 'getTempPath')) {
                $tempPath = $tempFile->getTempPath();
                error_log("DerivativeMedia EventListener: Temp file path: $tempPath");

                // Try to detect file type
                if (file_exists($tempPath)) {
                    $mimeType = mime_content_type($tempPath);
                    $fileSize = filesize($tempPath);
                    error_log("DerivativeMedia EventListener: File detected - MIME: $mimeType, Size: $fileSize bytes");

                    if ($mimeType && strpos($mimeType, 'video/') === 0) {
                        error_log("DerivativeMedia EventListener: *** VIDEO FILE DETECTED in ingest! MIME: $mimeType ***");
                        error_log("DerivativeMedia EventListener: Video file path: $tempPath");

                        // Store information for later processing
                        $this->storeVideoIngestInfo($tempPath, $mimeType, $request);
                    }
                }
            }

            if ($request && method_exists($request, 'getContent')) {
                $content = $request->getContent();
                if (is_array($content)) {
                    error_log("DerivativeMedia EventListener: Request content keys: " . print_r(array_keys($content), true));
                }
            }

        } catch (\Exception $e) {
            error_log("DerivativeMedia EventListener: Exception in ingest event: " . $e->getMessage());
        }
    }

    /**
     * Stores information about a video ingest operation in a temporary JSON file for deferred processing.
     *
     * The stored information includes the temporary file path, MIME type, timestamp, and a unique request ID.
     *
     * @param string $tempPath The path to the temporary video file.
     * @param string $mimeType The MIME type of the video file.
     * @param mixed $request The request object or data associated with the ingest operation.
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

            $infoFile = '/tmp/omeka_video_ingest_' . $ingestInfo['request_id'] . '.json';
            file_put_contents($infoFile, json_encode($ingestInfo));

            error_log("DerivativeMedia EventListener: Stored video ingest info: $infoFile");

        } catch (\Exception $e) {
            error_log("DerivativeMedia EventListener: Failed to store video ingest info: " . $e->getMessage());
        }
    }

    /**
     * Processes stored video ingest information files to trigger video media handling.
     *
     * Scans the system's temporary directory for JSON files containing video ingest information.
     * For each file, if it is at least 5 seconds old, attempts to find and process the corresponding video media, then deletes the file.
     * Also removes files older than 5 minutes as cleanup.
     * Logs progress and exceptions using error_log.
     */
    protected function processStoredVideoIngests()
    {
        try {
            $ingestFiles = glob('/tmp/omeka_video_ingest_*.json');

            if (empty($ingestFiles)) {
                return;
            }

            error_log("DerivativeMedia EventListener: Found " . count($ingestFiles) . " stored video ingests to process");

            foreach ($ingestFiles as $file) {
                $info = json_decode(file_get_contents($file), true);
                if (!$info) continue;

                $age = time() - $info['timestamp'];

                // Process files that are at least 5 seconds old (allow time for media creation)
                if ($age >= 5) {
                    error_log("DerivativeMedia EventListener: Processing stored video ingest: " . $info['request_id']);

                    // Try to find the media entity that was created from this ingest
                    $this->findAndProcessVideoMedia($info);

                    // Remove the processed file
                    unlink($file);
                } else {
                    error_log("DerivativeMedia EventListener: Video ingest too recent, waiting: " . $info['request_id']);
                }

                // Clean up old files (older than 5 minutes)
                if ($age > 300) {
                    error_log("DerivativeMedia EventListener: Cleaning up old video ingest file: " . $info['request_id']);
                    unlink($file);
                }
            }

        } catch (\Exception $e) {
            error_log("DerivativeMedia EventListener: Exception processing stored video ingests: " . $e->getMessage());
        }
    }

    /**
     * Searches for recently created video media matching the provided ingest information and generates a thumbnail for the first match found.
     *
     * @param array $ingestInfo Associative array containing ingest details such as MIME type and request ID.
     * @return bool True if a matching video media was found and processed; false otherwise.
     */
    protected function findAndProcessVideoMedia($ingestInfo)
    {
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
            error_log("DerivativeMedia EventListener: Found " . count($mediaItems) . " media items with type " . $ingestInfo['mime_type']);

            foreach ($mediaItems as $media) {
                $mediaId = $media->id();
                $mediaType = $media->mediaType();

                error_log("DerivativeMedia EventListener: Checking media #$mediaId - Type: $mediaType");

                if ($mediaType && strpos($mediaType, 'video/') === 0) {
                    error_log("DerivativeMedia EventListener: *** FOUND VIDEO MEDIA #$mediaId from ingest! ***");

                    // Get the actual media entity
                    $mediaEntity = $api->read('media', $mediaId)->getContent();

                    // Generate video thumbnail
                    $this->generateVideoThumbnail($mediaEntity);

                    return true; // Found and processed
                }
            }

            error_log("DerivativeMedia EventListener: No matching video media found for ingest: " . $ingestInfo['request_id']);
            return false;

        } catch (\Exception $e) {
            error_log("DerivativeMedia EventListener: Exception finding video media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generates a thumbnail image for the given video media at a configured percentage position.
     *
     * Attempts to create a video thumbnail using the VideoThumbnailService. The thumbnail position is determined by a percentage value from settings, defaulting to 25% if not set.
     *
     * @param object $media The media entity representing the video.
     * @return bool True if the thumbnail was generated successfully, false otherwise.
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
                error_log("DerivativeMedia EventListener: SUCCESS - Video thumbnail generated for media #{$media->getId()}");
            } else {
                $logger->err("DerivativeMedia EventListener: Failed to generate video thumbnail for media #{$media->getId()}");
                error_log("DerivativeMedia EventListener: FAILED - Video thumbnail generation failed for media #{$media->getId()}");
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $logger->err("DerivativeMedia EventListener: Exception during video thumbnail generation for media #{$media->getId()}: " . $e->getMessage());
            $logger->err('DerivativeMedia EventListener: Stack trace: ' . $e->getTraceAsString());
            error_log("DerivativeMedia EventListener: EXCEPTION - " . $e->getMessage());
            return false;
        }
    }
}
