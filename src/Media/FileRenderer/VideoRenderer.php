<?php declare(strict_types=1);

namespace DerivativeMedia\Media\FileRenderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;

/**
 * Simplified VideoRenderer based on core Omeka VideoRenderer
 * Uses the same approach but with our URL fixes applied
 */
class VideoRenderer implements RendererInterface
{
    const DEFAULT_OPTIONS = [
        'controls' => true,
    ];

    public function render(
        PhpRenderer $view,
        MediaRepresentation $media,
        array $options = []
    ) {
        // RENDERER_TRACE: Log renderer call details
        error_log("RENDERER_TRACE: VideoRenderer::render() called for media ID: " . $media->id());
        error_log("RENDERER_TRACE: Media type: " . $media->mediaType());
        error_log("RENDERER_TRACE: Media filename: " . $media->filename());
        error_log("RENDERER_TRACE: Options: " . json_encode($options));
        error_log("RENDERER_TRACE: View class: " . get_class($view));

        // CRITICAL DEBUG: Log that our custom VideoRenderer is being called
        error_log('DerivativeMedia VideoRenderer: CUSTOM RENDERER CALLED for media ID: ' . $media->id());

        $options = array_merge(self::DEFAULT_OPTIONS, $options);

        // DOWNLOAD-PROTECTED: Enhanced video renderer with download prevention
        // The URL fixes are already applied by our ServerUrl and File Store overrides
        $attrs = [];

        $attrs[] = sprintf('src="%s"', $view->escapeHtml($media->originalUrl()));

        if (isset($options['width'])) {
            $attrs[] = sprintf('width="%s"', $view->escapeHtml($options['width']));
        }
        if (isset($options['height'])) {
            $attrs[] = sprintf('height="%s"', $view->escapeHtml($options['height']));
        }
        if (isset($options['poster'])) {
            $attrs[] = sprintf('poster="%s"', $view->escapeHtml($options['poster']));
        }
        if (isset($options['autoplay']) && $options['autoplay']) {
            $attrs[] = 'autoplay';
        }
        if (isset($options['controls']) && $options['controls']) {
            $attrs[] = 'controls';
            // DOWNLOAD PREVENTION: Disable download button in video controls
            $attrs[] = 'controlsList="nodownload"';
        }
        if (isset($options['loop']) && $options['loop']) {
            $attrs[] = 'loop';
        }
        if (isset($options['muted']) && $options['muted']) {
            $attrs[] = 'muted';
        }
        if (isset($options['class']) && $options['class']) {
            $attrs[] = sprintf('class="%s"', $view->escapeHtml($options['class']));
        }
        if (isset($options['preload']) && $options['preload']) {
            $attrs[] = sprintf('preload="%s"', $view->escapeHtml($options['preload']));
        }

        // DOWNLOAD PREVENTION: Disable right-click context menu
        $attrs[] = 'oncontextmenu="return false"';

        // DOWNLOAD PREVENTION: Add additional security attributes
        $attrs[] = 'disablePictureInPicture';
        $attrs[] = 'disableRemotePlayback';

        // DOWNLOAD PREVENTION: Remove fallback download link and replace with non-downloadable message
        $fallbackMessage = sprintf(
            '<p style="margin: 10px 0; font-style: italic; color: #666;">%s</p>',
            $view->escapeHtml($view->translate('Your browser does not support HTML5 video.'))
        );

        return sprintf(
            '<video %s>%s</video>',
            implode(' ', $attrs),
            $fallbackMessage
        );
    }
}
