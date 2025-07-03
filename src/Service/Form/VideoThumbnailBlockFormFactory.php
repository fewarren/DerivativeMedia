<?php declare(strict_types=1);

namespace DerivativeMedia\Service\Form;

use DerivativeMedia\Form\VideoThumbnailBlockForm;
use DerivativeMedia\Service\DebugManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class VideoThumbnailBlockFormFactory implements FactoryInterface
{
    /**
     * Creates and returns a new instance of VideoThumbnailBlockForm.
     *
     * Attempts to retrieve a DebugManager from the container for logging and tracing; falls back to direct instantiation if unavailable. Logs the factory invocation and traces the operation. If form creation fails, logs the error and rethrows the exception. Form options are not populated here to allow for site-specific handling elsewhere.
     *
     * @param ContainerInterface $container The dependency injection container.
     * @param string $requestedName The name of the requested service.
     * @param array|null $options Optional parameters (not used).
     * @return VideoThumbnailBlockForm The created form instance.
     * @throws \Exception If form instantiation fails.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        // Get DebugManager for proper logging
        $debugManager = null;
        try {
            $debugManager = $container->get('DerivativeMedia\Service\DebugManager');
        } catch (\Exception $e) {
            // Fallback: create DebugManager directly if service not available
            $debugManager = new DebugManager();
        }

        $opId = 'form_factory_' . uniqid();

        // Use proper debug logging instead of unconditional error_log
        $debugManager->logInfo(
            sprintf('VideoThumbnailBlockFormFactory invoked with requestedName: %s', $requestedName),
            DebugManager::COMPONENT_FACTORY,
            $opId
        );

        $debugManager->traceFormFactory($opId, ['requestedName' => $requestedName]);

        try {
            $form = new VideoThumbnailBlockForm();

            // Note: We don't populate options here - that's done in the block layout form method
            // This allows for site-specific filtering and proper context

            $debugManager->logInfo('VideoThumbnailBlockForm factory completed successfully', DebugManager::COMPONENT_FACTORY, $opId);
            return $form;
        } catch (\Exception $e) {
            $debugManager->logError(
                sprintf('Failed to create VideoThumbnailBlockForm: %s', $e->getMessage()),
                DebugManager::COMPONENT_FACTORY,
                $opId
            );
            throw $e;
        }
    }
}
