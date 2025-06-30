<?php declare(strict_types=1);

namespace DerivativeMedia;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use DerivativeMedia\Form\ConfigForm;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Entity\Media;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * Derivative Media
 *
 * Create derivative audio/video/pdf media files for cross-browser compatibility.
 *
 * @copyright Daniel Berthereau, 2020-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    /**
     * Info about the process managed by the module.
     *
     * For all resources (media and item):
     * - mode (string): build file as "static", "dynamic", "live" or "dynamic_live".
     * - level (string): "item" or "media".
     * - multiple (bool): can create multiple derivatives specified in config.
     * For resource "item":
     * - mediatype (string): the media type of the destination.
     * - extension (string): the extension of the destination.
     * - size (null|integer): real size or estimation.
     * - build (array): the config to create the destination. Currently not used.
     * - dir (string): the destination dir of the type.
     * - size (bool): check the size to create the file dynamically or not for
     *   modes "live" and "dynamic_live".
     * For media, mode is always static.
     *
     * @var array
     */
    const DERIVATIVES = [
        // Media level.
        'audio' => [
            'mode' => 'static',
            'level' => 'media',
            'multiple' => true,
        ],
        'video' => [
            'mode' => 'static',
            'level' => 'media',
            'multiple' => true,
        ],
        'pdf_media' => [
            'mode' => 'static',
            'level' => 'media',
            'multiple' => true,
        ],

        // Item level.
        'alto' => [
            'mode' => 'live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/alto+xml',
            'extension' => 'alto.xml',
            /*
            'build' => [
                'mediatype' => ['application/alto+xml'],
            ],
            */
            'dir' => 'alto',
            'size' => true,
        ],
        'iiif-2' => [
            'mode' => 'live',
            'level' => 'item',
            'multiple' => false,
            // According to specification for 2.1, the response should be json,
            // except if client asks json-ld.
            'mediatype' => 'application/json',
            'extension' => 'manifest.json',
            /*
            'build' => [
                'mediatype' => ['image', 'audio', 'video'],
            ],
            */
            'dir' => 'iiif/2',
            'size' => false,
        ],
        'iiif-3' => [
            'mode' => 'live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"',
            'extension' => 'manifest.json',
            /*
            'build' => [
                'mediatype' => ['image', 'audio', 'video'],
            ],
            */
            'dir' => 'iiif/3',
            'size' => false,
        ],
        'pdf' => [
            'mode' => 'dynamic',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/pdf',
            'extension' => 'pdf',
            /*
            'build' => [
                'mediatype' => ['image'],
            ],
            */
            'dir' => 'pdf',
            'size' => true,
        ],
        'pdf2xml' => [
            'mode' => 'live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/vnd.pdf2xml+xml',
            'extension' => 'pdf2xml.xml',
            /*
            'build' => [
                'mediatype' => ['application/pdf'],
            ],
            */
            'dir' => 'pdf2xml',
            'size' => false,
        ],
        'txt' => [
            'mode' => 'live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'text/plain',
            'extension' => 'txt',
            /*
            'build' => [
                'mediatype' => ['text/plain'],
            ],
            */
            'dir' => 'txt',
            'size' => true,
        ],
        'text' => [
            'mode' => 'live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'text/plain',
            'extension' => 'txt',
            /*
            'build' => [
                'property' => 'extracttext:extracted_text',
            ],
            */
            'dir' => 'text',
            'size' => true,
        ],
        'zip' => [
            'mode' => 'dynamic_live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/zip',
            'extension' => 'zip',
            /*
            'build' => [
                'mediatype' => [''],
            ],
            */
            'dir' => 'zip',
            'size' => true,
        ],
        'zipm' => [
            'mode' => 'dynamic_live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/zip',
            'extension' => 'zip',
            /*
            'build' => [
                'mediatype' => ['image', 'audio', 'video'],
            ],
            */
            'dir' => 'zipm',
            'size' => true,
        ],
        'zipo' => [
            'mode' => 'dynamic_live',
            'level' => 'item',
            'multiple' => false,
            'mediatype' => 'application/zip',
            'extension' => 'zip',
            /*
            'build' => [
                'mediatype_not' => ['image', 'audio', 'video'],
            ],
            */
            'dir' => 'zipo',
            'size' => true,
        ],
    ];

    protected $dependencies = [
        'Common',
    ];

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(
                null,
                ['DerivativeMedia\Controller\Index']
            );
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');
        $messenger = $plugins->get('messenger');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.62')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.62'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!is_dir($basePath) || !is_readable($basePath) || !is_writeable($basePath)) {
            $message = new PsrMessage(
                'The directory "{path}" is not writeable.', // @translate
                ['path' => $basePath]
            );
            throw new ModuleCannotInstallException((string) $message);
        }

        if (!class_exists('ZipArchive')) {
            $message = new PsrMessage(
                'The extension "php-zip" should be installed on the server to create Zip files.', // @translate
            );
            $messenger->addWarning($message);
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $messenger = $plugins->get('messenger');
        $urlPlugin = $plugins->get('url');
        $message = new PsrMessage(
            'Before compressing files with config tasks, the settings should be set in {link_url}main settings{link_end}.', // @translate
            [
                'link_url' => sprintf('<a href="%s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'setting', 'action' => 'browse'], ['fragment' => 'derivative-media'])),
                'link_end' => '</a>',
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $services = $this->getServiceLocator();
        $t = $services->get('MvcTranslator');
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING:'); // @translate
        $html .= '</strong>';
        $html .= '</p>';

        $html .= '<p>';
        // TODO Remove all files one by one, because the base path of files may have been changed.
        $html .= new PsrMessage(
            'All derivative files will be kept in the folders specified in the config inside {path}.', // @translate
            ['path' => $basePath]
        );
        $html .= '</p>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Note: When an item is saved manually, no event is triggered for media.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'afterSaveItem']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'afterSaveItem']
        );

        // TODO "api.create.post" seems never to occur for media. Remove event?
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.create.post',
            [$this, 'afterSaveMedia']
        );

        $sharedEventManager->attach(
            \Omeka\Entity\Media::class,
            'entity.remove.post',
            [$this, 'afterDeleteMedia'],
            // Before the deletion of the media via the core method.
            10
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'handleViewShowAfterAdmin']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'handleViewShowAfterAdmin']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.details',
            [$this, 'handleViewShowAfterAdmin']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            [$this, 'handleViewShowAfterAdmin']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $urlPlugin = $services->get('ControllerPluginManager')->get('url');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $message = new \Omeka\Stdlib\Message(
            'This page allows to launch background job to prepare static derivative files according to parameters set in %1$smain settings%2$s.', // @translate
            sprintf('<a href="%s">', htmlspecialchars($urlPlugin->fromRoute('admin/default', ['controller' => 'setting'])) . '#derivativemedia_enable'),
            '</a>'
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);

        $this->checkFfmpeg(true);
        $this->checkGhostscript(true);

        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->init();

        return $renderer->formCollection($form);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        if (!$this->handleConfigFormAuto($controller)) {
            return false;
        }

        $params = $controller->getRequest()->getPost();

        // TODO Check why data are empty.
        $params = $params->toArray();

        if (empty($params['process_derivative_items'])
            && empty($params['process_metadata_items'])
            && empty($params['process_derivative_media'])
            && empty($params['process_metadata_media'])
        ) {
            $message = 'No job launched.'; // @translate
            $controller->messenger()->addWarning($message);
            return true;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $derivativeEnabled = $settings->get('derivativemedia_enable', []);

        if (in_array('audio', $derivativeEnabled) || in_array('video', $derivativeEnabled)) {
            $hasFfmpeg = $this->checkFfmpeg(false);
            if (!$hasFfmpeg) {
                $message = 'The command "ffmpeg" should be available on the server to convert audio or video.'; // @translate
                $controller->messenger()->addWarning($message);
                return true;
            }
        }

        if (in_array('pdf', $derivativeEnabled) || in_array('pdf_media', $derivativeEnabled)) {
            $hasGhostscript = $this->checkGhostscript(false);
            if (!$hasGhostscript) {
                $message = 'The command "gs" (ghostscript) should be available on the server to convert pdf.'; // @translate
                $controller->messenger()->addWarning($message);
                return true;
            }
        }

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $urlPlugin = $services->get('ControllerPluginManager')->get('url');

        if (!empty($params['process_derivative_items'])) {
            $query = [];
            parse_str($params['query'] ?? '', $query);
            $args = [
                'query' => $query,
            ];
            $job = $dispatcher->dispatch(\DerivativeMedia\Job\CreateDerivatives::class, $args);
            $message = 'Creating derivative media by items ({link_url}job #{job_id}{link_end}, {link_log}logs{link_end})'; // @translate
        } elseif (!empty($params['process_derivative_media'])) {
            $queryItems = [];
            parse_str($params['query_items'] ?? '', $queryItems);
            $args = [
                'query_items' => $queryItems,
                'item_sets' => $params['item_sets'] ?? [],
                'ingesters' => $params['ingesters'] ?? [],
                'renderers' => $params['renderers'] ?? [],
                'media_types' => $params['media_types'] ?? [],
                'media_ids' => $params['media_ids'] ?? '',
            ];
            $job = $dispatcher->dispatch(\DerivativeMedia\Job\DerivativeMediaFile::class, $args);
            $message = 'Creating derivative media ({link_url}job #{job_id}{link_end}, {link_log}logs{link_end})'; // @translate
        } elseif (!empty($params['process_metadata_media'])) {
            $queryItems = [];
            parse_str($params['query_items'] ?? '', $queryItems);
            $args = [
                'query_items' => $queryItems,
                'item_sets' => $params['item_sets'] ?? [],
                'ingesters' => $params['ingesters'] ?? [],
                'renderers' => $params['renderers'] ?? [],
                'media_types' => $params['media_types'] ?? [],
                'media_ids' => $params['media_ids'] ?? '',
            ];
            $job = $dispatcher->dispatch(\DerivativeMedia\Job\DerivativeMediaMetadata::class, $args);
            $message = 'Storing metadata for existing files ({link}job #{job_id}{link_end}, {link_log}logs{link_end})'; // @translate
        }

        $message = new PsrMessage(
            $message,
            [
                'link_url' => sprintf('<a href="%s">',
                    htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%1$s">', $this->isModuleActive('Log')
                    ? $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])
                    : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);

        return true;
    }

    public function handleViewShowAfterAdmin(Event $event): void
    {
        $view = $event->getTarget();
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $view->resource;
        // Quick skip for media.
        $isMedia = $resource instanceof \Omeka\Api\Representation\MediaRepresentation;
        if ($isMedia) {
            $data = $resource->mediaData();
            if (empty($data) || empty($data['derivative'])) {
                return;
            }
        }

        /** @see \DerivativeMedia\View\Helper\Derivatives */
        $html = $view->derivatives($resource, [
            'heading' => $view->translate('Derivative files'), // @translate
            'class' => 'meta-group',
            'warn' => true,
        ]);

        if (!$html) {
            return;
        }

        echo <<<'HTML'
<style>
@media screen {
    .browse .derivative-medias h4 {
        display: inline-block;
    }
    .browse .derivative-medias ul {
        display: inline-block;
        padding-left: 6px;
    }
    .browse .sidebar .derivative-medias ul,
    .show .derivative-medias ul {
        padding-left: 0;
    }
    .derivative-medias ul li {
        list-style: none;
        display: inline-block;
    }
    .derivative-medias ul li:not(:last-child):after {
        content: ' · ';
    }
}
</style>
HTML;

        echo $html;
    }

    public function afterSaveItem(Event $event): void
    {
        // Don't run during a batch edit of items, because it runs one job by
        // item and it is slow. A batch process is always partial.
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        if ($request->getOption('isPartial')) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $enabled = $settings->get('derivativemedia_enable', []);
        $derivativeUpdate = $settings->get('derivativemedia_update', 'existing');

        $mediaLevel = ['audio', 'video', 'pdf_media'];

        $processUpdate = in_array($derivativeUpdate, ['existing_live', 'existing', 'all_live', 'all']);
        $processItemDerivative = (bool) array_diff($enabled, $mediaLevel);
        $processMediaAudioVideo = (bool) array_intersect(['audio', 'video'], $enabled);
        $processMediaPdf = in_array('pdf_media', $mediaLevel);
        $processMediaDerivative = $processMediaAudioVideo || $processMediaPdf;

        $processItem = $processItemDerivative && $processUpdate;
        $processMedia = $processMediaDerivative
            && (
                ($processMediaAudioVideo && $this->checkFfmpeg())
                || ($processMediaPdf && $this->checkGhostscript())
            );

        if (!$processItem && !$processMedia) {
            return;
        }

        /** @var \Omeka\Entity\Item $item */
        $item = $event->getParam('response')->getContent();

        $medias = $item->getMedia();
        if (!count($medias)) {
            return;
        }

        // Check new media without audio/video derivative.
        if ($processMedia) {
            $convert = false;
            foreach ($medias as $media) {
                // Don't reprocess derivative.
                $data = $media->getData();
                if (!empty($data['derivative'])) {
                    continue;
                }
                if ($this->checkConvertAudioVideo($media)) {
                    $convert = true;
                    break;
                }
            }
            if ($convert) {
                $args = [
                    'item_id' => $item->getId(),
                ];
                $dispatcher = $services->get('Omeka\Job\Dispatcher');
                $dispatcher->dispatch(\DerivativeMedia\Job\DerivativeItem::class, $args);
            }
        }

        if ($processItem) {
            $this->processDerivativeItem($item, $derivativeUpdate);
        }
    }

    public function afterSaveMedia(Event $event): void
    {
        // Don't run during a batch edit of items, because it runs one job by
        // item and it is slow. A batch process is always partial.
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        if ($request->getOption('isPartial')) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $enabled = $settings->get('derivativemedia_enable', []);
        $derivativeUpdate = $settings->get('derivativemedia_update', 'existing');

        $mediaLevel = ['audio', 'video', 'pdf_media'];

        $processUpdate = in_array($derivativeUpdate, ['existing_live', 'existing', 'all_live', 'all']);
        $processItemDerivative = (bool) array_diff($enabled, $mediaLevel);
        $processMediaAudioVideo = (bool) array_intersect(['audio', 'video'], $enabled);
        $processMediaPdf = in_array('pdf_media', $mediaLevel);
        $processMediaDerivative = $processMediaAudioVideo || $processMediaPdf;

        $processItem = $processItemDerivative && $processUpdate;
        $processMedia = $processMediaDerivative
            && (
                ($processMediaAudioVideo && $this->checkFfmpeg())
                || ($processMediaPdf && $this->checkGhostscript())
            );

        if (!$processItem && !$processMedia) {
            return;
        }

        /** @var \Omeka\Entity\Media $media */
        $media = $event->getParam('response')->getContent();

        $dispatcher = $services->get('Omeka\Job\Dispatcher');

        // Check new media without audio/video derivative.
        if ($processMedia) {
            // Don't reprocess derivative.
            $data = $media->getData();
            if (empty($data['derivative']) && $this->checkConvertAudioVideo($media)) {
                $args = [
                    'media_id' => $media->getId(),
                ];
                $dispatcher->dispatch(\DerivativeMedia\Job\DerivativeMedia::class, $args);
            }
        }

        // FIXME Find a way not to process item for each update of a media, but one time for all. The same for deletion.
        if ($processItem) {
            // $this->processDerivativeItem($media->getItem(), $derivativeUpdate);
        }
    }

    public function afterDeleteMedia(Event $event): void
    {
        /** @var \Omeka\Entity\Media $media */
        $media = $event->getTarget();
        if (!$this->isManaged($media)) {
            return;
        }

        $data = $media->getData();
        if (empty($data['derivative'])) {
            return;
        }

        $services = $this->getServiceLocator();

        /** @var \Omeka\File\Store\StoreInterface $store */
        $store = $services->get('Omeka\File\Store');
        foreach ($data['derivative'] as $folder => $derivative) {
            $storagePath = $folder . '/' . $derivative['filename'];
            $store->delete($storagePath);
        }

        // TODO See update media.
    }

    protected function processDerivativeItem(\Omeka\Entity\Item $item, string $derivativeUpdate): void
    {
        // Quick check item level and list types to process.
        $services = $this->getServiceLocator();

        /**
         * @var \Omeka\Api\Adapter\ItemAdapter $adapter
         * @var \DerivativeMedia\View\Helper\DerivativeList $derivativeList
         */
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('items');
        $derivativeList = $services->get('ViewHelperManager')->get('derivativeList');

        /** @var \Omeka\Api\Representation\ItemRepresentation $item */
        $item = $adapter->getRepresentation($item);

        // hasDerivative() checks for "dynamic_live", so no need to check here.
        $derivatives = $derivativeList($item);

        switch ($derivativeUpdate) {
            case 'existing_live':
                $todo = array_filter($derivatives, function ($v) {
                    return $v['ready']
                        && $v['mode'] === 'live';
                });
                break;
            case 'existing':
                $todo = array_filter($derivatives, function ($v) {
                    return $v['ready'];
                });
                break;
            case 'all_live':
                $todo = array_filter($derivatives, function ($v) {
                    return $v['mode'] === 'live';
                });
                break;
            case 'all':
                $todo = $derivatives;
                break;
            default:
                return;
        }

        if (!$todo) {
            return;
        }

        $args = [
            'item_id' => $item->id(),
            'type' => array_keys($todo),
        ];
        $dispatcher = $services->get('Omeka\Job\Dispatcher');
        $dispatcher->dispatch(\DerivativeMedia\Job\CreateDerivatives::class, $args);
    }

    protected function checkConvertAudioVideo(Media $media): bool
    {
        static $hasLocalStore;
        static $convertersAudio;
        static $convertersVideo;
        static $convertersPdf;

        if (is_null($hasLocalStore)) {
            $services = $this->getServiceLocator();
            /** @var \Omeka\File\Store\StoreInterface $store */
            $store = $services->get('Omeka\File\Store');
            $hasLocalStore = $store instanceof \Omeka\File\Store\Local;
            if (!$hasLocalStore) {
                $services->get('Omeka\Logger')->err(
                    '[Derivative Media] This module requires a local store currently.' // @translate
                );
                return false;
            }

            $removeCommented = function ($v, $k) {
                return !empty($v) && mb_strlen(trim($k)) && mb_substr(trim($k), 0, 1) !== '#';
            };
            $settings = $services->get('Omeka\Settings');
            $enabled = $settings->get('derivativemedia_enable', []);
            $convertersAudio = in_array('audio', $enabled)
                ? array_filter($settings->get('derivativemedia_converters_audio', []), $removeCommented, ARRAY_FILTER_USE_BOTH)
                : [];
            $convertersVideo = in_array('video', $enabled)
                ? array_filter($settings->get('derivativemedia_converters_video', []), $removeCommented, ARRAY_FILTER_USE_BOTH)
                : [];
            $convertersPdf = in_array('pdf_media', $enabled)
                ? array_filter($settings->get('derivativemedia_converters_pdf', []), $removeCommented, ARRAY_FILTER_USE_BOTH)
                : [];
        }

        if (!$hasLocalStore) {
            return false;
        }

        if (!$convertersAudio && !$convertersVideo) {
            return false;
        }

        if (!$this->isManaged($media)) {
            return false;
        }

        $mediaType = (string) $media->getMediaType();
        $mainMediaType = strtok($mediaType, '/');
        if ($convertersAudio && $mainMediaType === 'audio') {
            return true;
        } elseif ($convertersVideo && $mainMediaType === 'video') {
            return true;
        } elseif ($convertersPdf && $mediaType === 'application/pdf') {
            return true;
        }

        return false;
    }

    protected function isManaged(Media $media)
    {
        $mediaType = $media->getMediaType();
        return $mediaType
            && $media->hasOriginal()
            && $media->getRenderer() === 'file'
            && (
                in_array(strtok($mediaType, '/'), ['audio', 'video'])
                || $mediaType === 'application/pdf'
            );
    }

    protected function checkFfmpeg(bool $warnMessage = false): bool
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $checkFfmpeg = $plugins->get('checkFfmpeg');
        $checkFfmpeg = $checkFfmpeg();
        if (!$checkFfmpeg && $warnMessage) {
            $messenger = $plugins->get('messenger');
            $message = new \Omeka\Stdlib\Message(
                'The command-line utility "ffmpeg" should be installed and should be available in the cli path to make audio and video derivatives.' // @translate
            );
            $messenger->addWarning($message);
        }
        return $checkFfmpeg;
    }

    protected function checkGhostscript(bool $warnMessage = false): bool
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $checkGhostcript = $plugins->get('checkGhostscript');
        $checkGhostcript = $checkGhostcript();
        if (!$checkGhostcript && $warnMessage) {
            $messenger = $plugins->get('messenger');
            $message = new \Omeka\Stdlib\Message(
                'The command-line utility "gs" (ghoscript) should be installed and should be available in the cli path to make pdf derivatives.' // @translate
            );
            $messenger->addWarning($message);
        }
        return $checkGhostcript;
    }
}
