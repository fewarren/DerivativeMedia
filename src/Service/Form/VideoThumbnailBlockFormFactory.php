<?php declare(strict_types=1);

namespace DerivativeMedia\Service\Form;

use DerivativeMedia\Form\VideoThumbnailBlockForm;
use DerivativeMedia\Service\DebugManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class VideoThumbnailBlockFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        // CRITICAL DEBUG: Add error_log to ensure factory is being called
        error_log("DerivativeMedia: VideoThumbnailBlockFormFactory::__invoke called with requestedName: $requestedName");

        $debugManager = $container->get('DerivativeMedia\Service\DebugManager');

        $opId = 'form_factory_' . uniqid();
        $debugManager->traceFormFactory($opId, ['requestedName' => $requestedName]);

        $form = new VideoThumbnailBlockForm();

        // Note: We don't populate options here - that's done in the block layout form method
        // This allows for site-specific filtering and proper context

        $debugManager->logInfo('VideoThumbnailBlockForm factory completed successfully', DebugManager::COMPONENT_FACTORY, $opId);
        return $form;
    }
}
