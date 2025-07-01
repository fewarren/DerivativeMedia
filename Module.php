<?php declare(strict_types=1);

namespace DerivativeMedia;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    /**
     * @var \DerivativeMedia\Service\DebugManager|null
     */
    private $logger = null;

    /**
     * Get or initialize the logger instance
     *
     * @return \DerivativeMedia\Service\DebugManager
     */
    private function getLogger()
    {
        if ($this->logger === null) {
            try {
                // Try to get logger from service locator if available
                if (method_exists($this, 'getServiceLocator') && $this->getServiceLocator()) {
                    $services = $this->getServiceLocator();
                    if ($services && $services->has('DerivativeMedia\Service\DebugManager')) {
                        $this->logger = $services->get('DerivativeMedia\Service\DebugManager');
                    }
                }
            } catch (\Exception $e) {
                // Service locator not available or service not found
            }

            // Fallback: create DebugManager directly
            if ($this->logger === null) {
                // Include the DebugManager class if not already loaded
                $debugManagerPath = __DIR__ . '/src/Service/DebugManager.php';
                if (file_exists($debugManagerPath)) {
                    require_once $debugManagerPath;
                    $this->logger = new \DerivativeMedia\Service\DebugManager([
                        'debug_enabled' => true,
                        'log_file' => 'derivativemedia_trace.log',
                        'base_log_path' => null, // Auto-detect
                    ]);
                } else {
                    // Ultimate fallback: create a simple logger
                    $this->logger = new class {
                        public function logInfo($message, $component = 'MODULE', $operationId = '') {
                            error_log("DerivativeMedia: $message");
                        }
                        public function logError($message, $component = 'MODULE', $operationId = '') {
                            error_log("DerivativeMedia ERROR: $message");
                        }
                    };
                }
            }
        }

        return $this->logger;
    }

    /**
     * Log a trace message using the injected logger
     *
     * @param string $message The message to log
     * @param string $operationId Optional operation ID for tracking
     */
    private function trace($message, $operationId = '')
    {
        $this->getLogger()->logInfo($message, 'MODULE', $operationId ?: 'module-' . uniqid());
    }



    public function getConfig()
    {
        $this->trace("getConfig() called - Using standard Omeka S configuration");

        $configFile = __DIR__ . '/config/module.config.php';

        if (file_exists($configFile)) {
            $config = include $configFile;
            $this->trace("Module configuration loaded successfully");
            return $config;
        } else {
            $this->trace("ERROR: Config file does not exist!");
            return [];
        }
    }

    public function onBootstrap(MvcEvent $e)
    {
        $this->trace("=== onBootstrap() called - DEVELOPMENT VERSION WITH ALL FIXES ===");

        $serviceManager = $e->getApplication()->getServiceManager();

        // COMPREHENSIVE FIX: Apply all URL generation fixes

        // 1. ServerUrl helper override (fixes hostname issues)
        $this->trace("Applying ServerUrl override...");
        try {
            $viewHelperManager = $serviceManager->get('ViewHelperManager');

            // Get base URL from standard Omeka S configuration
            $config = $serviceManager->get('Config');
            $configuredBaseUrl = $config['base_url'] ?? null;

            if (!$configuredBaseUrl) {
                $this->trace("WARNING: base_url not configured in local.config.php - using auto-detection");
                // Auto-detect from server variables as fallback
                $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $configuredBaseUrl = "$scheme://$host/omeka-s";
            }

            $forceServerUrl = new class($configuredBaseUrl) extends \Laminas\View\Helper\ServerUrl {
                private $baseUrl;

                public function __construct($baseUrl) {
                    $this->baseUrl = $baseUrl;
                }

                public function __invoke($requestUri = null)
                {
                    // Use error_log for debugging (uses standard Omeka S base_url)
                    error_log("CUSTOM_SERVERURL: Called with requestUri='$requestUri', returning '{$this->baseUrl}'");

                    if ($requestUri === null) {
                        return $this->baseUrl;
                    }

                    return $this->baseUrl . $requestUri;
                }
            };

            $reflection = new \ReflectionClass($viewHelperManager);
            $servicesProperty = $reflection->getProperty('services');
            $servicesProperty->setAccessible(true);
            $services = $servicesProperty->getValue($viewHelperManager);
            $services['serverUrl'] = $forceServerUrl;
            $servicesProperty->setValue($viewHelperManager, $services);

            $this->trace("ServerUrl override applied successfully");

        } catch (Exception $ex) {
            $this->trace("ERROR in ServerUrl override: " . $ex->getMessage());
        }

        // 2. File Store - Use standard Omeka S configuration (no override needed)
        $this->trace("Using standard Omeka S file store configuration from local.config.php");

        // 3. CRITICAL FIX: Override file renderer registration
        $this->trace("Registering custom file renderers...");
        try {
            $settings = $serviceManager->get('Omeka\Settings');
            $customRenderersEnabled = $settings->get('derivativemedia_enable_custom_file_renderers', true);

            if ($customRenderersEnabled) {
                $fileRendererManager = $serviceManager->get('Omeka\Media\FileRenderer\Manager');

                // Force registration of our custom renderers
                $fileRendererManager->setService('video', new \DerivativeMedia\Media\FileRenderer\VideoRenderer());
                $fileRendererManager->setService('audio', new \DerivativeMedia\Media\FileRenderer\AudioRenderer());

                // Override aliases to point to our custom renderers
                $fileRendererManager->setAlias('video/mp4', 'video');
                $fileRendererManager->setAlias('video/quicktime', 'video');
                $fileRendererManager->setAlias('video/x-msvideo', 'video');
                $fileRendererManager->setAlias('video/ogg', 'video');
                $fileRendererManager->setAlias('video/webm', 'video');
                $fileRendererManager->setAlias('application/ogg', 'video');

                $fileRendererManager->setAlias('audio/ogg', 'audio');
                $fileRendererManager->setAlias('audio/x-aac', 'audio');
                $fileRendererManager->setAlias('audio/mpeg', 'audio');
                $fileRendererManager->setAlias('audio/mp4', 'audio');
                $fileRendererManager->setAlias('audio/x-wav', 'audio');
                $fileRendererManager->setAlias('audio/x-aiff', 'audio');
                $fileRendererManager->setAlias('mp3', 'audio');

                $this->trace("Custom file renderers registered successfully");
            } else {
                $this->trace("Custom file renderers disabled by setting");
            }

        } catch (Exception $e) {
            $this->trace("ERROR registering custom file renderers: " . $e->getMessage());
        }

        // 4. Test both fixes
        $this->trace("Testing comprehensive fixes...");
        try {
            // Test ServerUrl
            $viewHelperManager = $serviceManager->get('ViewHelperManager');
            $serverUrlHelper = $viewHelperManager->get('serverUrl');
            $serverUrlResult = $serverUrlHelper();
            $this->trace("ServerUrl test result: '$serverUrlResult'");

            // Test File Store
            $fileStore = $serviceManager->get('Omeka\File\Store');
            $this->trace("File store class: " . get_class($fileStore));

            $testUri = $fileStore->getUri('test/path');
            $this->trace("File store test URI: '$testUri'");

            // Test media URL generation
            $api = $serviceManager->get('Omeka\ApiManager');
            $mediaRep = $api->read('media', 349)->getContent();
            $originalUrl = $mediaRep->originalUrl();
            $this->trace("Media 349 originalUrl after comprehensive fixes: '$originalUrl'");

            // Get expected base URL from standard Omeka S configuration
            $config = $serviceManager->get('Config');
            $expectedBaseUrl = $config['base_url'] ?? null;

            if (!$expectedBaseUrl) {
                $this->trace("WARNING: base_url not configured in local.config.php for testing");
                // Auto-detect from server variables as fallback
                $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $expectedBaseUrl = "$scheme://$host/omeka-s";
            }

            if (strpos($originalUrl, $expectedBaseUrl) === 0) {
                $this->trace("SUCCESS: Comprehensive fixes working - Media URL has correct hostname!");
            } else {
                $this->trace("ERROR: Comprehensive fixes failed - Media URL has wrong hostname. Expected: $expectedBaseUrl, Got: $originalUrl");
            }

        } catch (Exception $ex) {
            $this->trace("ERROR testing comprehensive fixes: " . $ex->getMessage());
        }

        // Call parent attachListeners
        $sharedEventManager = $serviceManager->get('SharedEventManager');
        $this->attachListeners($sharedEventManager);

        $this->trace("=== Comprehensive fixes onBootstrap() completed ===");
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $this->trace("attachListeners() called - DEVELOPMENT VERSION WITH ALL FIXES");

        // CRITICAL: Video thumbnail generation event listeners
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

        // Optional: Page display events
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Page',
            'view.show.after',
            [$this, 'handleVideoThumbnailDisplay']
        );

        $this->trace("Comprehensive fixes event listeners attached (including video thumbnail generation)");
    }

    /**
     * Handle video thumbnail generation when media is created or updated
     */
    public function handleVideoThumbnailGeneration($event)
    {
        $this->trace("handleVideoThumbnailGeneration() called");

        try {
            $media = $event->getParam('response')->getContent();

            // Only process video files
            if (strpos($media->getMediaType(), 'video/') === 0) {
                $this->trace("Video media detected - ID: " . $media->getId() . ", Type: " . $media->getMediaType());

                // Get service manager
                $services = $this->getServiceLocator();
                if (!$services) {
                    // Try to get from event
                    $services = $event->getTarget()->getServiceLocator();
                }

                if ($services && $services->has('DerivativeMedia\Service\VideoThumbnailService')) {
                    $videoThumbnailService = $services->get('DerivativeMedia\Service\VideoThumbnailService');

                    // Get thumbnail percentage from settings
                    $settings = $services->get('Omeka\Settings');
                    $percentage = (int) $settings->get('derivativemedia_video_thumbnail_percentage', 25);

                    $this->trace("Generating video thumbnail for media " . $media->getId() . " at {$percentage}% position");

                    // Generate the thumbnail
                    $success = $videoThumbnailService->generateThumbnail($media, $percentage);

                    if ($success) {
                        $this->trace("Video thumbnail generated successfully for media " . $media->getId());
                    } else {
                        $this->trace("Video thumbnail generation failed for media " . $media->getId());
                    }
                } else {
                    $this->trace("VideoThumbnailService not available");
                }
            } else {
                $this->trace("Non-video media detected - ID: " . $media->getId() . ", Type: " . $media->getMediaType());
            }

        } catch (Exception $e) {
            $this->trace("ERROR in handleVideoThumbnailGeneration: " . $e->getMessage());
        }
    }

    public function handleVideoThumbnailDisplay(Event $event)
    {
        $this->trace("handleVideoThumbnailDisplay() called - DEVELOPMENT VERSION");
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->trace("install() called");
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->trace("uninstall() called");
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        $this->trace("upgrade() called from $oldVersion to $newVersion");
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $this->trace("getConfigForm() called");

        try {
            // Use proper dependency injection through service locator
            $services = $this->getServiceLocator();

            $config = $services->get('Config');
            $settings = $services->get('Omeka\Settings');
            $form = $services->get('FormElementManager')->get('DerivativeMedia\Form\ConfigForm');

            $this->trace("Services obtained successfully");

            $data = [];
            $defaultSettings = $config['derivativemedia']['settings'];
            foreach ($defaultSettings as $name => $value) {
                $data[$name] = $settings->get($name, $value);
            }

            $this->trace("Data prepared, initializing form...");

            $form->init();
            $form->setData($data);
            $html = $renderer->formCollection($form);

            $this->trace("getConfigForm() completed successfully, HTML length: " . strlen($html));

            return $html;

        } catch (Exception $e) {
            $this->trace("ERROR in getConfigForm(): " . $e->getMessage());
            $this->trace("Stack trace: " . $e->getTraceAsString());
            return '';
        }
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $this->trace("handleConfigForm() called");

        try {
            // Use proper dependency injection through service locator
            $services = $this->getServiceLocator();

            $config = $services->get('Config');
            $settings = $services->get('Omeka\Settings');
            $form = $services->get('FormElementManager')->get('DerivativeMedia\Form\ConfigForm');

            $params = $controller->getRequest()->getPost();

            $form->init();
            $form->setData($params);

            if (!$form->isValid()) {
                $controller->messenger()->addErrors($form->getMessages());
                $this->trace("handleConfigForm() validation failed");
                return false;
            }

            $defaultSettings = $config['derivativemedia']['settings'];
            $params = $form->getData();

            // Save settings first
            foreach ($params as $name => $value) {
                if (array_key_exists($name, $defaultSettings)) {
                    $settings->set($name, $value);
                    $this->trace("Setting saved: $name = " . var_export($value, true));
                }
            }

            // CRITICAL: Check for job dispatch buttons
            if (isset($params['process_video_thumbnails'])) {
                $this->trace("Video thumbnail job dispatch requested");

                try {
                    // Get job dispatcher
                    $jobDispatcher = $services->get('Omeka\Job\Dispatcher');

                    // Prepare job arguments
                    $jobArgs = [
                        'query' => [], // Process all video media
                        'force_regenerate' => isset($params['force_regenerate_thumbnails']) ? (bool)$params['force_regenerate_thumbnails'] : false,
                        'percentage' => isset($params['derivativemedia_video_thumbnail_percentage']) ? (int)$params['derivativemedia_video_thumbnail_percentage'] : null,
                    ];

                    $this->trace("Dispatching GenerateVideoThumbnails job with args: " . json_encode($jobArgs));

                    // Dispatch the job
                    $job = $jobDispatcher->dispatch('DerivativeMedia\Job\GenerateVideoThumbnails', $jobArgs);

                    if ($job) {
                        $controller->messenger()->addSuccess('Video thumbnail generation job started successfully. Check the Jobs page for progress.'); // @translate
                        $this->trace("Job dispatched successfully with ID: " . $job->getId());
                    } else {
                        $controller->messenger()->addError('Failed to start video thumbnail generation job.'); // @translate
                        $this->trace("Job dispatch failed - no job returned");
                    }

                } catch (Exception $e) {
                    $controller->messenger()->addError('Error starting video thumbnail generation job: ' . $e->getMessage()); // @translate
                    $this->trace("Job dispatch error: " . $e->getMessage());
                }
            }

            $this->trace("handleConfigForm() completed successfully");
            return true;

        } catch (Exception $e) {
            $this->trace("ERROR in handleConfigForm(): " . $e->getMessage());
            return false;
        }
    }
}
