<?php declare(strict_types=1);

namespace DerivativeMedia\Media\FileRenderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use DerivativeMedia\Service\DebugManager;

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
        // CONFIGURABLE LOGGING FIX: Use DebugManager instead of extensive error_log calls
        $debugManager = null;
        $operationId = 'video-render-' . uniqid();

        try {
            // Get DebugManager from view's service locator
            $serviceLocator = $view->getHelperPluginManager()->getServiceLocator();
            $debugManager = $serviceLocator->get('DerivativeMedia\Service\DebugManager');
        } catch (\Exception $e) {
            // DebugManager not available, continue without logging
            $debugManager = null;
        }

        if ($debugManager) {
            // RENDERER_TRACE: Log renderer call details (only when debugging enabled)
            $debugManager->logDebug("VideoRenderer::render() called for media ID: " . $media->id(), DebugManager::COMPONENT_RENDERER, $operationId);
            $debugManager->logDebug("Media type: " . $media->mediaType(), DebugManager::COMPONENT_RENDERER, $operationId);
            $debugManager->logDebug("Media filename: " . $media->filename(), DebugManager::COMPONENT_RENDERER, $operationId);
            $debugManager->logDebug("Options: " . json_encode($options), DebugManager::COMPONENT_RENDERER, $operationId);
            $debugManager->logDebug("View class: " . get_class($view), DebugManager::COMPONENT_RENDERER, $operationId);
            $debugManager->logInfo("CUSTOM RENDERER CALLED for media ID: " . $media->id(), DebugManager::COMPONENT_RENDERER, $operationId);
        }

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
