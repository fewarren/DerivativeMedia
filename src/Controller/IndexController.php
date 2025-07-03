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
     * SECURITY FIX: Secure file download with path traversal protection and Omeka file store integration
     */
    public function downloadFileAction()
    {
        try {
            // SECURITY FIX: Validate and sanitize all user inputs
            $folder = $this->validatePathComponent($this->params('folder'), 'folder');
            $id = $this->validatePathComponent($this->params('id'), 'id');
            $filename = $this->validateFilename($this->params('filename'));

            if (!$folder || !$id || !$filename) {
                $this->getResponse()->setStatusCode(400);
                return $this->getResponse();
            }

            // SECURITY FIX: Use Omeka's file store instead of direct filesystem access
            $fileStore = $this->getServiceLocator()->get('Omeka\File\Store');
            $basePath = $this->getServiceLocator()->get('Config')['file_store']['local']['base_path'] ?? '/var/www/omeka-s/files';

            // SECURITY FIX: Construct secure file path with validation
            $relativePath = $this->buildSecureFilePath($folder, $id, $filename);
            $fullPath = $basePath . '/' . $relativePath;

            // SECURITY FIX: Validate the final path is within allowed directory
            if (!$this->isPathSafe($fullPath, $basePath)) {
                error_log("DerivativeMedia: Path traversal attempt blocked: $fullPath");
                $this->getResponse()->setStatusCode(403);
                return $this->getResponse();
            }

            // Check if file exists using file store
            if (!$fileStore->fileExists($relativePath)) {
                $this->getResponse()->setStatusCode(404);
                return $this->getResponse();
            }

            // Get file content through file store
            $fileContent = $fileStore->getFileContents($relativePath);
            if ($fileContent === false) {
                $this->getResponse()->setStatusCode(500);
                return $this->getResponse();
            }

            // Determine media type safely
            $mediaType = $this->getSecureMediaType($filename);

            // Send the file securely
            return $this->sendFileContent($fileContent, $mediaType, $filename, 'inline');

        } catch (\Exception $e) {
            error_log("DerivativeMedia: Download error: " . $e->getMessage());
            $this->getResponse()->setStatusCode(500);
            return $this->getResponse();
        }
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

    /**
     * Debug action to display viewer detection information
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
     * Video player action that respects the preferred viewer setting
     * This creates a dedicated video player page with only the preferred viewer
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

    /**
     * SECURITY: Validate path component to prevent path traversal attacks
     *
     * @param string|null $component The path component to validate
     * @param string $type The type of component (for error messages)
     * @return string|null Sanitized component or null if invalid
     */
    private function validatePathComponent(?string $component, string $type): ?string
    {
        if (empty($component)) {
            return null;
        }

        // SECURITY: Block path traversal attempts
        if (strpos($component, '..') !== false ||
            strpos($component, '/') !== false ||
            strpos($component, '\\') !== false ||
            strpos($component, "\0") !== false) {
            error_log("DerivativeMedia: Path traversal attempt in $type: $component");
            return null;
        }

        // SECURITY: Only allow alphanumeric, dash, underscore, and dot
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $component)) {
            error_log("DerivativeMedia: Invalid characters in $type: $component");
            return null;
        }

        // SECURITY: Limit length to prevent buffer overflow attacks
        if (strlen($component) > 255) {
            error_log("DerivativeMedia: Component too long in $type: " . strlen($component) . " chars");
            return null;
        }

        return $component;
    }

    /**
     * SECURITY: Validate filename to prevent malicious filenames
     *
     * @param string|null $filename The filename to validate
     * @return string|null Sanitized filename or null if invalid
     */
    private function validateFilename(?string $filename): ?string
    {
        if (empty($filename)) {
            return null;
        }

        // SECURITY: Block path traversal and dangerous characters
        if (strpos($filename, '..') !== false ||
            strpos($filename, '/') !== false ||
            strpos($filename, '\\') !== false ||
            strpos($filename, "\0") !== false) {
            error_log("DerivativeMedia: Path traversal attempt in filename: $filename");
            return null;
        }

        // SECURITY: Validate filename format (allow common file extensions)
        if (!preg_match('/^[a-zA-Z0-9._-]+\.[a-zA-Z0-9]+$/', $filename)) {
            error_log("DerivativeMedia: Invalid filename format: $filename");
            return null;
        }

        // SECURITY: Limit filename length
        if (strlen($filename) > 255) {
            error_log("DerivativeMedia: Filename too long: " . strlen($filename) . " chars");
            return null;
        }

        return $filename;
    }

    /**
     * SECURITY: Build secure file path with validation
     *
     * @param string $folder Validated folder component
     * @param string $id Validated ID component
     * @param string $filename Validated filename
     * @return string Secure relative file path
     */
    private function buildSecureFilePath(string $folder, string $id, string $filename): string
    {
        // SECURITY: Use explicit path construction to prevent injection
        return sprintf('%s/%s/%s', $folder, $id, $filename);
    }

    /**
     * SECURITY: Validate that the final path is within the allowed base directory
     *
     * @param string $fullPath The full path to validate
     * @param string $basePath The allowed base directory
     * @return bool True if path is safe, false if potential traversal
     */
    private function isPathSafe(string $fullPath, string $basePath): bool
    {
        // SECURITY: Resolve real paths to handle symlinks and relative paths
        $realFullPath = realpath($fullPath);
        $realBasePath = realpath($basePath);

        // If realpath fails, the path doesn't exist or is invalid
        if ($realFullPath === false || $realBasePath === false) {
            return false;
        }

        // SECURITY: Ensure the full path starts with the base path
        return strpos($realFullPath, $realBasePath) === 0;
    }

    /**
     * SECURITY: Get secure media type based on file extension
     *
     * @param string $filename The filename to analyze
     * @return string Safe media type
     */
    private function getSecureMediaType(string $filename): string
    {
        // SECURITY: Use whitelist of allowed file extensions and their media types
        $allowedTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
        ];

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // SECURITY: Return safe media type or default to octet-stream
        return $allowedTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * SECURITY: Send file content securely without direct filesystem access
     *
     * @param string $content File content
     * @param string $mediaType Media type
     * @param string $filename Filename for download
     * @param string $disposition Content disposition (inline/attachment)
     * @return \Laminas\Http\PhpEnvironment\Response
     */
    private function sendFileContent(string $content, string $mediaType, string $filename, string $disposition): \Laminas\Http\PhpEnvironment\Response
    {
        $response = $this->getResponse();

        // SECURITY: Sanitize filename for header
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        $headers = $response->getHeaders()
            ->addHeaderLine(sprintf('Content-Type: %s', $mediaType))
            ->addHeaderLine(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $safeFilename))
            ->addHeaderLine(sprintf('Content-Length: %s', strlen($content)))
            ->addHeaderLine('Content-Transfer-Encoding: binary')
            ->addHeaderLine('Cache-Control: private, max-age=3600')
            ->addHeaderLine('X-Content-Type-Options: nosniff')
            ->addHeaderLine('X-Frame-Options: DENY');

        $response->setContent($content);

        return $response;
    }
}
