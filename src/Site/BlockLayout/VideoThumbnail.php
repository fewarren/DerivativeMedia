<?php declare(strict_types=1);

namespace DerivativeMedia\Site\BlockLayout;

use DerivativeMedia\Service\VideoThumbnailService;
use DerivativeMedia\Service\DebugManager;
use DerivativeMedia\Form\VideoThumbnailBlockForm;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Manager;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

class VideoThumbnail extends AbstractBlockLayout
{
    /**
     * @var VideoThumbnailService
     */
    protected $videoThumbnailService;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var Manager
     */
    protected $apiManager;

    /**
     * @var DebugManager
     */
    protected $debugManager;

    /**
     * Initializes the VideoThumbnail block layout with required services for video thumbnail processing, form management, API access, and debugging.
     */
    public function __construct(
        VideoThumbnailService $videoThumbnailService,
        FormElementManager $formElementManager,
        Manager $apiManager,
        DebugManager $debugManager
    ) {
        $this->videoThumbnailService = $videoThumbnailService;
        $this->formElementManager = $formElementManager;
        $this->apiManager = $apiManager;
        $this->debugManager = $debugManager;
    }

    /**
     * Returns the display label for the video thumbnail block layout.
     *
     * @return string The label "Video Thumbnail".
     */
    public function getLabel()
    {
        return 'Video Thumbnail'; // @translate
    }

    /**
     * Validates and sanitizes the 'media' field in the block data by retaining only numeric media IDs.
     *
     * Updates the block's data to ensure that the 'media' array contains only valid integer media IDs.
     *
     * @param \Omeka\Entity\SitePageBlock $block The block whose data is being hydrated.
     * @param ErrorStore $errorStore Used for error tracking during hydration.
     */
    public function onHydrate(\Omeka\Entity\SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        // Validate selected media
        if (isset($data['media']) && is_array($data['media'])) {
            $validMediaIds = [];
            foreach ($data['media'] as $mediaId) {
                if (is_numeric($mediaId)) {
                    $validMediaIds[] = (int) $mediaId;
                }
            }
            $data['media'] = $validMediaIds;
            $block->setData($data);
        }
    }

    /**
     * Generates and returns the configuration form for the video thumbnail block.
     *
     * Initializes the form, populates the video selection dropdown with available video media from the site, and sets form data from the block if provided. Returns the rendered form partial for display in the block editor.
     *
     * @return string The rendered HTML form partial for the video thumbnail block.
     */
    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        // CRITICAL DEBUG: Add error_log to ensure form method is being called
        error_log("DerivativeMedia: VideoThumbnail::form method called");

        $opId = 'block_form_' . uniqid();
        $this->debugManager->traceBlockForm($opId, $block);

