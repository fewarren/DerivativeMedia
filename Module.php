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
    private static function trace($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] DerivativeMedia: $message\n";

        error_log($logMessage);
        file_put_contents('/var/www/omeka-s/logs/derivativemedia_trace.log', $logMessage, FILE_APPEND | LOCK_EX);
        error_log("DERIVATIVEMEDIA_TRACE: $message");
    }

    public function getConfig()
    {
        self::trace("getConfig() called - DEVELOPMENT VERSION WITH ALL FIXES");

        $configFile = __DIR__ . '/config/module.config.php';

        if (file_exists($configFile)) {
            $config = include $configFile;

            // CRITICAL FIX: Override file store configuration to fix media URL generation
            $config['file_store'] = [
                'local' => [
                    'base_path' => '/var/www/omeka-s/files',
                    'base_uri' => 'http://linuxapp-dev.srwc.local/omeka-s/files',
                ],
            ];

            self::trace("File store base_uri overridden to: http://linuxapp-dev.srwc.local/omeka-s/files");

            return $config;
        } else {
            self::trace("ERROR: Config file does not exist!");
            return [];
        }
    }

    public function onBootstrap(MvcEvent $e)
    {
        self::trace("=== onBootstrap() called - DEVELOPMENT VERSION WITH ALL FIXES ===");

        $serviceManager = $e->getApplication()->getServiceManager();

        // COMPREHENSIVE FIX: Apply all URL generation fixes

        // 1. ServerUrl helper override (fixes hostname issues)
        self::trace("Applying ServerUrl override...");
        try {
            $viewHelperManager = $serviceManager->get('ViewHelperManager');

            $forceServerUrl = new class extends \Laminas\View\Helper\ServerUrl {
                public function __invoke($requestUri = null)
                {
                    $baseUrl = 'http://linuxapp-dev.srwc.local/omeka-s';

                    $timestamp = date('Y-m-d H:i:s');
                    $logMessage = "[$timestamp] CUSTOM_SERVERURL: Called with requestUri='$requestUri', returning '$baseUrl'\n";
                    file_put_contents('/var/www/omeka-s/logs/derivativemedia_trace.log', $logMessage, FILE_APPEND | LOCK_EX);

                    if ($requestUri === null) {
                        return $baseUrl;
                    }

                    return $baseUrl . $requestUri;
                }
            };

            $reflection = new \ReflectionClass($viewHelperManager);
            $servicesProperty = $reflection->getProperty('services');
            $servicesProperty->setAccessible(true);
            $services = $servicesProperty->getValue($viewHelperManager);
            $services['serverUrl'] = $forceServerUrl;
            $servicesProperty->setValue($viewHelperManager, $services);

            self::trace("ServerUrl override applied successfully");

        } catch (Exception $ex) {
            self::trace("ERROR in ServerUrl override: " . $ex->getMessage());
        }

        // 2. File Store override (fixes media URL generation)
        self::trace("Applying File Store override...");
        try {
            $serviceManager->setFactory('Omeka\File\Store', function($container) {
                self::trace("Creating custom file store with forced base URI");

                $storeConfig = [
                    'local' => [
                        'base_path' => '/var/www/omeka-s/files',
                        'base_uri' => 'http://linuxapp-dev.srwc.local/omeka-s/files',
                    ]
                ];

                self::trace("File store config: " . json_encode($storeConfig));

                return new \Omeka\File\Store\Local($storeConfig['local']['base_path'], $storeConfig['local']['base_uri']);
            });

            self::trace("File Store override applied successfully");

        } catch (Exception $ex) {
            self::trace("ERROR in File Store override: " . $ex->getMessage());
        }

        // 3. CRITICAL FIX: Override file renderer registration
        self::trace("Registering custom file renderers...");
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

                self::trace("Custom file renderers registered successfully");
            } else {
                self::trace("Custom file renderers disabled by setting");
            }

        } catch (Exception $e) {
            self::trace("ERROR registering custom file renderers: " . $e->getMessage());
        }

        // 4. Test both fixes
        self::trace("Testing comprehensive fixes...");
        try {
            // Test ServerUrl
            $viewHelperManager = $serviceManager->get('ViewHelperManager');
            $serverUrlHelper = $viewHelperManager->get('serverUrl');
            $serverUrlResult = $serverUrlHelper();
            self::trace("ServerUrl test result: '$serverUrlResult'");

            // Test File Store
            $fileStore = $serviceManager->get('Omeka\File\Store');
            self::trace("File store class: " . get_class($fileStore));

            $testUri = $fileStore->getUri('test/path');
            self::trace("File store test URI: '$testUri'");

            // Test media URL generation
            $api = $serviceManager->get('Omeka\ApiManager');
            $mediaRep = $api->read('media', 349)->getContent();
            $originalUrl = $mediaRep->originalUrl();
            self::trace("Media 349 originalUrl after comprehensive fixes: '$originalUrl'");

            if (strpos($originalUrl, 'http://linuxapp-dev.srwc.local') === 0) {
                self::trace("SUCCESS: Comprehensive fixes working - Media URL has correct hostname!");
            } else {
                self::trace("ERROR: Comprehensive fixes failed - Media URL still has wrong hostname");
            }

        } catch (Exception $ex) {
            self::trace("ERROR testing comprehensive fixes: " . $ex->getMessage());
        }

        // Call parent attachListeners
        $sharedEventManager = $serviceManager->get('SharedEventManager');
        $this->attachListeners($sharedEventManager);

        self::trace("=== Comprehensive fixes onBootstrap() completed ===");
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        self::trace("attachListeners() called - DEVELOPMENT VERSION WITH ALL FIXES");

        $sharedEventManager->attach(
            'Omeka\Controller\Site\Page',
            'view.show.after',
            [$this, 'handleVideoThumbnailDisplay']
        );

        self::trace("Comprehensive fixes event listeners attached");
    }

    public function handleVideoThumbnailDisplay(Event $event)
    {
        self::trace("handleVideoThumbnailDisplay() called - DEVELOPMENT VERSION");
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        self::trace("install() called");
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        self::trace("uninstall() called");
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        self::trace("upgrade() called from $oldVersion to $newVersion");
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        self::trace("getConfigForm() called");

        try {
            // Use global service manager since getServiceLocator() might not be available
            global $application;
            if (!$application) {
                $application = \Omeka\Mvc\Application::init(require "/var/www/omeka-s/application/config/application.config.php");
            }
            $services = $application->getServiceManager();

            $config = $services->get('Config');
            $settings = $services->get('Omeka\Settings');
            $form = $services->get('FormElementManager')->get('DerivativeMedia\Form\ConfigForm');

            self::trace("Services obtained successfully");

            $data = [];
            $defaultSettings = $config['derivativemedia']['settings'];
            foreach ($defaultSettings as $name => $value) {
                $data[$name] = $settings->get($name, $value);
            }

            self::trace("Data prepared, initializing form...");

            $form->init();
            $form->setData($data);
            $html = $renderer->formCollection($form);

            self::trace("getConfigForm() completed successfully, HTML length: " . strlen($html));

            return $html;

        } catch (Exception $e) {
            self::trace("ERROR in getConfigForm(): " . $e->getMessage());
            self::trace("Stack trace: " . $e->getTraceAsString());
            return '';
        }
    }

    public function handleConfigForm(AbstractController $controller)
    {
        self::trace("handleConfigForm() called");

        try {
            // Use global service manager since getServiceLocator() might not be available
            global $application;
            if (!$application) {
                $application = \Omeka\Mvc\Application::init(require "/var/www/omeka-s/application/config/application.config.php");
            }
            $services = $application->getServiceManager();

            $config = $services->get('Config');
            $settings = $services->get('Omeka\Settings');
            $form = $services->get('FormElementManager')->get('DerivativeMedia\Form\ConfigForm');

            $params = $controller->getRequest()->getPost();

            $form->init();
            $form->setData($params);

            if (!$form->isValid()) {
                $controller->messenger()->addErrors($form->getMessages());
                self::trace("handleConfigForm() validation failed");
                return false;
            }

            $defaultSettings = $config['derivativemedia']['settings'];
            $params = $form->getData();

            foreach ($params as $name => $value) {
                if (array_key_exists($name, $defaultSettings)) {
                    $settings->set($name, $value);
                    self::trace("Setting saved: $name = " . var_export($value, true));
                }
            }

            self::trace("handleConfigForm() completed successfully");
            return true;

        } catch (Exception $e) {
            self::trace("ERROR in handleConfigForm(): " . $e->getMessage());
            return false;
        }
    }
}
