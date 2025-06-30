<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Omeka\Job\AbstractJob;

class CreateDerivatives extends AbstractJob
{
    use TraitDerivative;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Settings\Settings $settings
         * @var \DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative $createDerivative
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $this->logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');
        $entityManager = $services->get('Omeka\EntityManager');
        $createDerivative = $plugins->get('createDerivative');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/item/job_' . $this->job->getId());

        $enabled = $settings->get('derivativemedia_enable', []);

        $type = $this->getArg('type');
        $types = is_array($type) ? $type : [$type];
        $types = array_filter($types) ?: $enabled;
        // Recheck types with enabled types.
        $types = array_intersect($types, array_keys(Module::DERIVATIVES), $enabled);
        $types = array_combine($types, $types);
        unset($types['audio'], $types['video'], $types['pdf_media']);

        if (empty($types)) {
            $this->logger->warn(
                'No enabled type of derivative to process.' // @translate
            );
            return;
        }

        $itemId = $this->getArg('item_id');
        if ($itemId) {
            $query = ['id' => $itemId];
        } else {
            $query = $this->getArg('query');
        }

        $ids = $api->search('items', $query, ['returnScalar' => 'id'])->getContent();
        if (!$ids) {
            $this->logger->warn(
                'No items selected.' // @translate
            );
            return;
        }

        // Warning: dataMedia should be provided only when a single item and a
        // type should be processed, because the list of medias is different.
        $dataMedia = $itemId && count($types) === 1 ? $this->getArg('data_media', []) : [];

        foreach (array_values($ids) as $index => $itemId) {
            try {
                $item = $api->read('items', $itemId)->getContent();
            } catch (\Exception $e) {
                continue;
            }
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'The job was stopped.' // @translate
                );
                return;
            }
            $results = [];
            foreach ($types as $type) {
                $filepath = $this->itemFilepath($item, $type);
                $result = $createDerivative($type, $filepath, $item, $dataMedia);
                if ($result) {
                    $results[] = $type;
                }
            }
            // Messages are already logged, except in case of success.
            if ($results) {
                $this->logger->info(
                    'Item #{item_id}: derivative files for types {types} created successfully.', // @translate
                    ['item_id' => $itemId, 'types' => implode(', ', $results)]
                );
            }
            if ((++$index % 100) === 0) {
                $entityManager->clear();
            }
        }
    }
}
