<?php declare(strict_types=1);

namespace DerivativeMedia\Mvc\Controller\Plugin;

use IiifSearch\View\Helper\XmlAltoSingle;
use IiifServer\View\Helper\IiifManifest;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\View\Helper\Url;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Cli;
use ZipArchive;

class CreateDerivative extends AbstractPlugin
{
    use TraitDerivative;

    /**
     * @var \Omeka\Stdlib\Cli
     */
    protected $cli;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Laminas\View\Helper\Url
     */
    protected $url;

    /**
     * @var \IiifServer\View\Helper\IiifManifest
     */
    protected $iiifManifest;

    /**
     * @var \IiifSearch\View\Helper\XmlAltoSingle
     */
    protected $xmlAltoSingle;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct(
        Cli $cli,
        Logger $logger,
        Settings $settings,
        Url $url,
        string $basePath,
        ?IiifManifest $iiifManifest,
        ?XmlAltoSingle $xmlAltoSingle
    ) {
        $this->cli = $cli;
        $this->logger = $logger;
        $this->settings = $settings;
        $this->url = $url;
        $this->basePath = $basePath;
        $this->iiifManifest = $iiifManifest;
        $this->xmlAltoSingle = $xmlAltoSingle;
    }

    /**
     * Create derivative of an item at the specified filepath.
     *
     * Unlike media, item as no field in database to store data. So the check is
     * done directly on files.
     *
     * @var array $dataMedia Media data contains required values.
     * @return bool|null Success or error. Null if no media or currently being
     * created.
     *
     * @todo Check filepath.
     */
    public function __invoke(string $type, string $filepath, ?ItemRepresentation $item = null, ?array $dataMedia = null): ?bool
    {
        if (!$item && !$dataMedia) {
            return false;
        }

        $dataMedia = $dataMedia ?: $this->dataMedia($item, $type);
        if (empty($dataMedia)) {
            return null;
        }

        return $this->prepareDerivative($type, $filepath, $dataMedia, $item);
    }

