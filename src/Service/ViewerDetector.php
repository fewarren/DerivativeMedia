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

    /****
     * Initializes the ViewerDetector with module manager, global settings, and site-specific settings services.
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
     * Each entry includes the module name, support flags for video, media pages, and item pages, relevant settings, priority, and URL strategy. The result is sorted by descending priority.
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
     * Determines the most suitable active viewer module for video content.
     *
     * Selects the preferred viewer based on user settings if available and capable; otherwise, returns the highest priority active viewer that supports video. Returns null if no suitable viewer is found.
     *
     * @return array|null The selected viewer's configuration array, or null if none are available.
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
     * Determines the optimal URL strategy for displaying a video thumbnail based on the best available viewer and its configuration.
     *
     * Selects a strategy and URL type depending on the active viewer module and its settings, with specific handling for OctopusViewer and UniversalViewer. Returns a default strategy if no suitable viewer is found.
     *
     * @param object $media The media object for which the URL strategy is determined.
     * @param string $siteSlug The slug of the site context.
     * @return array An associative array containing the strategy, viewer details, and URL type.
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
     * @param string $moduleId The ID of the module to check.
     * @return bool True if the module exists and is active; otherwise, false.
     */
    private function isModuleActive($moduleId)
    {
        $module = $this->moduleManager->getModule($moduleId);
        return $module && $module->getState() === ModuleManager::STATE_ACTIVE;
    }

    /**
     * Generates a URL to the dedicated video player page for the given media and site.
     *
     * Always constructs the URL manually to ensure the preferred viewer is used and to avoid CleanUrl conflicts.
     *
     * @param object $media The media object.
     * @param string $siteSlug The site slug.
     * @param callable|null $urlHelper Ignored; present for interface compatibility.
     * @return string The URL to the video player page for the specified media.
     */
    public function generateVideoUrl($media, $siteSlug, $urlHelper = null)
    {
        // CRITICAL FIX: Use manual URL construction to avoid CleanUrl conflicts
        // Always use the dedicated video player page to ensure only preferred viewer is shown

        // Manual URL construction for the video player page
        return "/s/$siteSlug/video-player/" . $media->id();
    }

    /**
     * Returns debugging information about active viewer modules and related settings.
     *
     * The returned array includes lists of active modules, detected video viewer modules with their capabilities, and relevant configuration settings.
     *
     * @return array Associative array with keys: 'active_modules', 'viewer_modules', and 'settings'.
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
