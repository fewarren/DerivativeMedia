<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DerivativeMedia extends AbstractJob
{
    use DerivativeMediaTrait;

    public function perform(): void
    {
        $result = $this->initialize();
        if (!$result) {
            return;
        }

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $mediaId = $this->getArg('media_id');
        if (!$mediaId) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No media was set, so no derivative to create.' // @translate
            );
            return;
        }

        try {
            /** @var \Omeka\Entity\Media $media */
            $media = $api->read('media', ['id' => $mediaId], [], ['initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No media #{media_id}: no derivative media to create.', // @translate
                ['media_id' => $mediaId]
            );
            return;
        }

        if (!$this->isManaged($media)) {
            $this->logger->warn(
                'Media #{media_id}: not an audio or video file.', // @translate
                ['media_id' => $mediaId]
            );
            return;
        }

        $this->derivateMedia($media);
    }
}
