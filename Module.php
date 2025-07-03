<?php declare(strict_types=1);

namespace DerivativeMedia;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Omeka\Module\AbstractModule;
use Omeka\Entity\Media;
use DerivativeMedia\Form;
use DerivativeMedia\Service\DebugManager;

class Module extends AbstractModule
{
    /**
     * @var DebugManager|null
     */
    private $debugManager;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function init(ModuleManager $moduleManager): void
    {
        // Module initialization if needed
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        // Initialize DebugManager for configurable logging
        $this->initializeDebugManager($event);

        $this->addAclRules();

        // CRITICAL FIX: Override ServerUrl helper to fix URL generation issues
        $this->overrideServerUrlHelper($event);

        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
        if ($this->debugManager) {
            $operationId = 'bootstrap-' . uniqid();
            $this->debugManager->logInfo('onBootstrap called - CLEAN WORKING version is active', DebugManager::COMPONENT_MODULE, $operationId);

            try {
                $serviceManager = $event->getApplication()->getServiceManager();
                $blockLayoutManager = $serviceManager->get("Omeka\\BlockLayoutManager");
                $registeredBlocks = $blockLayoutManager->getRegisteredNames();
                $this->debugManager->logDebug("Registered block layouts: " . implode(", ", $registeredBlocks), DebugManager::COMPONENT_MODULE, $operationId);

                if ($blockLayoutManager->has("videoThumbnail")) {
                    $videoBlock = $blockLayoutManager->get("videoThumbnail");
                    $this->debugManager->logDebug("videoThumbnail block class: " . get_class($videoBlock), DebugManager::COMPONENT_MODULE, $operationId);
                } else {
                    $this->debugManager->logWarning("videoThumbnail block NOT REGISTERED", DebugManager::COMPONENT_MODULE, $operationId);
                }

                // Check if our factory is being called
                $config = $serviceManager->get('Config');
                if (isset($config['block_layouts']['factories']['videoThumbnail'])) {
                    $this->debugManager->logDebug("videoThumbnail factory configured: " . $config['block_layouts']['factories']['videoThumbnail'], DebugManager::COMPONENT_MODULE, $operationId);
                } else {
                    $this->debugManager->logWarning("videoThumbnail factory NOT CONFIGURED", DebugManager::COMPONENT_MODULE, $operationId);
                }

            } catch (\Exception $e) {
                $this->debugManager->logError("Error checking block layouts: " . $e->getMessage(), DebugManager::COMPONENT_MODULE, $operationId);
            }
        }
    }

    /**
     * Initialize DebugManager for configurable logging
     */
    protected function initializeDebugManager(MvcEvent $event): void
    {
        try {
            $serviceManager = $event->getApplication()->getServiceManager();
            $this->debugManager = $serviceManager->get('DerivativeMedia\Service\DebugManager');
        } catch (\Exception $e) {
            // DebugManager not available, continue without logging
            $this->debugManager = null;
        }
    }

