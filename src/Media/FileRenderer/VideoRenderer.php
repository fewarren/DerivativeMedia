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

    /**
     * Renders an HTML5 video element for the given media with configurable options and optional download prevention.
     *
     * Generates a `<video>` tag using the original media URL, applying attributes such as width, height, poster, autoplay, controls, loop, muted, class, and preload based on the provided options. If download prevention is enabled via system settings, the renderer disables the download button, right-click context menu, picture-in-picture, and remote playback. The fallback content is either a compatibility message or a download link, depending on the download prevention setting.
     *
     * @param PhpRenderer $view The view renderer instance.
     * @param MediaRepresentation $media The media item to render as a video.
     * @param array $options Optional rendering options (e.g., width, height, controls, poster, autoplay, loop, muted, class, preload).
     * @return string The complete HTML for the video element.
     */
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

        // Check if download prevention is enabled
        $settings = $view->getHelperPluginManager()->getServiceLocator()->get('Omeka\Settings');
        $disableDownloads = $settings->get('derivativemedia_disable_video_downloads', false);

        // Enhanced video renderer with optional download prevention
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

            // CONFIGURABLE DOWNLOAD PREVENTION: Disable download button in video controls
            if ($disableDownloads) {
                $attrs[] = 'controlsList="nodownload"';
            }
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

        // CONFIGURABLE DOWNLOAD PREVENTION: Additional security attributes
        if ($disableDownloads) {
            // Disable right-click context menu
            $attrs[] = 'oncontextmenu="return false"';

            // Add additional security attributes
            $attrs[] = 'disablePictureInPicture';
            $attrs[] = 'disableRemotePlayback';
        }

        // CONFIGURABLE FALLBACK: Choose between download link or simple message
        if ($disableDownloads) {
            // No download link - just compatibility message
            $fallbackContent = sprintf(
                '<p style="margin: 10px 0; font-style: italic; color: #666;">%s</p>',
                $view->escapeHtml($view->translate('Your browser does not support HTML5 video.'))
            );
        } else {
            // Standard fallback with download link
            $fallbackContent = $view->hyperlink($media->filename(), $media->originalUrl());
        }

        return sprintf(
            '<video %s>%s</video>',
            implode(' ', $attrs),
            $fallbackContent
        );
    }
}
