<?php declare(strict_types=1);

namespace DerivativeMedia\Controller;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Laminas\Http\Response;
use Laminas\View\Model\JsonModel;

class IndexController extends \Omeka\Controller\IndexController
{
    use TraitDerivative;

    /**
     * @todo Manage other storage type. See module Access.
     * @todo Some formats don't really need storage (text…), so make them truly dynamic.
     *
     * @todo Dynamic files cannot be stored in media data because of rights.
     *
     * {@inheritDoc}
     * @see \Omeka\Controller\IndexController::indexAction()
     */
    public function indexAction()
    {
        $type = $this->params('type');
        if (!isset(Module::DERIVATIVES[$type])
            || Module::DERIVATIVES[$type]['level'] === 'media'
        ) {
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('This type is not supported.'), // @translate
            ]);
        }

        $derivativeEnabled = $this->settings()->get('derivativemedia_enable', []);
        if (!in_array($type, $derivativeEnabled)) {
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('This type is not available.'), // @translate
            ]);
        }

        $id = $this->params('id');

        // Check if the resource is available and rights for the current user.

        // Automatically throw exception.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource*/
        $resource = $this->api()->read('resources', ['id' => $id])->getContent();

        // Check if resource contains files.
        if ($resource->resourceName() !== 'items') {
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('Resource is not an item.'), // @translate
            ]);
        }

        /** @var \Omeka\Api\Representation\ItemRepresentation $item */
        $item = $resource;

        $force = !empty($this->params()->fromQuery('force'));
        $prepare = !empty($this->params()->fromQuery('prepare'));

        // Quick check if the file exists when needed.
        $filepath = $this->itemFilepath($item, $type);

        $ready = !$force
            && file_exists($filepath) && is_readable($filepath) && filesize($filepath);

        // In case a user reclicks the link.
        if ($prepare && $ready) {
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
            return new JsonModel([
                'status' => 'fail',
                'data' => [
                    'id' => $this->translate('This derivative is ready. Reload the page.'), // @translate
                ],
            ]);
        }

        if (!$ready) {
            if (Module::DERIVATIVES[$type]['mode'] === 'static') {
                $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'error',
                    'message' => $this->translate('This derivative is not ready. Ask the webmaster for it.'), // @translate
                ]);
            }

            $dataMedia = $this->dataMedia($item, $type);
            if (!$dataMedia) {
                $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'error',
                    'message' => $this->translate('This type of derivative file cannot be prepared for this item.'), // @translate
                ]);
            }

            if (!$prepare
                && (
                    Module::DERIVATIVES[$type]['mode'] === 'live'
                    || (Module::DERIVATIVES[$type]['mode'] === 'dynamic_live'
                        && Module::DERIVATIVES[$type]['size']
                        && Module::DERIVATIVES[$type]['size'] < (int) $this->settings()->get('derivativemedia_max_size_live', 30)
                    )
                )
            ) {
                $ready = $this->createDerivative($type, $filepath, $item, $dataMedia);
                if (!$ready) {
                    $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                    return new JsonModel([
                        'status' => 'error',
                        'message' => $this->translate('This derivative files of this item cannot be prepared.'), // @translate
                    ]);
                }
            } else {
                $args = [
                    'item_id' => $item->id(),
                    'type' => $type,
                    'data_media' => $dataMedia,
                ];
                /** @var \Omeka\Job\Dispatcher $dispatcher */
                $dispatcher = $this->jobDispatcher();
                $dispatcher->dispatch(\DerivativeMedia\Job\CreateDerivatives::class, $args);
                $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'fail',
                    'data' => [
                        'id' => $this->translate('This derivative is being created. Come back later.'), // @translate
                    ],
                ]);
            }
        }

        // Send the file.
        return $this->sendFile($filepath, Module::DERIVATIVES[$type]['mediatype'], basename($filepath), 'attachment', true);
    }

    /**
     * This is the 'file' action that is invoked when a user wants to download
     * the given file.
     *
     * @see \Access\Controller\AccessFileController::sendFile()
     * @see \DerivativeMedia\Controller\IndexController::sendFile()
     * @see \Statistics\Controller\DownloadController::sendFile()
     * and
     * @see \ImageServer\Controller\ImageController::fetchAction()
     */
    protected function sendFile(
        string $filepath,
        string $mediaType,
        ?string $filename = null,
        // "inline" or "attachment".
        // It is recommended to set attribute "download" to link tag "<a>".
        ?string $dispositionMode = 'inline',
        ?bool $cache = false
    ): \Laminas\Http\PhpEnvironment\Response {
        $filename = $filename ?: basename($filepath);
        $filesize = (int) filesize($filepath);

        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();

        // Write headers.
        $headers = $response->getHeaders()
            ->addHeaderLine(sprintf('Content-Type: %s', $mediaType))
            ->addHeaderLine(sprintf('Content-Disposition: %s; filename="%s"', $dispositionMode, $filename))
            ->addHeaderLine(sprintf('Content-Length: %s', $filesize))
            ->addHeaderLine('Content-Transfer-Encoding: binary');
        if ($cache) {
            // Use this to open files directly.
            // Cache for 30 days.
            $headers
                ->addHeaderLine('Cache-Control: private, max-age=2592000, post-check=2592000, pre-check=2592000')
                ->addHeaderLine(sprintf('Expires: %s', gmdate('D, d M Y H:i:s', time() + (30 * 24 * 60 * 60)) . ' GMT'));
        }

        $headers
            ->addHeaderLine('Accept-Ranges: bytes');

        // TODO Check for Apache XSendFile or Nginx: https://stackoverflow.com/questions/4022260/how-to-detect-x-accel-redirect-nginx-x-sendfile-apache-support-in-php
        // TODO Use Laminas stream response?
        // $response = new \Laminas\Http\Response\Stream();

        // Adapted from https://stackoverflow.com/questions/15797762/reading-mp4-files-with-php.
        $hasRange = !empty($_SERVER['HTTP_RANGE']);
        if ($hasRange) {
            // Start/End are pointers that are 0-based.
            $start = 0;
            $end = $filesize - 1;
            $matches = [];
            $result = preg_match('/bytes=\h*(?<start>\d+)-(?<end>\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches);
            if ($result) {
                $start = (int) $matches['start'];
                if (!empty($matches['end'])) {
                    $end = (int) $matches['end'];
                }
            }
            // Check valid range to avoid hack.
            $hasRange = ($start < $filesize && $end < $filesize && $start < $end)
                && ($start > 0 || $end < ($filesize - 1));
        }

        if ($hasRange) {
            // Set partial content.
            $response
                ->setStatusCode($response::STATUS_CODE_206);
            $headers
                ->addHeaderLine('Content-Length: ' . ($end - $start + 1))
                ->addHeaderLine("Content-Range: bytes $start-$end/$filesize");
        } else {
            $headers
                ->addHeaderLine('Content-Length: ' . $filesize);
        }

        // Fix deprecated warning in \Laminas\Http\PhpEnvironment\Response::sendHeaders() (l. 113).
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_DEPRECATED);

        // Send headers separately to handle large files.
        $response->sendHeaders();

        error_reporting($errorReporting);

        // Clears all active output buffers to avoid memory overflow.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }

        if ($hasRange) {
            $fp = @fopen($filepath, 'rb');
            $buffer = 1024 * 8;
            $pointer = $start;
            fseek($fp, $start, SEEK_SET);
            while (!feof($fp)
                && $pointer <= $end
                && connection_status() === CONNECTION_NORMAL
            ) {
                set_time_limit(0);
                echo fread($fp, min($buffer, $end - $pointer + 1));
                flush();
                $pointer += $buffer;
            }
            fclose($fp);
        } else {
            readfile($filepath);
        }

        // TODO Fix issue with session. See readme of module XmlViewer.
        ini_set('display_errors', '0');

        // Return response to avoid default view rendering and to manage events.
        return $response;
    }
}