    /**
     * Override the ServerUrl helper to fix URL generation issues
     */
    protected function overrideServerUrlHelper(MvcEvent $event): void
    {
        try {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewHelperManager = $serviceManager->get('ViewHelperManager');

            // Override the ServerUrl helper with our custom implementation
            $viewHelperManager->setFactory('serverUrl', function($container) {
                return new View\Helper\CustomServerUrl();
            });

            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            if ($this->debugManager) {
                $this->debugManager->logInfo('ServerUrl helper override applied successfully', DebugManager::COMPONENT_MODULE);
            }

        } catch (\Exception $e) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            if ($this->debugManager) {
                $this->debugManager->logError('Failed to override ServerUrl helper: ' . $e->getMessage(), DebugManager::COMPONENT_MODULE);
            }
        }
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules(): void
    {
        // Add any ACL rules if needed
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
        if ($this->debugManager) {
            $this->debugManager->logInfo('attachListeners method called - CLEAN APPROACH', DebugManager::COMPONENT_MODULE);
        }

        // CLEAN APPROACH: Only attach essential listeners for video thumbnail functionality
        // No global thumbnailer interference that causes CSS issues

        // Only attach video thumbnail generation when specifically needed
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.create.post',
            [$this, 'handleVideoThumbnailGeneration']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.update.post',
            [$this, 'handleVideoThumbnailGeneration']
        );

        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
        if ($this->debugManager) {
            $this->debugManager->logInfo('Clean event listeners attached for video thumbnail functionality only', DebugManager::COMPONENT_MODULE);
        }
    }

    /**
     * Handle video thumbnail generation - clean approach
     */
    public function handleVideoThumbnailGeneration($event)
    {
        $media = $event->getParam('response')->getContent();

        // Only process video files
        if (strpos($media->getMediaType(), 'video/') === 0) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            if ($this->debugManager) {
                $operationId = 'video-thumb-' . uniqid();
                $this->debugManager->logInfo('Video media detected - ID: ' . $media->getId(), DebugManager::COMPONENT_MODULE, $operationId);
            }

            // DEPENDENCY INJECTION FIX: Get services from event instead of deprecated service locator
            $serviceManager = $event->getTarget()->getServiceLocator();
            $settings = $serviceManager->get('Omeka\Settings');

            // Check if automatic video thumbnail generation is enabled
            $autoThumbnailEnabled = $settings->get('derivativemedia_video_thumbnail_enabled', false);
            if (!$autoThumbnailEnabled) {
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                if ($this->debugManager) {
                    $this->debugManager->logInfo('Automatic video thumbnail generation is disabled in settings', DebugManager::COMPONENT_MODULE, $operationId ?? 'video-thumb-' . uniqid());
                }
                return;
            }

            // Use VideoThumbnailService for video thumbnail generation
            if ($serviceManager->has('DerivativeMedia\Service\VideoThumbnailService')) {
                $videoThumbnailService = $serviceManager->get('DerivativeMedia\Service\VideoThumbnailService');

                // CRITICAL FIX: Actually call the generateThumbnail method
                try {
                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    if ($this->debugManager) {
                        $this->debugManager->logInfo('Calling generateThumbnail for media ID: ' . $media->getId(), DebugManager::COMPONENT_MODULE, $operationId ?? 'video-thumb-' . uniqid());
                    }

                    // Get configured thumbnail percentage
                    $thumbnailPercentage = $settings->get('derivativemedia_video_thumbnail_percentage', 8);

                    // For automatic generation, don't force regeneration (preserve existing thumbnails)
                    $result = $videoThumbnailService->generateThumbnail($media, $thumbnailPercentage, false);

                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    if ($this->debugManager) {
                        if ($result) {
                            $this->debugManager->logInfo('Video thumbnail generation successful for media ID: ' . $media->getId(), DebugManager::COMPONENT_MODULE, $operationId ?? 'video-thumb-' . uniqid());
                        } else {
                            $this->debugManager->logWarning('Video thumbnail generation failed for media ID: ' . $media->getId(), DebugManager::COMPONENT_MODULE, $operationId ?? 'video-thumb-' . uniqid());
                        }
                    }
                } catch (\Exception $e) {
                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    if ($this->debugManager) {
                        $this->debugManager->logError('Exception during video thumbnail generation for media ID: ' . $media->getId() . ' - ' . $e->getMessage(), DebugManager::COMPONENT_MODULE, $operationId ?? 'video-thumb-' . uniqid());
                    }
                }
            } else {
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                if ($this->debugManager) {
                    $this->debugManager->logError('VideoThumbnailService not available', DebugManager::COMPONENT_MODULE, $operationId ?? 'video-thumb-' . uniqid());
                }
            }
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        // DEPENDENCY INJECTION FIX: Get services from renderer's service locator instead of deprecated getServiceLocator()
        $serviceLocator = $renderer->getHelperPluginManager()->getServiceLocator();
        $config = $serviceLocator->get('Config');
        $settings = $serviceLocator->get('Omeka\Settings');
        $form = $serviceLocator->get('FormElementManager')->get(Form\ConfigForm::class);

        $data = [];
        $defaultSettings = $config['derivativemedia']['settings'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }
        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        // DEPENDENCY INJECTION FIX: Get services from controller's event instead of deprecated getServiceLocator()
        $serviceManager = $controller->getEvent()->getApplication()->getServiceManager();
        $config = $serviceManager->get('Config');
        $settings = $serviceManager->get('Omeka\Settings');
        $form = $serviceManager->get('FormElementManager')->get(Form\ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        // Save regular settings first
        $defaultSettings = $config['derivativemedia']['settings'];
        $params = $form->getData();
        foreach ($params as $name => $value) {
            if (array_key_exists($name, $defaultSettings)) {
                $settings->set($name, $value);
            }
        }

        // Handle job dispatch for video thumbnail generation
        if (isset($params['process_video_thumbnails'])) {
            // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
            if ($this->debugManager) {
                $operationId = 'job-dispatch-' . uniqid();
                $this->debugManager->logInfo('process_video_thumbnails button clicked', DebugManager::COMPONENT_MODULE, $operationId);
            }

            try {
                $jobDispatcher = $serviceManager->get('Omeka\Job\Dispatcher');

                // Prepare job arguments
                $jobArgs = [
                    'query' => [],
                    'force_regenerate' => !empty($params['force_regenerate_thumbnails']),
                    'percentage' => !empty($params['video_thumbnail_percentage']) ? (int)$params['video_thumbnail_percentage'] : null,
                ];

                // Add video query if provided
                if (!empty($params['video_query'])) {
                    $jobArgs['query']['fulltext_search'] = trim($params['video_query']);
                }

                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                if ($this->debugManager) {
                    $this->debugManager->logInfo('Dispatching GenerateVideoThumbnails job with args: ' . json_encode($jobArgs), DebugManager::COMPONENT_MODULE, $operationId ?? 'job-dispatch-' . uniqid());
                }

                // Dispatch the job
                $job = $jobDispatcher->dispatch('DerivativeMedia\Job\GenerateVideoThumbnails', $jobArgs);

                if ($job) {
                    $controller->messenger()->addSuccess(sprintf(
                        'Video thumbnail generation job started successfully. Job ID: %d. Check the Jobs page to monitor progress.',
                        $job->getId()
                    ));
                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    if ($this->debugManager) {
                        $this->debugManager->logInfo('Job dispatched successfully with ID: ' . $job->getId(), DebugManager::COMPONENT_MODULE, $operationId ?? 'job-dispatch-' . uniqid());
                    }
                } else {
                    $controller->messenger()->addError('Failed to start video thumbnail generation job.');
                    // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                    if ($this->debugManager) {
                        $this->debugManager->logError('Job dispatch failed - no job returned', DebugManager::COMPONENT_MODULE, $operationId ?? 'job-dispatch-' . uniqid());
                    }
                }

            } catch (\Exception $e) {
                $controller->messenger()->addError('Error starting video thumbnail generation job: ' . $e->getMessage());
                // CONFIGURABLE LOGGING FIX: Use DebugManager instead of direct error_log
                if ($this->debugManager) {
                    $this->debugManager->logError('Job dispatch error: ' . $e->getMessage(), DebugManager::COMPONENT_MODULE, $operationId ?? 'job-dispatch-' . uniqid());
                }
            }
        }

        return true;
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator): void
    {
        // Handle any upgrade logic if needed
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator): void
    {
        // Handle any cleanup if needed
    }
}
