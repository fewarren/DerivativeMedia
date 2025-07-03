<?php
namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use DerivativeMedia\Service\ViewerDetector as ViewerDetectorService;

/**
 * View helper for accessing ViewerDetector service
 */
class ViewerDetector extends AbstractHelper
{
    /**
     * @var ViewerDetectorService
     */
    protected $viewerDetector;

    /**
     * Constructor
     */
    public function __construct(ViewerDetectorService $viewerDetector)
    {
        $this->viewerDetector = $viewerDetector;
    }

    /**
     * Return the ViewerDetector view helper (for chaining) or service (for direct access)
     *
     * @return ViewerDetectorService|self
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Get the underlying service
     *
     * @return ViewerDetectorService
     */
    public function getService()
    {
        return $this->viewerDetector;
    }

    /**
     * Generate the optimal URL for a video media based on active viewers
     *
     * @param object $media The media object
     * @param string $siteSlug The site slug
     * @return string The generated URL
     */
    public function generateVideoUrl($media, $siteSlug)
    {
        $view = $this->getView();
        $urlHelper = function($route, $params = [], $options = []) use ($view) {
            return $view->url($route, $params, $options);
        };

        return $this->viewerDetector->generateVideoUrl($media, $siteSlug, $urlHelper);
    }

    /**
     * Get debug information about viewers
     *
     * @return array
     */
    public function getDebugInfo()
    {
        return $this->viewerDetector->getViewerDebugInfo();
    }

    /**
     * Get video URL strategy for a media
     *
     * @param object $media The media object (RESERVED FOR FUTURE USE: media-specific URL logic)
     * @param string $siteSlug The site slug (RESERVED FOR FUTURE USE: site-specific URL logic)
     * @return array Strategy information
     */
    public function getVideoUrlStrategy($media, $siteSlug)
    {
        return $this->viewerDetector->getVideoUrlStrategy($media, $siteSlug);
    }

    /**
     * Get active video viewers
     *
     * @return array
     */
    public function getActiveVideoViewers()
    {
        return $this->viewerDetector->getActiveVideoViewers();
    }

    /**
     * Get best video viewer
     *
     * @return array|null
     */
    public function getBestVideoViewer()
    {
        return $this->viewerDetector->getBestVideoViewer();
    }
}
