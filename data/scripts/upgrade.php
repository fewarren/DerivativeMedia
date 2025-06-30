<?php declare(strict_types=1);

namespace DerivativeMedia;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$configLocal = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.62')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.62'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.4.4', '<')) {
    $settings->set('derivativemedia_enable', []);
    $message = new PsrMessage(
        'A new option was added to enable specific converters.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'It is now possible to output a zip of all files of an item (format url: https://example.org/derivative/zip/{item_id}).' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.5', '<')) {
    $settings->set('derivativemedia_update', 'existing');
    $message = new PsrMessage(
        'Many new formats have been added: zip, text, alto, iiif, pdf.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'A resource page block allows to display the list of available derivatives of a resource.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'Check {link_url}new settings{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', $url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable'])), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.8', '<')) {
    $message = new PsrMessage(
        'The module manages now http requests "Content Range" that allow to read files faster.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.9', '<')) {
    $settings->set('derivativemedia_converters_pdf', $configLocal['derivativemedia']['settings']['derivativemedia_converters_pdf']);
    $settings->set('derivativemedia_append_original_pdf', $configLocal['derivativemedia']['settings']['derivativemedia_append_original_pdf']);

    $message = new PsrMessage(
        'Helpers "derivativeMedia" and "hasDerivative" were renamed "derivatives" and "derivativeList".' // @translate
    );
    $messenger->addNotice($message);

    $message = new PsrMessage(
        'The module manages now pdf files. Check {link_url}new settings{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', $url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable'])), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.10', '<')) {
    $message = new PsrMessage(
        'It is now possible to run the job to create derivative and metadata by items. See {link_url}config form{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', $url('admin/default', ['controller' => 'module'], ['query' => ['id' => 'DerivativeMedia']])), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Settings were updated. You may check {link_url}them{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', $url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable'])), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.12', '<')) {
    $message = new PsrMessage(
        'Helpers "derivativeMedia" and "hasDerivative" were renamed "derivatives" and "derivativeList".' // @translate
    );
    $messenger->addWarning($message);
}
