/**
 * DerivativeMedia Video Enhancements
 * 
 * Provides enhanced functionality for video thumbnail blocks and viewer integration
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVideoEnhancements);
    } else {
        initVideoEnhancements();
    }

    /**
     * Initializes video enhancements for the page, including thumbnail interactivity, viewer-specific features, and accessibility improvements.
     */
    function initVideoEnhancements() {
        console.log('DerivativeMedia: Initializing video enhancements');
        
        // Enhance video thumbnail blocks
        enhanceVideoThumbnails();
        
        // Add viewer-specific enhancements
        addViewerEnhancements();
        
        // Add accessibility improvements
        addAccessibilityEnhancements();
    }

    /**
     * Enhances all video thumbnail items with improved keyboard navigation, hover effects, and play event tracking.
     *
     * Adds keyboard support for activating video links, visual hover scaling on play overlays, and dispatches a custom event when a video play is initiated from a thumbnail.
     */
    function enhanceVideoThumbnails() {
        const videoThumbnails = document.querySelectorAll('.video-thumbnail-item');
        
        videoThumbnails.forEach(function(thumbnail) {
            const mediaId = thumbnail.getAttribute('data-media-id');
            
            // Add keyboard navigation
            const links = thumbnail.querySelectorAll('a');
            links.forEach(function(link) {
                link.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        link.click();
                    }
                });
            });
            
            // Add hover effects
            thumbnail.addEventListener('mouseenter', function() {
                const playOverlay = thumbnail.querySelector('.play-overlay');
                if (playOverlay) {
                    playOverlay.style.transform = 'translate(-50%, -50%) scale(1.1)';
                }
            });
            
            thumbnail.addEventListener('mouseleave', function() {
                const playOverlay = thumbnail.querySelector('.play-overlay');
                if (playOverlay) {
                    playOverlay.style.transform = 'translate(-50%, -50%) scale(1)';
                }
            });
            
            // Add click tracking for analytics
            const playButtons = thumbnail.querySelectorAll('.video-play-btn, .video-thumbnail-link');
            playButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    console.log('DerivativeMedia: Video play initiated for media ID:', mediaId);
                    
                    // Trigger custom event for other modules to listen to
                    const event = new CustomEvent('derivativeMediaVideoPlay', {
                        detail: {
                            mediaId: mediaId,
                            url: button.href
                        }
                    });
                    document.dispatchEvent(event);
                });
            });
        });
    }

    /**
     * Applies video and viewer-specific enhancements on media or item pages.
     *
     * Enhances all video elements with custom controls and keyboard shortcuts, and applies additional enhancements for detected viewers when on relevant pages.
     */
    function addViewerEnhancements() {
        // Check if we're on a media or item page
        const isMediaPage = document.body.classList.contains('media') && 
                           document.body.classList.contains('show');
        const isItemPage = document.body.classList.contains('item') && 
                          document.body.classList.contains('show');
        
        if (isMediaPage || isItemPage) {
            // Add enhanced video controls if video is present
            const videos = document.querySelectorAll('video');
            videos.forEach(enhanceVideoElement);
            
            // Add viewer-specific enhancements
            enhanceActiveViewer();
        }
    }

    /**
     * Enhances a video element with keyboard accessibility and custom controls.
     *
     * Adds keyboard shortcuts for play/pause (Space), rewind (ArrowLeft), and fast-forward (ArrowRight), and ensures the video is focusable for keyboard navigation.
     */
    function enhanceVideoElement(video) {
        // Add custom controls and features
        video.addEventListener('loadedmetadata', function() {
            console.log('DerivativeMedia: Video loaded, duration:', video.duration);
        });
        
        // Add keyboard shortcuts
        video.addEventListener('keydown', function(e) {
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    if (video.paused) {
                        video.play();
                    } else {
                        video.pause();
                    }
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    video.currentTime = Math.max(0, video.currentTime - 10);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    video.currentTime = Math.min(video.duration, video.currentTime + 10);
                    break;
            }
        });
        
        // Make video focusable for keyboard navigation
        if (!video.hasAttribute('tabindex')) {
            video.setAttribute('tabindex', '0');
        }
    }

    /**
     * Detects and logs the presence of supported media viewers on the page, providing hooks for viewer-specific enhancements.
     */
    function enhanceActiveViewer() {
        // OctopusViewer enhancements
        if (window.OctopusViewer) {
            console.log('DerivativeMedia: OctopusViewer detected, adding enhancements');
            // Add OctopusViewer-specific enhancements here
        }
        
        // UniversalViewer enhancements
        if (window.UV) {
            console.log('DerivativeMedia: UniversalViewer detected, adding enhancements');
            // Add UniversalViewer-specific enhancements here
        }
        
        // Check for other viewers
        if (document.querySelector('.mirador-viewer')) {
            console.log('DerivativeMedia: Mirador viewer detected');
        }
    }

    /**
     * Improves accessibility for video thumbnails and controls by adding ARIA labels, roles, and visible focus indicators.
     *
     * Adds descriptive ARIA labels to video thumbnail links, sets appropriate roles and labels on thumbnail containers, hides decorative play buttons from screen readers, and injects CSS for visible keyboard focus outlines on interactive elements.
     */
    function addAccessibilityEnhancements() {
        // Add ARIA labels to video thumbnails
        const videoThumbnails = document.querySelectorAll('.video-thumbnail-item');
        
        videoThumbnails.forEach(function(thumbnail) {
            const title = thumbnail.querySelector('h4');
            const link = thumbnail.querySelector('.video-thumbnail-link');
            
            if (title && link) {
                const titleText = title.textContent.trim();
                link.setAttribute('aria-label', 'Play video: ' + titleText);
            }
            
            // Add role attributes
            thumbnail.setAttribute('role', 'article');
            thumbnail.setAttribute('aria-label', 'Video thumbnail');
        });
        
        // Add screen reader support for play buttons
        const playButtons = document.querySelectorAll('.play-button');
        playButtons.forEach(function(button) {
            button.setAttribute('aria-hidden', 'true');
        });
        
        // Add focus indicators
        const style = document.createElement('style');
        style.textContent = `
            .video-thumbnail-link:focus {
                outline: 2px solid #007cba;
                outline-offset: 2px;
            }
            
            .video-play-btn:focus {
                outline: 2px solid #007cba;
                outline-offset: 2px;
            }
            
            video:focus {
                outline: 2px solid #007cba;
                outline-offset: 2px;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Detects and returns the availability of various video viewers and playback capabilities on the current page.
     * @returns {Object} An object indicating the presence of OctopusViewer, UniversalViewer, Mirador, VideoJS, and HTML5 video support.
     */
    function detectViewerCapabilities() {
        const capabilities = {
            hasOctopusViewer: !!window.OctopusViewer,
            hasUniversalViewer: !!window.UV,
            hasMirador: !!document.querySelector('.mirador-viewer'),
            hasVideoJS: !!window.videojs,
            hasHTML5Video: !!document.createElement('video').canPlayType
        };
        
        console.log('DerivativeMedia: Detected viewer capabilities:', capabilities);
        return capabilities;
    }

    // Export functions for other scripts to use
    window.DerivativeMediaEnhancements = {
        detectViewerCapabilities: detectViewerCapabilities,
        enhanceVideoElement: enhanceVideoElement
    };

})();
