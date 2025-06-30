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
     * Initializes the ViewerDetector with module management and settings services.
     *
     * @param ModuleManager $moduleManager Manages the state of installed modules.
     * @param Settings $settings Provides access to global configuration settings.
     * @param SiteSettings $siteSettings Provides access to site-specific configuration settings.
     */
    public function __construct(ModuleManager $moduleManager, Settings $settings, SiteSettings $siteSettings)
    {
        $this->moduleManager = $moduleManager;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
    }

    /**
     * Returns an array of all active viewer modules with their video capabilities and configuration.
     *
     * Each viewer entry includes capability flags, priority, and URL strategy. Only modules that are currently active are included, and the result is sorted by descending priority.
     *
     * @return array Associative array of active viewer modules and their capabilities.
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
     * Returns the highest priority viewer module that supports video playback.
     *
     * If a preferred viewer is set in the settings and supports video, it is returned; otherwise, the first available video-capable viewer by priority is selected. Returns null if no suitable viewer is found.
     *
     * @return array|null The selected viewer's configuration, or null if none support video.
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
     * Determines the optimal URL strategy for displaying video thumbnails based on the best available viewer and its configuration.
     *
     * Returns an array describing the chosen strategy, the viewer used, and the type of URL to generate. The strategy adapts to the capabilities and settings of active viewer modules, such as OctopusViewer or UniversalViewer, and falls back to a standard approach if no suitable viewer is found.
     *
     * @param object $media The media object for which the URL strategy is determined.
     * @param string $siteSlug The slug of the site context.
     * @return array An associative array with keys: 'strategy', 'viewer', and 'url_type'.
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

    /****
     * Determines whether the specified module is currently active.
     *
     * @param string $moduleId The identifier of the module to check.
     * @return bool True if the module is active; otherwise, false.
     */
    private function isModuleActive($moduleId)
    {
        $module = $this->moduleManager->getModule($moduleId);
        return $module && $module->getState() === ModuleManager::STATE_ACTIVE;
    }

    /**
     * Generates a canonical URL for a video media object that always points to the dedicated video player page under the specified site slug.
     *
     * This ensures the preferred viewer is used and avoids conflicts with other URL schemes or clean URL routing.
     *
     * @param object $media The media object.
     * @param string $siteSlug The site slug.
     * @param callable|null $urlHelper Optional URL helper (not used; URL is constructed manually).
     * @return string The URL to the dedicated video player page for the media.
     */
    public function generateVideoUrl($media, $siteSlug, $urlHelper = null)
    {
        // CRITICAL FIX: Use manual URL construction to avoid CleanUrl conflicts
        // Always use the dedicated video player page to ensure only preferred viewer is shown

        // Manual URL construction for the video player page
        return "/s/$siteSlug/video-player/" . $media->id();
    }

    /**
     * Returns debugging information about active modules, available video viewers, and relevant viewer settings.
     *
     * @return array An associative array containing lists of active module IDs, active video viewer modules with their capabilities, and key viewer-related settings.
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
