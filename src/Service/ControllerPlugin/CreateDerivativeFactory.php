<?php declare(strict_types=1);

namespace DerivativeMedia\Service\ControllerPlugin;

use DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CreateDerivativeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $viewHelpers = $services->get('ViewHelperManager');
        return new CreateDerivative(
            $services->get('Omeka\Cli'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Settings'),
            // Don't use controller plugin "url", an exception occurs:
            // Laminas\Mvc\Exception\DomainException: Url plugin requires a controller that implements InjectApplicationEventInterface
            $viewHelpers->get('url'),
            $basePath,
            $viewHelpers->has('iiifManifest') ? $viewHelpers->get('iiifManifest') : null,
            $viewHelpers->has('xmlAltoSingle') ? $viewHelpers->get('xmlAltoSingle') : null
        );
    }
}
