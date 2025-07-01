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
     * Initializes the ViewerDetector helper with a ViewerDetectorService instance.
     *
     * @param ViewerDetectorService $viewerDetector The service used for viewer detection operations.
     */
    public function __construct(ViewerDetectorService $viewerDetector)
    {
        $this->viewerDetector = $viewerDetector;
    }

    /**
     * Returns the ViewerDetector view helper instance for method chaining or direct access to its methods.
     *
     * @return self The ViewerDetector view helper instance.
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Returns the underlying ViewerDetectorService instance.
     *
     * @return ViewerDetectorService The service used for viewer detection operations.
     */
    public function getService()
    {
        return $this->viewerDetector;
    }

    /**
     * Generates the optimal video URL for a given media object and site slug, selecting the best viewer configuration.
     *
     * @param object $media The media object for which to generate the video URL.
     * @param string $siteSlug The slug identifying the site context.
     * @return string The optimal video URL.
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
     * Retrieves debug information about available video viewers.
     *
     * @return array An array containing diagnostic details about video viewers.
     */
    public function getDebugInfo()
    {
        return $this->viewerDetector->getViewerDebugInfo();
    }

    /**
     * Retrieves the video URL strategy for the specified media and site slug.
     *
     * @param object $media The media object for which to determine the strategy.
     * @param string $siteSlug The slug identifying the site context.
     * @return array An array containing information about the selected video URL strategy.
     */
    public function getVideoUrlStrategy($media, $siteSlug)
    {
        return $this->viewerDetector->getVideoUrlStrategy($media, $siteSlug);
    }

    /**
     * Returns an array of currently active video viewers.
     *
     * @return array List of active video viewers.
     */
    public function getActiveVideoViewers()
    {
        return $this->viewerDetector->getActiveVideoViewers();
    }

    /**
     * Returns the best available video viewer as determined by the underlying service.
     *
     * @return array|null The best video viewer information, or null if none is available.
     */
    public function getBestVideoViewer()
    {
        return $this->viewerDetector->getBestVideoViewer();
    }
}
