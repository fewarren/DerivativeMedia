<?php declare(strict_types=1);

namespace DerivativeMedia\Controller;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Laminas\Http\Response;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class IndexController extends \Omeka\Controller\IndexController
{
    use TraitDerivative;

    /**
     * Handles requests for derivative media files, validating type and availability, and delivers the file or returns an appropriate error response.
     *
     * Validates the requested derivative type and checks if it is enabled. Ensures the resource is an item and that the derivative file exists and is ready. If not ready, may attempt to create the derivative immediately or dispatch a background job, depending on the derivative mode and settings. Returns JSON error or status messages with relevant HTTP status codes if the derivative is unavailable or cannot be prepared. If the derivative is ready, sends the file as an HTTP response attachment.
     *
     * @return \Laminas\View\Model\JsonModel|\Laminas\Http\PhpEnvironment\Response Returns a JSON error/status response or the file as an HTTP response.
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
     * Handles file download requests for media files expected by VideoRenderer and AudioRenderer.
     *
     * Constructs the file path from URL parameters, verifies file existence and readability, determines the MIME type, and sends the file inline to the client. Returns a 404 response if the file is not found or unreadable.
     *
     * @return \Laminas\Http\PhpEnvironment\Response The HTTP response containing the file or a 404 status.
     */
    public function downloadFileAction()
    {
        $folder = $this->params('folder');
        $id = $this->params('id');
        $filename = $this->params('filename');

        // Construct the file path
        $filepath = sprintf('/var/www/omeka-s/files/%s/%s/%s', $folder, $id, $filename);

        // Check if file exists
        if (!file_exists($filepath) || !is_readable($filepath)) {
            $this->getResponse()->setStatusCode(404);
            return $this->getResponse();
        }

        // Determine media type
        $mediaType = mime_content_type($filepath) ?: 'application/octet-stream';

        // Send the file
        return $this->sendFile($filepath, $mediaType, $filename, 'inline', true);
    }

    /**
     * Sends a file to the client with appropriate HTTP headers, supporting inline or attachment disposition, optional caching, and HTTP range requests for partial content delivery.
     *
     * Handles large files efficiently by clearing output buffers and streaming content, and sets headers for content type, disposition, length, transfer encoding, and caching as needed. Supports partial content delivery for media streaming via HTTP range requests.
     *
     * @param string $filepath The absolute path to the file to be sent.
     * @param string $mediaType The MIME type of the file.
     * @param string|null $filename Optional filename to use in the Content-Disposition header; defaults to the file's basename.
     * @param string|null $dispositionMode Whether to send the file 'inline' or as an 'attachment'; defaults to 'inline'.
     * @param bool|null $cache Whether to enable client-side caching for 30 days; defaults to false.
     * @return \Laminas\Http\PhpEnvironment\Response The HTTP response object after sending the file.
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

    /**
     * Returns debugging information about video viewer detection and configuration.
     *
     * Provides details on active video viewers, the best viewer selection, and viewer detection debug info. Also includes a sample video media item and its URL strategy if available, along with a timestamp.
     *
     * @return \Laminas\View\Model\JsonModel JSON response containing viewer detection debug data.
     */
    public function debugAction()
    {
        $serviceLocator = $this->getEvent()->getApplication()->getServiceManager();
        $viewerDetector = $serviceLocator->get('DerivativeMedia\Service\ViewerDetector');

        $debugInfo = $viewerDetector->getViewerDebugInfo();
        $activeViewers = $viewerDetector->getActiveVideoViewers();
        $bestViewer = $viewerDetector->getBestVideoViewer();

        // Get sample media for URL strategy testing
        $sampleMedia = null;
        $sampleStrategy = null;
        try {
            $mediaList = $this->api()->search('media', ['media_type' => 'video/mp4', 'limit' => 1])->getContent();
            if (!empty($mediaList)) {
                $sampleMedia = $mediaList[0];
                $sampleStrategy = $viewerDetector->getVideoUrlStrategy($sampleMedia, 'browsingarchive');
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return new JsonModel([
            'debug_info' => $debugInfo,
            'active_viewers' => $activeViewers,
            'best_viewer' => $bestViewer,
            'sample_media' => $sampleMedia ? [
                'id' => $sampleMedia->id(),
                'title' => $sampleMedia->displayTitle(),
                'media_type' => $sampleMedia->mediaType(),
                'strategy' => $sampleStrategy
            ] : null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Renders a dedicated video player page for a media item using the preferred viewer.
     *
     * Retrieves the specified media and site by their identifiers, determines the best available video viewer, and returns a view model for the video player page. Returns a 404 response if the media or site is not found.
     *
     * @return \Laminas\View\Model\ViewModel The view model for the video player page.
     */
    public function videoPlayerAction()
    {
        $siteSlug = $this->params('site-slug');
        $mediaId = $this->params('media-id');

        // Get the media object
        try {
            $media = $this->api()->read('media', ['id' => $mediaId])->getContent();
        } catch (\Exception $e) {
            $this->getResponse()->setStatusCode(404);
            return $this->notFoundAction();
        }

        // Get the site object
        try {
            $site = $this->api()->read('sites', ['slug' => $siteSlug])->getContent();
        } catch (\Exception $e) {
            $this->getResponse()->setStatusCode(404);
            return $this->notFoundAction();
        }

        // Get viewer detection service using modern Omeka S approach
        $serviceLocator = $this->getEvent()->getApplication()->getServiceManager();
        $viewerDetector = $serviceLocator->get('DerivativeMedia\Service\ViewerDetector');

        // Get the best viewer for this video
        $bestViewer = $viewerDetector->getBestVideoViewer();
        $debugInfo = $viewerDetector->getViewerDebugInfo();

        // Return ViewModel with variables and explicit template (modern Omeka S approach)
        $viewModel = new ViewModel([
            'media' => $media,
            'site' => $site,
            'bestViewer' => $bestViewer,
            'debugInfo' => $debugInfo,
            'siteSlug' => $siteSlug,
        ]);

        // Explicitly set template to override automatic resolution
        $viewModel->setTemplate('derivative-media/video-player');

        return $viewModel;
    }
}
