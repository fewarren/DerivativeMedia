<?php declare(strict_types=1);

namespace DerivativeMedia\Service\ViewHelper;

use DerivativeMedia\View\Helper\DerivativeList;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DerivativeListFactory implements FactoryInterface
{
    /**
     * Create and return the HasDerivative view helper
     *
     * @return DerivativeList
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $helpers = $services->get('ViewHelperManager');
        $serverUrl = $helpers->get('serverUrl');
        $basePath = $helpers->get('basePath');

        // The base url of files is used for derivative files.
        $baseUrlFiles = $serverUrl($basePath('/files'));

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        return new DerivativeList(
            $basePath,
            $baseUrlFiles,
            $settings->get('derivativemedia_enable', []),
            (int) $settings->get('derivativemedia_max_size_live', 30),
            $services->get('ViewHelperManager')->get('Url')
        );
    }
}
