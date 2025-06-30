<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DerivativeItem extends AbstractJob
{
    use DerivativeMediaTrait;

    public function perform(): void
    {
        $result = $this->initialize();
        if (!$result) {
            return;
        }

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $itemId = $this->getArg('item_id');
        if (!$itemId) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No item was set, so no derivative media to create.' // @translate
            );
            return;
        }

        try {
            /** @var \Omeka\Entity\Item $item */
            $item = $api->read('items', ['id' => $itemId], [], ['initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No item #{item_id}: no derivative media to create.', // @translate
                ['item_id' => $itemId]
            );
            return;
        }

        foreach ($item->getMedia() as $media) {
            if ($this->isManaged($media)) {
                $this->derivateMedia($media);
            }
        }
    }
}
