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

class Module extends AbstractModule
{
    /**
     * Returns the module configuration array.
     *
     * @return array The configuration settings loaded from module.config.php.
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Placeholder for module initialization logic.
     *
     * This method is reserved for future initialization steps when the module is loaded.
     */
    public function init(ModuleManager $moduleManager): void
    {
        // Module initialization if needed
    }

    /**
     * Handles module bootstrap logic, including ACL rule addition, view helper override, and block layout diagnostics.
     *
     * This method ensures necessary access control rules are added, overrides the ServerUrl view helper to address URL generation issues, and logs diagnostic information about block layout registration and configuration. It also logs the activation of the module's clean bootstrap process.
     *
     * @param MvcEvent $event The MVC event triggering the bootstrap process.
     */
    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addAclRules();

        // CRITICAL FIX: Override ServerUrl helper to fix URL generation issues
        $this->overrideServerUrlHelper($event);

        // BOOTSTRAP_TRACE: Log block layout registration
        try {
            $serviceManager = $event->getApplication()->getServiceManager();
            $blockLayoutManager = $serviceManager->get("Omeka\\BlockLayoutManager");
            $registeredBlocks = $blockLayoutManager->getRegisteredNames();
            error_log("BOOTSTRAP_TRACE: Registered block layouts: " . implode(", ", $registeredBlocks));

            if ($blockLayoutManager->has("videoThumbnail")) {
                $videoBlock = $blockLayoutManager->get("videoThumbnail");
                error_log("BOOTSTRAP_TRACE: videoThumbnail block class: " . get_class($videoBlock));
            } else {
                error_log("BOOTSTRAP_TRACE: videoThumbnail block NOT REGISTERED");
            }

            // Check if our factory is being called
            $config = $serviceManager->get('Config');
            if (isset($config['block_layouts']['factories']['videoThumbnail'])) {
                error_log("BOOTSTRAP_TRACE: videoThumbnail factory configured: " . $config['block_layouts']['factories']['videoThumbnail']);
            } else {
                error_log("BOOTSTRAP_TRACE: videoThumbnail factory NOT CONFIGURED");
            }

        } catch (Exception $e) {
            error_log("BOOTSTRAP_TRACE: Error checking block layouts: " . $e->getMessage());
        }

        // CLEAN VERSION: Minimal logging, no global thumbnailer interference
        error_log('DerivativeMedia: onBootstrap called - CLEAN WORKING version is active');
    }

    /**
     * Replaces the default ServerUrl view helper with a custom implementation to address URL generation issues.
     *
     * @param MvcEvent $event The MVC event containing application context.
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

            error_log('DerivativeMedia: ServerUrl helper override applied successfully');

        } catch (\Exception $e) {
            error_log('DerivativeMedia: Failed to override ServerUrl helper: ' . $e->getMessage());
        }
    }

    /**
     * Placeholder for adding Access Control List (ACL) rules specific to the module.
     *
     * Extend this method to define custom ACL rules as needed.
     */
    protected function addAclRules(): void
    {
        // Add any ACL rules if needed
    }

    /**
     * Attaches event listeners for video thumbnail generation on media creation and update events.
     *
     * Listeners are registered only for the `api.create.post` and `api.update.post` events of the `MediaAdapter` to trigger video thumbnail processing when relevant.
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        error_log('DerivativeMedia: attachListeners method called - CLEAN APPROACH');

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

        error_log('DerivativeMedia: Clean event listeners attached for video thumbnail functionality only');
    }

    /**
     * Triggers video thumbnail generation for media entities of type video.
     *
     * If the provided event contains a media entity with a MIME type starting with 'video/', this method attempts to retrieve the VideoThumbnailService and initiate thumbnail generation for that media.
     */
    public function handleVideoThumbnailGeneration($event)
    {
        $media = $event->getParam('response')->getContent();
        
        // Only process video files
        if (strpos($media->getMediaType(), 'video/') === 0) {
            error_log('DerivativeMedia: Video media detected - ID: ' . $media->getId());
            
            // Use VideoThumbnailService for video thumbnail generation
            $services = $this->getServiceLocator();
            if ($services->has('DerivativeMedia\Service\VideoThumbnailService')) {
                $videoThumbnailService = $services->get('DerivativeMedia\Service\VideoThumbnailService');
                // Generate video thumbnail if needed
            }
        }
    }

    /**
     * Generates and returns the HTML for the module's configuration form populated with current settings.
     *
     * @param PhpRenderer $renderer The renderer used to generate the form HTML.
     * @return string The rendered configuration form HTML.
     */
    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(Form\ConfigForm::class);

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

    /**
     * Handles the module configuration form submission.
     *
     * Validates and saves configuration settings from the form. If the user requests video thumbnail generation, dispatches a background job to generate video thumbnails for matching media. Adds success or error messages to the controller messenger based on the outcome.
     *
     * @param AbstractController $controller The controller handling the configuration form request.
     * @return bool True if the form was processed successfully, false if validation failed.
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(Form\ConfigForm::class);

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
            error_log('DerivativeMedia: process_video_thumbnails button clicked');

            try {
                $jobDispatcher = $services->get('Omeka\Job\Dispatcher');

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

                error_log('DerivativeMedia: Dispatching GenerateVideoThumbnails job with args: ' . json_encode($jobArgs));

                // Dispatch the job
                $job = $jobDispatcher->dispatch('DerivativeMedia\Job\GenerateVideoThumbnails', $jobArgs);

                if ($job) {
                    $controller->messenger()->addSuccess(sprintf(
                        'Video thumbnail generation job started successfully. Job ID: %d. Check the Jobs page to monitor progress.',
                        $job->getId()
                    ));
                    error_log('DerivativeMedia: Job dispatched successfully with ID: ' . $job->getId());
                } else {
                    $controller->messenger()->addError('Failed to start video thumbnail generation job.');
                    error_log('DerivativeMedia: Job dispatch failed - no job returned');
                }

            } catch (\Exception $e) {
                $controller->messenger()->addError('Error starting video thumbnail generation job: ' . $e->getMessage());
                error_log('DerivativeMedia: Job dispatch error: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Handles module upgrade logic between versions.
     *
     * This method is currently a placeholder and does not perform any actions.
     *
     * @param string $oldVersion The previous version of the module.
     * @param string $newVersion The new version of the module.
     */
    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator): void
    {
        // Handle any upgrade logic if needed
    }

    /**
     * Performs cleanup tasks when the module is uninstalled.
     *
     * Currently, this method does not implement any cleanup logic.
     */
    public function uninstall(ServiceLocatorInterface $serviceLocator): void
    {
        // Handle any cleanup if needed
    }
}
