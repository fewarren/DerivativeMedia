<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Laminas\Filter\RealPath;
use Omeka\Entity\Media;

trait DerivativeMediaTrait
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Omeka\Stdlib\Cli
     */
    protected $cli;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\File\Store\StoreInterface
     */
    protected $store;

    /**
     * @var \Omeka\File\TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var bool
     */
    protected $hasFfmpeg;

    /**
     * @var bool
     */
    protected $hasGhostscript;

    /**
     * @var array
     */
    protected $converters;

    protected function initialize()
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/media/job_' . $this->job->getId());

        $plugins = $services->get('ControllerPluginManager');
        $checkFfmpeg = $plugins->get('checkFfmpeg');
        $checkGhostscript = $plugins->get('checkGhostscript');
        $this->hasFfmpeg = $checkFfmpeg();
        $this->hasGhostscript = $checkGhostscript();

        if (!$this->hasFfmpeg && !$this->hasGhostscript) {
            $message = new \Omeka\Stdlib\Message(
                'The command-line utility "ffmpeg" and/or "gs" (ghostscript) should be installed and should be available in the cli path to make derivatives.' // @translate
            );
            $this->logger->err($message);
            return;
        }

        $removeCommented = function ($v, $k) {
            return !empty($v) && mb_strlen(trim($k)) && mb_substr(trim($k), 0, 1) !== '#';
        };
        $settings = $services->get('Omeka\Settings');
        $this->converters['audio'] = array_filter($settings->get('derivativemedia_converters_audio', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        $this->converters['video'] = array_filter($settings->get('derivativemedia_converters_video', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        $this->converters['pdf'] = array_filter($settings->get('derivativemedia_converters_pdf', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        if (empty(array_filter($this->converters))) {
            return false;
        }

        // Security checks all converters one time.
        foreach ($this->converters as $type => $converters) {
            foreach ($converters as $pattern => $command) {
                $command = trim($command);
                $pattern = trim($pattern);

                // FIXME How to secure admin-defined command? Move to config file? Create an intermediate shell script? Currently, most important characters are forbidden already and righs are the web server ones.
                if (!mb_strlen($command)
                    || mb_strpos($command, 'sudo') !== false
                    || mb_strpos($command, '$') !== false
                    || mb_strpos($command, '<') !== false
                    || mb_strpos($command, '>') !== false
                    || mb_strpos($command, ';') !== false
                    || mb_strpos($command, '&') !== false
                    || mb_strpos($command, '|') !== false
                    || mb_strpos($command, '%') !== false
                    || mb_strpos($command, '"') !== false
                    || mb_strpos($command, '\\') !== false
                    || mb_strpos($command, '..') !== false
                ) {
                    $this->logger->err(
                        'The derivative command "{command}" for {type} contains forbidden characters [$<>;&|%"\\..].', // @translate
                        ['command' => $command, 'type' => $type]
                    );
                    return false;
                }

                if (!mb_strlen($pattern)
                    || mb_strpos($pattern, '/{filename}.') === false
                    || mb_substr($pattern, 0, 1) === '/'
                    || mb_strpos($pattern, '..') !== false
                ) {
                    $this->logger->err(
                        'The derivative pattern "{pattern}" for {type} does not create a real path.', // @translate
                        ['pattern' => $pattern, 'type' => $type]
                    );
                    return false;
                }
            }
        }

        // Note: ffmpeg supports urls as input and output.
        $this->store = $services->get('Omeka\File\Store');
        if (!($this->store instanceof \Omeka\File\Store\Local)) {
            $this->logger->err(
                'A local store is required to derivate media currently.' // @translate
            );
            return false;
        }

        $this->basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->cli = $services->get('Omeka\Cli');
        $this->tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $this->entityManager = $services->get('Omeka\EntityManager');

        return true;
    }

    protected function derivateMedia(Media $media): bool
    {
        static $errorFfmpeg = false;
        static $errorGhostscript = false;

        $mediaType = (string) $media->getMediaType();
        $mainMediaType = strtok($mediaType, '/');
        $commonType = in_array($mainMediaType, ['audio', 'video'])
            ? $mainMediaType
            : ($mediaType === 'application/pdf' ? 'pdf' : null);

        if (empty($this->converters[$commonType])) {
            return false;
        }

        if (in_array($commonType, ['audio', 'video']) && !$this->hasFfmpeg) {
            if (!$errorFfmpeg) {
                $errorFfmpeg = true;
                $message = new \Omeka\Stdlib\Message(
                    'The command-line utility "ffmpeg" should be installed and should be available in the cli path to make derivatives.' // @translate
                );
                $this->logger->err($message);
            }
            return false;
        }

        if ($commonType === 'pdf' && !$this->hasGhostscript) {
            if (!$errorGhostscript) {
                $errorGhostscript = true;
                $message = new \Omeka\Stdlib\Message(
                    'The command-line utility "gs" (ghostscript) should be installed and should be available in the cli path to make derivatives.' // @translate
                );
                $this->logger->err($message);
            }
            return false;
        }

        $filename = $media->getFilename();
        $sourcePath = $this->basePath . '/original/' . $filename;

        if (!file_exists($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file does not exist ({filename})', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return false;
        }

        if (!is_readable($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file is not readable ({filename}).', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return false;
        }

        $realpath = new RealPath(false);

        $storageId = $media->getStorageId();
        foreach ($this->converters[$commonType] as $pattern => $command) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Media #{media_id}: Process stopped.', // @translate
                    ['media_id' => $media->getId()]
                );
                return false;
            }

            $command = trim($command);
            $pattern = trim($pattern);

            $folder = mb_substr($pattern, 0, mb_strpos($pattern, '/{filename}.'));
            $basename = str_replace('{filename}', $storageId, mb_substr($pattern, mb_strpos($pattern, '/{filename}.') + 1));
            $storageName = $folder . '/' . $basename;
            $derivativePath = $this->basePath . '/' . $storageName;

            if ($folder === 'original') {
                $this->logger->err(
                    'Media #{media_id}: the output cannot be the original folder.', // @translate
                    ['media_id' => $media->getId(), 'pattern' => $pattern]
                );
                return false;
            }

            // Another security check.
            if ($derivativePath !== $realpath->filter($derivativePath)) {
                $this->logger->err(
                    'Media #{media_id}: the derivative pattern "{pattern}" does not create a real path.', // @translate
                    ['media_id' => $media->getId(), 'pattern' => $pattern]
                );
                return false;
            }

            if (file_exists($derivativePath) && !is_writeable($derivativePath)) {
                $this->logger->warn(
                    'Media #{media_id}: derivative media is not writeable ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                continue;
            }

            // The path can contain a directory (module Archive repertory).
            // TODO To be removed: this is managed by the store anyway.
            $dirpath = dirname($derivativePath);
            if (file_exists($dirpath)) {
                if (!is_dir($dirpath) || !is_writeable($dirpath)) {
                    $this->logger->warn(
                        'Media #{media_id}: derivative media is not writeable ({filename}).', // @translate
                        ['media_id' => $media->getId(), 'filename' => $storageName]
                    );
                    continue;
                }
            } else {
                $result = @mkdir($dirpath, 0755, true);
                if (!$result) {
                    $this->logger->err(
                        'Media #{media_id}: derivative media is not writeable ({filename}).', // @translate
                        ['media_id' => $media->getId(), 'filename' => $storageName]
                    );
                    continue;
                }
            }

            $mediaData = $media->getData();

            // Remove existing file in order to keep database sync in all cases.
            if ($folder !== 'original'
                && ($fileExists = file_exists($derivativePath)
                    || (!empty($mediaData) && !empty($mediaData['derivative']) && array_key_exists($folder, $mediaData['derivative'])
                ))
            ) {
                if ($fileExists) {
                    $this->store->delete($storageName);
                }
                $this->storeMetadata($media, $folder, null, null);
                $this->entityManager->persist($media);
                $this->entityManager->flush();
                $this->logger->info(
                    'Media #{media_id}: existing derivative media removed ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
            }

            $this->logger->info(
                'Media #{media_id}: creating derivative media "{filename}".', // @translate
                ['media_id' => $media->getId(), 'filename' => $storageName]
            );

            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath() . '.' . pathinfo($basename, PATHINFO_EXTENSION);
            $tempFile->delete();
            $tempFile->setTempPath($tempPath);

            $command = $commonType === 'pdf'
                ? sprintf('gs -sDEVICE=pdfwrite -dNOPAUSE -dQUIET -dBATCH %1$s -o %2$s %3$s', $command, escapeshellarg($tempPath), escapeshellarg($sourcePath))
                : sprintf('ffmpeg -i %1$s %2$s %3$s', escapeshellarg($sourcePath), $command, escapeshellarg($tempPath));

            $output = $this->cli->execute($command);

            // Errors are already logged only with proc_open(), not exec().
            if (false === $output) {
                $this->logger->err(
                    'Media #{media_id}: derivative media cannot be created ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            if (strlen($output)) {
                $this->logger->info(
                    'Media #{media_id}: Output results for "{filename}":
{output}', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName, 'output' => $output]
                );
            }

            if (!file_exists($tempPath) || !filesize($tempPath)) {
                $this->logger->err(
                    'Media #{media_id}: derivative media is empty ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            $mediaType = $tempFile->getMediaType();
            if ((in_array($commonType, ['audio', 'video']) && !in_array(strtok($mediaType, '/'), ['audio', 'video']))
                || ($commonType === 'pdf' && $mediaType !== 'application/pdf')
            ) {
                $this->logger->err(
                    'Media #{media_id}: derivative media is not audio, video, or pdf, but "{mediatype}" ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'mediatype' => $mediaType, 'filename' => $storageName]
                );
                $tempFile->delete();
                return false;
            }

            try {
                $this->store->put($tempPath, $storageName);
            } catch (\Omeka\File\Exception\RuntimeException $e) {
                $this->logger->err(
                    'Media #{media_id}: derivative media cannot be stored ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            $tempFile->delete();

            $this->storeMetadata($media, $folder, $basename, $mediaType);

            $this->entityManager->persist($media);
            $this->entityManager->flush();

            $this->logger->info(
                'Media #{media_id}: derivative media created ({filename}).', // @translate
                ['media_id' => $media->getId(), 'filename' => $storageName]
            );
        }

        unset($media);
        return true;
    }

    protected function checkFilesAndStoreMetadata(Media $media): bool
    {
        $mainMediaType = strtok((string) $media->getMediaType(), '/');
        if (empty($this->converters[$mainMediaType])) {
            return false;
        }

        $filename = $media->getFilename();
        $sourcePath = $this->basePath . '/original/' . $filename;

        if (!file_exists($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file does not exist ({filename})', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return false;
        }

        if (!is_readable($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file is not readable ({filename}).', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return false;
        }

        $realpath = new RealPath(false);

        $storageId = $media->getStorageId();
        foreach ($this->converters[$mainMediaType] as $pattern => $command) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Media #{media_id}: Process stopped.', // @translate
                    ['media_id' => $media->getId()]
                );
                return false;
            }

            $command = trim($command);
            $pattern = trim($pattern);

            $folder = mb_substr($pattern, 0, mb_strpos($pattern, '/{filename}.'));
            $basename = str_replace('{filename}', $storageId, mb_substr($pattern, mb_strpos($pattern, '/{filename}.') + 1));
            $storageName = $folder . '/' . $basename;
            $derivativePath = $this->basePath . '/' . $storageName;

            if ($folder === 'original') {
                $this->logger->err(
                    'Media #{media_id}: the output cannot be the original folder.', // @translate
                    ['media_id' => $media->getId(), 'pattern' => $pattern]
                );
                return false;
            }

            // Another security check.
            if ($derivativePath !== $realpath->filter($derivativePath)) {
                $this->logger->err(
                    'Media #{media_id}: the derivative pattern "{pattern}" does not create a real path.', // @translate
                    ['media_id' => $media->getId(), 'pattern' => $pattern]
                );
                return false;
            }

            if (!file_exists($derivativePath)) {
                $this->storeMetadata($media, $folder, null, null);
                $this->entityManager->persist($media);
                $this->entityManager->flush();
                continue;
            }

            if (!is_readable($derivativePath)) {
                $this->storeMetadata($media, $folder, null, null);
                $this->entityManager->persist($media);
                $this->entityManager->flush();
                $this->logger->err(
                    'Media #{media_id}: the derivative file is not readable ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                continue;
            }

            if (!filesize($derivativePath)) {
                $this->storeMetadata($media, $folder, null, null);
                $this->entityManager->persist($media);
                $this->entityManager->flush();
                $this->logger->err(
                    'Media #{media_id}: the derivative file is empty ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                continue;
            }

            // Use temp file factory only to get media-type. The file is kept.
            $tempFile = $this->tempFileFactory->build();
            $tempFile->delete();
            $tempFile->setTempPath($derivativePath);
            $mediaType = $tempFile->getMediaType();

            if (!in_array(strtok($mediaType, '/'), ['audio', 'video']) && $mediaType !== 'application/pdf') {
                $this->storeMetadata($media, $folder, null, null);
                $this->entityManager->persist($media);
                $this->entityManager->flush();
                $this->logger->err(
                    'Media #{media_id}: derivative media is not audio, video, or pdf, but "{mediatype}" ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'mediatype' => $mediaType, 'filename' => $storageName]
                );
                continue;
            }

            $this->storeMetadata($media, $folder, $basename, $mediaType);
            $this->entityManager->persist($media);
            $this->entityManager->flush();

            $this->logger->info(
                'Media #{media_id}: derivative media file metadata stored ({filename}).', // @translate
                ['media_id' => $media->getId(), 'filename' => $storageName]
            );
        }

        unset($media);
        return true;
    }

    /**
     * Store or remove data about a derivative file (no flush).
     *
     * @todo Store size for performance and hash for security. Or create a new table?
     */
    protected function storeMetadata(Media $media, string $folder, ?string $basename = null, ?string $mediaType = null)
    {
        // Prepare media data.
        $mediaData = $media->getData();

        if (empty($mediaData)) {
            $mediaData = ['derivative' => []];
        } elseif (!isset($mediaData['derivative'])) {
            $mediaData['derivative'] = [];
        }

        if (empty($basename) || empty($mediaType)) {
            unset($mediaData['derivative'][$folder]);
        } else {
            $mediaData['derivative'][$folder]['filename'] = $basename;
            $mediaData['derivative'][$folder]['type'] = $mediaType;
        }

        $media->setData($mediaData);

        return $this;
    }

    protected function isManaged(Media $media)
    {
        $mediaType = $media->getMediaType();
        return $mediaType
            && $media->hasOriginal()
            && $media->getRenderer() === 'file'
            && (
                in_array(strtok($mediaType, '/'), ['audio', 'video'])
                || $mediaType === 'application/pdf'
            );
    }
}
