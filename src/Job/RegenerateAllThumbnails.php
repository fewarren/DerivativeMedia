<?php
namespace DerivativeMedia\Job;

use Omeka\Job\AbstractJob;
use Omeka\Api\Representation\MediaRepresentation;

/**
 * Job to regenerate all thumbnails using the UniversalThumbnailer
 */
class RegenerateAllThumbnails extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $entityManager = $services->get('Omeka\EntityManager');
        $thumbnailer = $services->get('Omeka\File\Thumbnailer');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $fileStore = $services->get('Omeka\File\Store');

        $logger->info('Starting universal thumbnail regeneration job');

        // Get all media that have original files
        $query = ['has_original' => true];
        $forceRegenerate = $this->getArg('force_regenerate', false);
        
        if (!$forceRegenerate) {
            // Only process media without thumbnails
            $query['has_thumbnails'] = false;
        }

        $mediaList = $api->search('media', $query)->getContent();
        $total = count($mediaList);

        $logger->info(sprintf('Found %d media files to process (force_regenerate: %s)', $total, $forceRegenerate ? 'true' : 'false'));

        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($mediaList as $media) {
            if ($this->shouldStop()) {
                $logger->info('Job stopped by user');
                break;
            }

            $processed++;
            $mediaId = $media->id();
            $mediaType = $media->mediaType();
            $filename = $media->filename();

            $logger->info(sprintf('[%d/%d] Processing media ID %d: %s (%s)', 
                $processed, $total, $mediaId, $filename, $mediaType));

            try {
                // Get the original file path
                $originalPath = $fileStore->getLocalPath('original', $filename);
                
                if (!file_exists($originalPath)) {
                    $logger->err(sprintf('Original file not found: %s', $originalPath));
                    $failed++;
                    continue;
                }

                // Create temp file for thumbnailer
                $tempFile = $tempFileFactory->build();
                $tempFile->setTempPath($originalPath);
                $tempFile->setSourceName($media->source());
                $tempFile->setMediaType($mediaType);
                $tempFile->setStorageId($media->storageId());

                // Set up thumbnailer
                $thumbnailer->setSource($tempFile);

                if (!$thumbnailer->canThumbnail()) {
                    $logger->info(sprintf('Thumbnailer cannot handle media type: %s', $mediaType));
                    continue;
                }

                // Generate thumbnails for all configured types
                $config = $services->get('Config');
                $thumbnailTypes = $config['thumbnails']['types'] ?? [
                    'large' => ['constraint' => 800],
                    'medium' => ['constraint' => 200], 
                    'square' => ['constraint' => 200, 'strategy' => 'square']
                ];

                $thumbnailsCreated = 0;
                foreach ($thumbnailTypes as $type => $typeConfig) {
                    try {
                        $strategy = $typeConfig['strategy'] ?? 'default';
                        $constraint = $typeConfig['constraint'] ?? 200;

                        $logger->info(sprintf('Creating %s thumbnail (%s, %d)', $type, $strategy, $constraint));

                        $thumbnailPath = $thumbnailer->create($strategy, $constraint);
                        
                        if ($thumbnailPath && file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
                            // Store the thumbnail
                            $storePath = $fileStore->put($thumbnailPath, $type . '/' . $filename);
                            $thumbnailsCreated++;
                            
                            $logger->info(sprintf('Created %s thumbnail: %s (%d bytes)', 
                                $type, $storePath, filesize($thumbnailPath)));
                        } else {
                            $logger->err(sprintf('Failed to create %s thumbnail', $type));
                        }
                    } catch (\Exception $e) {
                        $logger->err(sprintf('Error creating %s thumbnail: %s', $type, $e->getMessage()));
                    }
                }

                if ($thumbnailsCreated > 0) {
                    // Update database to reflect that thumbnails exist
                    $mediaEntity = $entityManager->find('Omeka\Entity\Media', $mediaId);
                    if ($mediaEntity) {
                        $mediaEntity->setHasThumbnails(true);
                        $entityManager->flush();
                        
                        $logger->info(sprintf('Updated database: media %d now has thumbnails', $mediaId));
                        $successful++;
                    }
                } else {
                    $logger->err(sprintf('No thumbnails created for media %d', $mediaId));
                    $failed++;
                }

            } catch (\Exception $e) {
                $logger->err(sprintf('Error processing media %d: %s', $mediaId, $e->getMessage()));
                $failed++;
            }

            // Periodic progress update
            if ($processed % 10 === 0) {
                $logger->info(sprintf('Progress: %d/%d processed, %d successful, %d failed', 
                    $processed, $total, $successful, $failed));
            }
        }

        $logger->info(sprintf('Universal thumbnail regeneration completed: %d processed, %d successful, %d failed', 
            $processed, $successful, $failed));
    }
}