    protected function prepareDerivative(string $type, string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        if (!$this->ensureDirectory(dirname($filepath))) {
            $this->logger->err('Unable to create directory.'); // @translate
            return false;
        }

        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                $this->logger->err('Unable to remove existing file.'); // @translate
                return false;
            }
        }

        // Use a temp file to avoid concurrent processes (two users request it).
        $tempFilepath = $this->tempFilepath($filepath);

        // Check if another process is creating the file.
        if (file_exists($tempFilepath)) {
            $this->logger->warn('The derivative is currently beeing created.'); // @translate
            return null;
        }

        if ($type === 'alto') {
            $result = $this->prepareDerivativeAlto($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'iiif-2') {
            $result = $this->prepareDerivativeIiif($tempFilepath, $dataMedia, $item, 2);
        } elseif ($type === 'iiif-3') {
            $result = $this->prepareDerivativeIiif($tempFilepath, $dataMedia, $item, 3);
        } elseif ($type === 'pdf') {
            $result = $this->prepareDerivativePdf($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'pdf2xml') {
            $result = $this->prepareDerivativePdf2Xml($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'text') {
            $result = $this->prepareDerivativeTextExtracted($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'txt') {
            $result = $this->prepareDerivativeText($tempFilepath, $dataMedia, $item);
        } elseif (in_array($type, ['zip', 'zipm', 'zipo'])) {
            $result = $this->prepareDerivativeZip($type, $tempFilepath, $dataMedia, $item);
        } else {
            $result = null;
        }

        if ($result) {
            rename($tempFilepath, $filepath);
            @chmod($filepath, 0664);
        } elseif (file_exists($tempFilepath)) {
            @unlink($tempFilepath);
        }

        return $result;
    }

    protected function prepareDerivativeAlto(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        if (!$this->xmlAltoSingle) {
            $this->logger->err('To create xml alto, the module IiifSearch is required for now.'); // @translate
            return false;
        }

        $result = $this->xmlAltoSingle->__invoke($item, $filepath, $dataMedia);

        return (bool) $result;
    }

    protected function prepareDerivativeIiif(string $filepath, array $dataMedia, ?ItemRepresentation $item, $version): ?bool
    {
        if (!$this->iiifManifest) {
            $this->logger->err('To create iiif manifest, the module IiifServer is required for now.'); // @translate
            return false;
        }

        $manifest = $this->iiifManifest->__invoke($item, $version);

        if ($manifest) {
            if (!is_string($manifest)) {
                $manifest = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            $result = file_put_contents($filepath, $manifest);
            return (bool) $result;
        }

        return false;
    }

    protected function prepareDerivativePdf(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $files = array_column($dataMedia, 'filepath');

        // Avoid to modify quality to speed process.
        $command = sprintf('convert %s -quality 100 %s', implode(' ', array_map('escapeshellarg', $files)), escapeshellarg($filepath));
        $result = $this->cli->execute($command);

        return $result !== false;
    }

    protected function prepareDerivativePdf2Xml(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        if (count($dataMedia) > 1) {
            $this->logger->err('Extraction can be done on a single pdf attached to an item.'); // @translate
            return false;
        }

        $source = (reset($dataMedia))['filepath'];
        $command = sprintf('pdftohtml -i -c -hidden -nodrm -enc "UTF-8" -xml %1$s %2$s',
            escapeshellarg($source), escapeshellarg($filepath));

        $result = $this->cli->execute($command);

        return $result !== false;
    }

    protected function prepareDerivativeTextExtracted(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $output = '';

        $pageSeparator = <<<'TXT'
==============
Page %1$d/%2$d
==============


TXT;

        $total = count($dataMedia);
        $index = 0;
        foreach ($dataMedia as $dataMedia) {
            ++$index;
            $output .= sprintf($pageSeparator, $index, $total);
            $output .= $dataMedia['content'] . PHP_EOL;
        }

        // Fix for windows: remove end of line then add them to fix all cases.
        $output = str_replace(["\r\n", "\n\r","\n"], ["\n", "\n", "\r\n"], trim($output));

        $result = file_put_contents($filepath, $output);

        return (bool) $result;
    }

    protected function prepareDerivativeText(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $output = '';

        $pageSeparator = <<<'TXT'
==============
Page %1$d/%2$d
==============


TXT;

        $total = count($dataMedia);
        $index = 0;
        foreach ($dataMedia as $dataMedia) {
            ++$index;
            $output .= sprintf($pageSeparator, $index, $total);
            $output .= file_get_contents($dataMedia['filepath']) . PHP_EOL;
        }

        // Fix for windows: remove end of line then add them to fix all cases.
        $output = str_replace(["\r\n", "\n\r","\n"], ["\n", "\n", "\r\n"], trim($output));

        $result = file_put_contents($filepath, trim($output));

        return (bool) $result;
    }

    /**
     * @see \ContactUs\Job\ZipResources
     * @see \DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative
     */
    protected function prepareDerivativeZip(string $type, string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        if (!class_exists('ZipArchive')) {
            $this->logger->err('The php extension "php-zip" must be installed.'); // @translate
            return false;
        }

        // ZipArchive::OVERWRITE is available only in php 8.
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
            $this->logger->err('Unable to create the zip file.'); // @translate
            return false;
        }

        // Here, the site may not be available, so can't store item site url.
        $comment = $this->settings->get('installation_title') . ' [' . $this->url->__invoke('top', [], ['force_canonical' => true]) . ']';
        $zip->setArchiveComment($comment);

        // Store files: they are all already compressed (image, video, pdf...),
        // except some txt, xml and old docs.

        $index = 0;
        $filenames = [];
        foreach ($dataMedia as $file) {
            $zip->addFile($file['filepath']);
            // Light and quick compress text and xml.
            if ($file['maintype'] === 'text'
                || $file['mediatype'] === 'application/xml'
                || substr($file['mediatype'], -4) === '+xml'
            ) {
                $zip->setCompressionIndex($index, ZipArchive::CM_DEFLATE, 1);
            } else {
                $zip->setCompressionIndex($index, ZipArchive::CM_STORE);
            }

            // Use the source name, but check and rename for unique filename,
            // taking care of extension.
            $basepath = pathinfo($file['source'], PATHINFO_FILENAME);
            $extension = pathinfo($file['source'], PATHINFO_EXTENSION);
            $i = 0;
            do {
                $sourceBase = $basepath . ($i ? '.' . $i : '') . (strlen($extension) ? '.' . $extension : '');
                ++$i;
            } while (in_array($sourceBase, $filenames));
            $filenames[] = $sourceBase;
            $zip->renameName($file['filepath'], $sourceBase);
            ++$index;
        }

        $result = $zip->close();

        return $result;
    }

    protected function ensureDirectory(string $dirpath): bool
    {
        if (file_exists($dirpath)) {
            return true;
        }
        return mkdir($dirpath, 0775, true);
    }
}
