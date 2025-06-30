<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use DerivativeMedia\Service\DebugManager;
use DerivativeMedia\Service\VideoThumbnailService;
use Omeka\Job\AbstractJob;

class GenerateVideoThumbnails extends AbstractJob
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var DebugManager
     */
    protected $debugManager;

    /**
     * @var VideoThumbnailService
     */
    protected $videoThumbnailService;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $this->debugManager = $services->get('DerivativeMedia\Service\DebugManager');
        $this->videoThumbnailService = $services->get('DerivativeMedia\Service\VideoThumbnailService');
        $api = $services->get('Omeka\ApiManager');
        $entityManager = $services->get('Omeka\EntityManager');

        $opId = 'bulk_video_thumbnails_' . uniqid();
        $this->debugManager->logInfo('Starting bulk video thumbnail generation job', DebugManager::COMPONENT_SERVICE, $opId);

        // Get job arguments
        $query = $this->getArg('query', []);
        $forceRegenerate = $this->getArg('force_regenerate', false);
        $percentage = $this->getArg('percentage', null);
        $mediaId = $this->getArg('media_id', null); // Support for single media processing

        $this->debugManager->logInfo(sprintf('Job args - Query: %s, Force: %s, Percentage: %s, Media ID: %s',
            json_encode($query), $forceRegenerate ? 'true' : 'false', $percentage ?? 'default', $mediaId ?? 'bulk'),
            DebugManager::COMPONENT_SERVICE, $opId);

        // Handle single media processing
        if ($mediaId) {
            $this->processSingleMedia($mediaId, $forceRegenerate, $percentage, $opId);
            return;
        }

        // Search for video media
        $videoMimeTypes = [
            'video/mp4',
            'video/avi', 
            'video/mov',
            'video/wmv',
            'video/mkv',
            'video/webm',
            'video/quicktime',
            'video/x-msvideo'
        ];

        $totalProcessed = 0;
        $totalSuccess = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($videoMimeTypes as $mimeType) {
            try {
                $this->debugManager->logInfo("Processing MIME type: $mimeType", DebugManager::COMPONENT_SERVICE, $opId);
                
                $searchQuery = array_merge($query, ['media_type' => $mimeType]);
                $response = $api->search('media', $searchQuery);
                $mediaItems = $response->getContent();

                $this->debugManager->logInfo(sprintf('Found %d media items for MIME type %s', count($mediaItems), $mimeType), DebugManager::COMPONENT_SERVICE, $opId);

                foreach ($mediaItems as $mediaRepresentation) {
                    if ($this->shouldStop()) {
                        $this->debugManager->logInfo('Job stopped by user request', DebugManager::COMPONENT_SERVICE, $opId);
                        return;
                    }

                    $totalProcessed++;
                    
                    try {
                        // Get the media entity
                        $mediaEntity = $entityManager->find('Omeka\Entity\Media', $mediaRepresentation->id());
                        if (!$mediaEntity) {
                            $this->debugManager->logError("Could not find media entity for ID: " . $mediaRepresentation->id(), DebugManager::COMPONENT_SERVICE, $opId);
                            $totalFailed++;
                            continue;
                        }

                        $this->debugManager->logInfo("Processing media #{$mediaEntity->getId()}: {$mediaEntity->getFilename()}", DebugManager::COMPONENT_SERVICE, $opId);

                        // Check if thumbnails already exist and we're not forcing regeneration
                        if (!$forceRegenerate && $mediaRepresentation->hasThumbnails()) {
                            $this->debugManager->logInfo("Media #{$mediaEntity->getId()} already has thumbnails, skipping", DebugManager::COMPONENT_SERVICE, $opId);
                            $totalSkipped++;
                            continue;
                        }

                        // Generate thumbnail
                        $success = $this->videoThumbnailService->generateThumbnail($mediaEntity, $percentage);

                        if ($success) {
                            $this->debugManager->logInfo("Successfully generated thumbnail for media #{$mediaEntity->getId()}", DebugManager::COMPONENT_SERVICE, $opId);
                            $totalSuccess++;
                        } else {
                            $this->debugManager->logError("Failed to generate thumbnail for media #{$mediaEntity->getId()}", DebugManager::COMPONENT_SERVICE, $opId);
                            $totalFailed++;
                        }

                    } catch (\Exception $e) {
                        $this->debugManager->logError("Exception processing media #{$mediaRepresentation->id()}: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $opId);
                        $totalFailed++;
                    }

                    // Clear entity manager periodically to avoid memory issues
                    if ($totalProcessed % 10 === 0) {
                        $entityManager->clear();
                    }
                }

            } catch (\Exception $e) {
                $this->debugManager->logError("Exception processing MIME type $mimeType: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $opId);
            }
        }

        // Log final results
        $this->logger->info(sprintf(
            'Video thumbnail generation completed. Processed: %d, Success: %d, Failed: %d, Skipped: %d',
            $totalProcessed, $totalSuccess, $totalFailed, $totalSkipped
        ));

        $this->debugManager->logInfo(sprintf(
            'Bulk video thumbnail generation completed - Processed: %d, Success: %d, Failed: %d, Skipped: %d',
            $totalProcessed, $totalSuccess, $totalFailed, $totalSkipped
        ), DebugManager::COMPONENT_SERVICE, $opId);
    }

    /**
     * Process a single media item for video thumbnail generation
     */
    protected function processSingleMedia(int $mediaId, bool $forceRegenerate, ?int $percentage, string $opId): void
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');

        try {
            $this->debugManager->logInfo("Processing single media #$mediaId", DebugManager::COMPONENT_SERVICE, $opId);

            // Get the media entity
            $mediaEntity = $entityManager->find('Omeka\Entity\Media', $mediaId);
            if (!$mediaEntity) {
                $this->debugManager->logError("Could not find media entity for ID: $mediaId", DebugManager::COMPONENT_SERVICE, $opId);
                return;
            }

            // Verify it's a video file
            $mediaType = $mediaEntity->getMediaType();
            if (!$mediaType || strpos($mediaType, 'video/') !== 0) {
                $this->debugManager->logInfo("Media #$mediaId is not a video file (type: $mediaType), skipping", DebugManager::COMPONENT_SERVICE, $opId);
                return;
            }

            $this->debugManager->logInfo("Generating video thumbnail for media #$mediaId: {$mediaEntity->getFilename()}", DebugManager::COMPONENT_SERVICE, $opId);

            // Generate thumbnail (force regeneration for single media to override defaults)
            $success = $this->videoThumbnailService->generateThumbnail($mediaEntity, $percentage);

            if ($success) {
                $this->debugManager->logInfo("Successfully generated thumbnail for media #$mediaId", DebugManager::COMPONENT_SERVICE, $opId);
                $this->logger->info("DerivativeMedia: Video thumbnail generated successfully for media #$mediaId");
            } else {
                $this->debugManager->logError("Failed to generate thumbnail for media #$mediaId", DebugManager::COMPONENT_SERVICE, $opId);
                $this->logger->err("DerivativeMedia: Video thumbnail generation failed for media #$mediaId");
            }

        } catch (\Exception $e) {
            $this->debugManager->logError("Exception processing media #$mediaId: " . $e->getMessage(), DebugManager::COMPONENT_SERVICE, $opId);
            $this->logger->err("DerivativeMedia: Exception generating video thumbnail for media #$mediaId: " . $e->getMessage());
        }
    }
}
