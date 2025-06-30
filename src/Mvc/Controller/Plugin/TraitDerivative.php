<?php declare(strict_types=1);

namespace DerivativeMedia\Mvc\Controller\Plugin;

use DerivativeMedia\Module;
use Omeka\Api\Representation\ItemRepresentation;

trait TraitDerivative
{
    /**
     * @var string
     */
    protected $basePath;

    protected function dataMedia(ItemRepresentation $item, string $type): array
    {
        if (!isset(Module::DERIVATIVES[$type])) {
            return [];
        }

        if (empty($this->basePath)) {
            $this->basePath = $item->getServiceLocator()->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        }

        $dataMedia = [];
        foreach ($item->media() as $media) {
            if (!$media->hasOriginal() || !$media->size()) {
                continue;
            }

            $filename = $media->filename();
            $filepath = $this->basePath . '/original/' . $filename;
            $ready = file_exists($filepath) && is_readable($filepath) && filesize($filepath);
            if (!$ready) {
                continue;
            }

            $mediaType = $media->mediaType();
            if (!$mediaType) {
                continue;
            }

            $mediaId = $media->id();
            $mainType = strtok($mediaType, '/');
            $extension = $media->extension();
            if ($type === 'alto'
                // Manage altowithout content.
                && ($mediaType !== 'application/alto+xml' || ($extension === 'xml' && !in_array($mediaType, ['application/x-empty', 'application/alto+xml'])))
            ) {
                continue;
            } elseif ($type === 'iiif-2' && !in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            } elseif ($type === 'iiif-3' && !in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            } elseif ($type === 'pdf'
                && ($mainType !== 'image')
                // TODO Get image and pdf to manage the case there are pdf too.
                // && ($mainType !== 'image' || $mediaType !== 'application/pdf')
            ) {
                continue;
            } elseif ($type === 'pdf2xml' && ($mediaType !== 'application/pdf')) {
                continue;
            } elseif ($type === 'txt'
                // Manage empty text file without content.
                && ($mediaType !== 'text/plain' || ($extension === 'txt' && !in_array($mediaType, ['application/x-empty', 'text/plain'])))
            ) {
                continue;
            } elseif ($type === 'text') {
                // This is an exception.
                if (($extracted = (string) $media->value('extracttext:extracted_text')) && strlen($extracted)) {
                    $dataMedia[$mediaId] = [
                        'id' => $mediaId,
                        'source' => null,
                        'filename' => null,
                        'filepath' => null,
                        'mediatype' => null,
                        'maintype' => null,
                        'extension' => null,
                        // Use byte size, because it is the size to download.
                        'size' => strlen($extracted),
                        'content' => $extracted,
                    ];
                }
                continue;
            } elseif ($type === 'zipm' && !in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            } elseif ($type === 'zipo' && in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            }
            $dataMedia[$mediaId] = [
                'id' => $mediaId,
                'source' => $media->source(),
                'filename' => $filename,
                'filepath' => $filepath,
                'mediatype' => $mediaType,
                'maintype' => $mainType,
                'extension' => $extension,
                'size' => $media->size(),
            ];
        }

        return $dataMedia;
    }

    protected function itemFilepath(ItemRepresentation $item, string $type): ?string
    {
        if (!isset(Module::DERIVATIVES[$type])) {
            return null;
        }

        if (empty($this->basePath)) {
            $this->basePath = $item->getServiceLocator()->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        }

        return $this->basePath
            . '/' . Module::DERIVATIVES[$type]['dir']
            . '/' . $item->id()
            . '.' . Module::DERIVATIVES[$type]['extension'];
    }

    protected function tempFilepath(string $filepath): string
    {
        // Keep the original extension to manage tools like convert.
        // Normally, all files have an extension.

        $extension = pathinfo($filepath, PATHINFO_EXTENSION) ?? '';
        return strlen($extension)
            ? mb_substr($filepath, 0, - strlen($extension) - 1) . '.tmp' . '.' . $extension
            : $filepath . '.tmp';
    }
}