        try {
            $this->debugManager->logInfo('Getting VideoThumbnailBlockForm from FormElementManager', DebugManager::COMPONENT_BLOCK, $opId);
            error_log("DerivativeMedia: About to get form from FormElementManager");
            $form = $this->formElementManager->get(VideoThumbnailBlockForm::class);

            // CRITICAL: Initialize the form before using it
            $this->debugManager->logInfo('Initializing form', DebugManager::COMPONENT_BLOCK, $opId);
            $form->init();

            // Populate media options with video files - CRITICAL: Do this in form method, not factory
            try {
                $this->debugManager->logInfo('Loading video media for dropdown', DebugManager::COMPONENT_BLOCK, $opId);

                $videoMedia = $this->apiManager->search('media', [
                    'site_id' => $site->id(),
                    'sort_by' => 'title',
                    'sort_order' => 'asc',
                    'limit' => 100,
                ])->getContent();

                // Filter for video media types
                $videoMedia = array_filter($videoMedia, function($media) {
                    $mediaType = $media->mediaType();
                    return $mediaType && strpos($mediaType, 'video/') === 0;
                });

                $mediaOptions = ['' => 'Select a video...'];
                foreach ($videoMedia as $media) {
                    $title = $media->displayTitle() ?: $media->source();
                    $mediaOptions[$media->id()] = sprintf('%s (ID: %d)', $title, $media->id());
                }

                $this->debugManager->logInfo(sprintf('Found %d video media items, setting %d options', count($videoMedia), count($mediaOptions)), DebugManager::COMPONENT_BLOCK, $opId);

                // Update the media_id element with populated options
                $mediaElement = $form->get('media_id');
                $mediaElement->setValueOptions($mediaOptions);

            } catch (\Exception $e) {
                // If we can't load media, just use empty options
                $this->debugManager->logError('Error loading video media for block form: ' . $e->getMessage(), DebugManager::COMPONENT_BLOCK, $opId);
            }

            if ($block) {
                $data = $block->data();
                $this->debugManager->logInfo(sprintf('Setting form data from block: %s', json_encode($data)), DebugManager::COMPONENT_BLOCK, $opId);
                $form->setData($data);
            } else {
                $this->debugManager->logInfo('No block data to set (new block)', DebugManager::COMPONENT_BLOCK, $opId);
            }

            $this->debugManager->logInfo('Rendering form partial', DebugManager::COMPONENT_BLOCK, $opId);
            return $view->partial('common/block-layout/video-thumbnail-form', [
                'form' => $form,
                'block' => $block,
                'site' => $site,
            ]);
        } catch (\Exception $e) {
            $this->debugManager->logError(sprintf('Exception in form method: %s', $e->getMessage()), DebugManager::COMPONENT_BLOCK, $opId);
            throw $e;
        }
    }

    /**
     * Renders the video thumbnail block using the provided view and block data.
     *
     * Passes the block, its data, the current site context, and the video thumbnail service to the view partial for display.
     *
     * @return string The rendered HTML for the video thumbnail block.
     */
    public function render(PhpRenderer $view, SitePageBlockRepresentation $block) {
        // BLOCK_TRACE: Log block rendering attempt
        error_log("BLOCK_TRACE: VideoThumbnail::render() called");
        error_log("BLOCK_TRACE: Block data: " . json_encode($block->data()));
        error_log("BLOCK_TRACE: View class: " . get_class($view));

        $data = $block->data();

        // CRITICAL FIX: Get site context for proper URL generation
        $site = $view->vars()->offsetGet('site');

        return $view->partial('common/block-layout/video-thumbnail', [
            'block' => $block,
            'data' => $data,
            'site' => $site, // Pass site context to template
            'videoThumbnailService' => $this->videoThumbnailService,
        ]);
    }

    /**
     * Processes and sanitizes form data for the video thumbnail block before saving.
     *
     * Converts and validates the `media_id` and `override_percentage` fields, ensuring correct types and value ranges. Handles legacy support for the `percentage` field, and trims string fields such as `heading` and `template`. Throws an exception if the percentage is out of the 0–100 range.
     *
     * @param array $data The submitted form data.
     * @return array The sanitized and validated block data.
     * @throws \InvalidArgumentException If the percentage value is outside the allowed range.
     */
    public function handleFormData(array $data)
    {
        $blockData = [];

        // Process media_id field - CRITICAL for video persistence
        if (array_key_exists('media_id', $data)) {
            if (empty($data['media_id']) || $data['media_id'] === '0') {
                $blockData['media_id'] = null;
            } else {
                $blockData['media_id'] = (int)$data['media_id'];
            }
        } else {
            $blockData['media_id'] = null;
        }

        // Process override_percentage field (primary field)
        if (array_key_exists('override_percentage', $data)) {
            if ($data['override_percentage'] === '' || $data['override_percentage'] === null) {
                $blockData['override_percentage'] = null;
            } elseif (is_numeric($data['override_percentage'])) {
                $intVal = (int)$data['override_percentage'];
                if ($intVal < 0 || $intVal > 100) {
                    throw new \InvalidArgumentException('Thumbnail Position (%) must be between 0 and 100.');
                }
                $blockData['override_percentage'] = $intVal;
            } else {
                $blockData['override_percentage'] = null;
            }
        } elseif (array_key_exists('percentage', $data)) {
            // Handle legacy 'percentage' field for backward compatibility
            if ($data['percentage'] === '' || $data['percentage'] === null) {
                $blockData['override_percentage'] = null;
            } elseif (is_numeric($data['percentage'])) {
                $intVal = (int)$data['percentage'];
                if ($intVal < 0 || $intVal > 100) {
                    throw new \InvalidArgumentException('Thumbnail Position (%) must be between 0 and 100.');
                }
                $blockData['override_percentage'] = $intVal;
            } else {
                $blockData['override_percentage'] = null;
            }
        } else {
            $blockData['override_percentage'] = null;
        }

        // Process heading field
        if (array_key_exists('heading', $data)) {
            $blockData['heading'] = trim($data['heading']) ?: null;
        } else {
            $blockData['heading'] = null;
        }

        // Process template field
        if (array_key_exists('template', $data)) {
            $blockData['template'] = trim($data['template']) ?: null;
        } else {
            $blockData['template'] = null;
        }

        return $blockData;
    }
}
