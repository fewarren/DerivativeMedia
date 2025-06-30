<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Url;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

class DerivativeList extends AbstractHelper
{
    use TraitDerivative;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUrlFiles;

    /**
     * @var array
     */
    protected $enabled;

    /**
     * @var array
     */
    protected $maxSizeLive;

    /**
     * @var \Laminas\View\Helper\Url
     */
    protected $url;

    public function __construct(
        string $basePath,
        string $baseUrlFiles,
        array $enabled,
        int $maxSizeLive,
        Url $url
    ) {
        $this->basePath = $basePath;
        $this->baseUrlFiles= $baseUrlFiles;
        $this->enabled = $enabled;
        $this->maxSizeLive = $maxSizeLive;
        $this->url = $url;
    }

    /**
     * List available derivatives of an item.
     *
     * Some derivative can be created dynamically.
     *
     * @param AbstractResourceEntityRepresentation|null $resource
     * @param array $options Managed options:
     * - type (string): limit output to a specifc type of data (item) or a
     *   data folder (media).
     * - include_media (bool): include media data for an item.
     * For compatibility with deprecated old version, $options can be a string
     * for "type".
     * @return array Array of derivative types and data. If option "include_media"
     * is set, a upper level is added, so output is an associative array with
     * the resource id (item and medias) as key and an array of derivative types
     * as value:
     * - mode (string): build file as "static", "dynamic", "live" or "dynamic_live".
     * - feasible (boolean): if item can have this type of derivative.
     * - in_progress (boolean): if the derivative is currently building.
     * - ready (boolean): derivative file is available.
     * - mediatype (string): the media type.
     * - extension (string): the extension.
     * - size (null|integer): real size or estimation.
     * - file (string): relative filepath of the derivative.
     * - url (string): url of the derivative file.
     * For media, derivatives data are stored in data(), so mode is always
     * static, so feasible and ready are true, and there is no progress. So the
     * type option is not used, but
     */
    public function __invoke(?AbstractResourceEntityRepresentation $resource, $options = []): array
    {
        if (!$resource) {
            return [];
        }

        $result = [];

        if (is_string($options)) {
            $options = ['type' => $options];
        }

        $options += [
            'type' => null,
            'include_media' => false,
        ];

        if ($options['type']
            && (!isset(Module::DERIVATIVES[$options['type']]) || !in_array($options['type'], $this->enabled))
        ) {
            return [];
        }

        if ($resource instanceof MediaRepresentation) {
            return $this->listDerivativeMedia($resource, $options['type']);
        } elseif (!$resource instanceof ItemRepresentation) {
            return [];
        }

        $result[$resource->id()] = $this->listDerivativeItem($resource, $options['type']);
        if (!$options['include_media']) {
            return reset($result);
        }

        foreach ($resource->media() as $media) {
            $result[$media->id()] = $this->listDerivativeMedia($media, $options['type']);
        }

        return $result;
    }

    /**
     * Get the list of derivative types available for an item.
     *
     * Unlike media, item jas no field in database to store data. So the check
     * is done directly on files. Furthermore, files may be created dynamically.
     */
    protected function listDerivativeItem(ItemRepresentation $item, ?string $type = null): array
    {
        $result = [];

        $itemId = $item->id();

        foreach ($type ? [$type] : $this->enabled as $type) {
            if (!isset(Module::DERIVATIVES[$type])
                || Module::DERIVATIVES[$type]['level'] === 'media'
            ) {
                continue;
            }

            // Don't make the full filepath available in view.
            $filePath = $this->itemFilepath($item, $type);
            $file = mb_substr($filePath, mb_strlen($this->basePath) + 1);

            $tempFilepath = $this->tempFilepath($filePath);

            $size = null;

            $ready = file_exists($filePath) && is_readable($filePath) && ($size = filesize($filePath));
            $isInProgress = !$ready
                && file_exists($tempFilepath) && is_readable($tempFilepath) && filesize($tempFilepath);

            // Check if a derivative may be created.
            $feasible = $ready || $isInProgress;
            if (!$feasible) {
                $dataMedia = $this->dataMedia($item, $type);
                $feasible = !empty($dataMedia);
                $size = empty(Module::DERIVATIVES[$type]['size'])
                    ? null
                    : array_sum(array_column($dataMedia, 'size'));
            }

            // TODO Output zip file directly as stream to avoid limit issue.

            if (Module::DERIVATIVES[$type]['mode'] === 'dynamic_live') {
                $derivativeMode = !$size || $size >= $this->maxSizeLive
                    ? 'dynamic'
                    : 'live';
            } else {
                $derivativeMode = Module::DERIVATIVES[$type]['mode'];
            }

            $result[$type] = [
                'mode' => $derivativeMode,
                'feasible' => $feasible,
                'in_progress' => $isInProgress,
                'ready' => $ready,
                'mediatype' => Module::DERIVATIVES[$type]['mediatype'],
                'extension' => Module::DERIVATIVES[$type]['extension'],
                'size' => $size,
                'file' => $file,
                'url' => $feasible
                    ? $this->url->__invoke('derivative', ['type' => $type, 'id' => $itemId])
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Get the list of derivative types available for a media.
     *
     * Unlike item, media data are stored in its data, so output directly them.
     */
    protected function listDerivativeMedia(MediaRepresentation $media, ?string $type = null): array
    {
        $result = [];

        /**
         * @var array $derivatives Contains the folder as key ("mp3", "ogg",
         * "mp4", "webm", "pdfs", "pdfe", etc.) and an array with "filename" and
         * "type" (media type).
         */
        $derivatives = $media->mediaData()['derivative'] ?? [];

        if (!$derivatives) {
            return [];
        }

        if ($type && (
            !in_array($type, $this->enabled)
            || !isset(Module::DERIVATIVES[$type])
            || Module::DERIVATIVES[$type]['level'] !== 'media'
        )) {
            return [];
        }

        // No need to check if the folder belongs to the type: it should be
        // right in normal cases and the aim is to display existing files.
        // TODO Keep only audio/video/pdf files requested.

        foreach ($derivatives as $folder => $derivative) {
            $file = $folder . '/' . $derivative['filename'];
            $result[$folder] = [
                'mode' => 'static',
                'feasible' => true,
                'in_progress' => false,
                'ready' => true,
                'mediatype' => $derivative['type'],
                'extension' => pathinfo($derivative['filename'], PATHINFO_EXTENSION),
                'size' => $derivative['size'] ?? filesize($this->basePath . '/' . $file),
                'file' => $file,
                'url' => $this->baseUrlFiles . '/' . $file,
            ];
        }

        return $result;
    }
}
