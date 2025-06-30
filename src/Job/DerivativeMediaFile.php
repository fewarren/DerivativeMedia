<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Doctrine\Common\Collections\Criteria;
use Omeka\Job\AbstractJob;

class DerivativeMediaFile extends AbstractJob
{
    use DerivativeMediaTrait;

    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 25;

    /**
     * @todo Factorize with DerivativeMetadata.
     *
     * {@inheritDoc}
     * @see \Omeka\Job\JobInterface::perform()
     */
    public function perform(): void
    {
        $result = $this->initialize();
        if (!$result) {
            return;
        }

        /**
         * @var \Omeka\Api\Manager $api
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        // Prepare the list of medias.

        $repository = $this->entityManager->getRepository(\Omeka\Entity\Media::class);
        $criteria = Criteria::create();
        $expr = $criteria->expr();

        // Always true expression to simplify process.
        $criteria->where($expr->gt('id', 0));

        $queryItems = $this->getArg('query_items', []);
        $itemIds = null;
        if ($queryItems) {
            $itemIds = $api->search('items', $queryItems, ['returnScalar' => 'id'])->getContent();
            $itemIds = array_map('intval', $itemIds);
        }

        $itemSets = $this->getArg('item_sets', []);
        if ($itemSets) {
            // TODO Include dql as a subquery.
            $dql = <<<DQL
SELECT item.id
FROM Omeka\Entity\Item item
JOIN item.itemSets item_set
WHERE item_set.id IN (:item_set_ids)
DQL;
            $query = $this->entityManager->createQuery($dql);
            $query->setParameter('item_set_ids', $itemSets, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
            $itemIds2 = array_map('intval', array_column($query->getArrayResult(), 'id'));
            $itemIds = $itemIds ? array_intersect($itemIds, $itemIds2) : $itemIds2;
        }

        if (is_array($itemIds)) {
            if (!count($itemIds)) {
                $this->logger->warn(
                    'The query or the list of item sets output no items.' // @translate
                );
                return;
            }
            $criteria->andWhere($expr->in('item', $itemIds));
        }

        $ingesters = $this->getArg('ingesters', []);
        if ($ingesters && !in_array('', $ingesters)) {
            $criteria->andWhere($expr->in('ingester', $ingesters));
        }

        $renderers = $this->getArg('renderers', []);
        if ($renderers && !in_array('', $renderers)) {
            $criteria->andWhere($expr->in('renderer', $renderers));
        }

        $mediaTypes = $this->getArg('media_types', []);
        if ($mediaTypes && !in_array('', $mediaTypes)) {
            $criteria->andWhere($expr->in('mediaType', $mediaTypes));
        }

        $mediaIds = $this->getArg('media_ids');
        if ($mediaIds) {
            $range = $this->exprRange('id', $mediaIds);
            if ($range) {
                $criteria->andWhere($expr->orX(...$range));
            }
        }

        $totalResources = $api->search('media', ['limit' => 0])->getTotalResults();

        // Check only media with an original file.
        $criteria->andWhere($expr->eq('hasOriginal', 1));

        $criteria->orderBy(['id' => 'ASC']);

        $collection = $repository->matching($criteria);
        $totalToProcess = $collection->count();

        if (empty($totalToProcess)) {
            $this->logger->info(
                'No media to process for creation of derivative medias (on a total of {total} medias). You may check your query.', // @translate
                ['total' => $totalResources]
            );
            return;
        }

        $this->logger->info(
            'Processing creation of derivative medias of {total_process} medias (on a total of {total} medias).', // @translate
            ['total_process' => $totalToProcess, 'total' => $totalResources]
        );

        // Do the process.

        $offset = 0;
        $key = 0;
        $totalProcessed = 0;
        $totalSucceed = 0;
        $totalFailed = 0;
        $count = 0;
        while (++$count <= $totalToProcess) {
            $criteria
                ->setMaxResults(self::SQL_LIMIT)
                ->setFirstResult($offset);
            $medias = $repository->matching($criteria);
            if (!count($medias)) {
                break;
            }

            /** @var \Omeka\Entity\Media $media */
            foreach ($medias as $key => $media) {
                if ($this->shouldStop()) {
                    $this->logger->warn(
                        'The job "Derivative Media" was stopped: {count}/{total} resources processed.', // @translate
                        ['count' => $offset + $key, 'total' => $totalToProcess]
                    );
                    break 2;
                }

                $this->logger->info(
                    'Media #{media_id} ({count}/{total}): creating derivative files.', // @translate
                    ['media_id' => $media->getId(), 'count' => $offset + $key + 1, 'total' => $totalToProcess]
                );

                if ($this->isManaged($media)) {
                    $result = $this->derivateMedia($media);
                    ++$totalProcessed;
                    $result
                        ? ++$totalSucceed
                        : ++$totalFailed;
                } else {
                    $this->logger->info(
                        'Media #{media_id} ({count}/{total}): The media is not an audio, a video or a pdf file.', // @translate
                        ['media_id' => $media->getId(), 'count' => $offset + $key + 1, 'total' => $totalToProcess]
                    );
                }

                // Avoid memory issue.
                unset($media);
            }

            // Avoid memory issue.
            unset($medias);
            $this->entityManager->clear();

            $offset += self::SQL_LIMIT;
        }

        $this->logger->info(
            'End of the creation of derivative files: {count}/{total} processed, {skipped} skipped, {succeed} succeed, {failed} failed.', // @translate
            ['count' => $totalProcessed, 'total' => $totalToProcess, 'skipped' => $totalToProcess - $totalProcessed, 'succeed' => $totalSucceed, 'failed' => $totalFailed]
        );
    }

    /**
     * Create a doctrine expression for a range.
     *
     * @param string $column
     * @param array|string $ids
     * @return \Doctrine\Common\Collections\Expr\CompositeExpression|null
     */
    protected function exprRange($column, $ids)
    {
        $ranges = $this->rangeToArray($ids);
        if (empty($ranges)) {
            return [];
        }

        $conditions = [];

        $expr = Criteria::create()->expr();
        foreach ($ranges as $range) {
            if (strpos($range, '-')) {
                $from = strtok($range, '-');
                $to = strtok('-');
                if ($from && $to) {
                    $conditions[] = $expr->andX($expr->gte($column, $from), $expr->lte($column, $to));
                } elseif ($from) {
                    $conditions[] = $expr->gte($column, $from);
                } else {
                    $conditions[] = $expr->lte($column, $to);
                }
            } else {
                $conditions[] = $expr->eq($column, $range);
            }
        }

        return $conditions;
    }

    /**
     * Clean a list of ranges of ids.
     *
     * @param string|array $ids
     * @return array
     */
    protected function rangeToArray($ids)
    {
        $clean = function ($str) {
            $str = preg_replace('/[^0-9-]/', ' ', $str);
            $str = preg_replace('/\s*-+\s*/', '-', $str);
            $str = preg_replace('/-+/', '-', $str);
            $str = preg_replace('/\s+/', ' ', $str);
            return trim($str);
        };

        $ids = is_array($ids)
            ? array_map($clean, $ids)
            : explode(' ', $clean($ids));

        // Skip empty ranges and ranges with multiple "-".
        return array_values(array_filter($ids, function ($v) {
            return !empty($v) && substr_count($v, '-') <= 1;
        }));
    }
}
