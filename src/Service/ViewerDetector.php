<?php
namespace DerivativeMedia\Service;

use Omeka\Module\Manager as ModuleManager;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;

/**
 * Service to detect active media viewer modules and their capabilities
 */
class ViewerDetector
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var SiteSettings
     */
    private $siteSettings;

    /**
     * Constructor
     */
    public function __construct(ModuleManager $moduleManager, Settings $settings, SiteSettings $siteSettings)
    {
        $this->moduleManager = $moduleManager;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
    }

    /**
     * Get all active viewer modules and their video capabilities
     *
     * @return array
     */
    public function getActiveVideoViewers()
    {
        $viewers = [];

        // Check OctopusViewer
        if ($this->isModuleActive('OctopusViewer')) {
            $viewers['OctopusViewer'] = [
                'name' => 'OctopusViewer',
                'supports_video' => true,
                'supports_media_pages' => true,
                'supports_item_pages' => true,
                'media_show_setting' => $this->settings->get('octopusviewer_media_show'),
                'item_show_setting' => $this->settings->get('octopusviewer_item_show'),
                'priority' => 10,
                'url_strategy' => 'media_or_item'
            ];
        }

        // Check UniversalViewer
        if ($this->isModuleActive('UniversalViewer')) {
            $viewers['UniversalViewer'] = [
                'name' => 'UniversalViewer',
                'supports_video' => true,
                'supports_media_pages' => false,
                'supports_item_pages' => true,
                'requires_item_context' => true,
                'priority' => 8,
                'url_strategy' => 'item_only'
            ];
        }

        // Check PdfViewer (may support video in some configurations)
        if ($this->isModuleActive('PdfViewer')) {
            $viewers['PdfViewer'] = [
                'name' => 'PdfViewer',
                'supports_video' => false,
                'supports_media_pages' => true,
                'supports_item_pages' => false,
                'priority' => 5,
                'url_strategy' => 'media_only'
            ];
        }

        // Sort by priority (highest first)
        uasort($viewers, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });

        return $viewers;
    }

    /**
     * Get the best viewer for video content
     *
     * @return array|null
     */
    public function getBestVideoViewer()
    {
        $viewers = $this->getActiveVideoViewers();
        
        // Check user preference first
        $preferredViewer = $this->settings->get('derivativemedia_preferred_viewer', 'auto');
        
        if ($preferredViewer !== 'auto') {
            foreach ($viewers as $viewer) {
                if (strtolower($viewer['name']) === strtolower($preferredViewer) && $viewer['supports_video']) {
                    return $viewer;
                }
            }
        }

        // Auto-detect: return highest priority video-capable viewer
        foreach ($viewers as $viewer) {
            if ($viewer['supports_video']) {
                return $viewer;
            }
        }

        return null;
    }

    /**
     * Determine the best URL strategy for video thumbnails
     *
     * @param object $media The media object
     * @param string $siteSlug The site slug
     * @return array URL strategy information
     */
    public function getVideoUrlStrategy($media, $siteSlug)
    {
        $bestViewer = $this->getBestVideoViewer();
        
        if (!$bestViewer) {
            return [
                'strategy' => 'standard',
                'viewer' => null,
                'url_type' => 'media_direct'
            ];
        }

        // OctopusViewer strategy
        if ($bestViewer['name'] === 'OctopusViewer') {
            // Check if media show is enabled
            if ($bestViewer['media_show_setting']) {
                return [
                    'strategy' => 'octopus_media',
                    'viewer' => $bestViewer,
                    'url_type' => 'media_page'
                ];
            } elseif ($bestViewer['item_show_setting']) {
                return [
                    'strategy' => 'octopus_item',
                    'viewer' => $bestViewer,
                    'url_type' => 'item_page_fragment'
                ];
            }
        }

        // UniversalViewer strategy
        if ($bestViewer['name'] === 'UniversalViewer') {
            return [
                'strategy' => 'universal_item',
                'viewer' => $bestViewer,
                'url_type' => 'item_page_fragment'
            ];
        }

        // Default strategy
        return [
            'strategy' => 'item_fragment',
            'viewer' => $bestViewer,
            'url_type' => 'item_page_fragment'
        ];
    }

    /**
     * Check if a module is active
     *
     * @param string $moduleId
     * @return bool
     */
    private function isModuleActive($moduleId)
    {
        $module = $this->moduleManager->getModule($moduleId);
        return $module && $module->getState() === ModuleManager::STATE_ACTIVE;
    }

    /**
     * Generate the optimal URL for a video media based on active viewers
     * CRITICAL FIX: Always use dedicated video player page to respect preferred viewer setting
     *
     * @param object $media The media object
     * @param string $siteSlug The site slug
     * @param callable|null $urlHelper Optional URL helper function for proper URL generation
     * @return string The generated URL
     */
    public function generateVideoUrl($media, $siteSlug, $urlHelper = null)
    {
        // CRITICAL FIX: Use manual URL construction to avoid CleanUrl conflicts
        // Always use the dedicated video player page to ensure only preferred viewer is shown

        // Manual URL construction for the video player page
        return "/s/$siteSlug/video-player/" . $media->id();
    }

    /**
     * Get viewer module information for debugging
     *
     * @return array
     */
    public function getViewerDebugInfo()
    {
        $info = [
            'active_modules' => [],
            'viewer_modules' => [],
            'settings' => []
        ];

        // Get all active modules
        foreach ($this->moduleManager->getModules() as $moduleId => $module) {
            if ($module->getState() === ModuleManager::STATE_ACTIVE) {
                $info['active_modules'][] = $moduleId;
            }
        }

        // Get viewer-specific information
        $info['viewer_modules'] = $this->getActiveVideoViewers();

        // Get relevant settings
        $info['settings'] = [
            'derivativemedia_preferred_viewer' => $this->settings->get('derivativemedia_preferred_viewer', 'auto'),
            'octopusviewer_media_show' => $this->settings->get('octopusviewer_media_show'),
            'octopusviewer_item_show' => $this->settings->get('octopusviewer_item_show')
        ];

        return $info;
    }
}
