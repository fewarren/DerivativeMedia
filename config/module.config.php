<?php declare(strict_types=1);

namespace DerivativeMedia;

return [
    // FIXED: Disable custom file renderers by default to prevent item page layout issues
    // These can be enabled via module settings if needed for specific use cases
    'file_renderers' => [
        'invokables' => [
            'audio' => Media\FileRenderer\AudioRenderer::class,
            'video' => Media\FileRenderer\VideoRenderer::class,
        ],
        'aliases' => [
            // Override core aliases to use our custom renderers
            'audio/ogg' => 'audio',
            'audio/x-aac' => 'audio',
            'audio/mpeg' => 'audio',
            'audio/mp4' => 'audio',
            'audio/x-wav' => 'audio',
            'audio/x-aiff' => 'audio',
            'application/ogg' => 'video',
            'video/mp4' => 'video',
            'video/quicktime' => 'video',
            'video/x-msvideo' => 'video',
            'video/ogg' => 'video',
            'video/webm' => 'video',
            'mp3' => 'audio',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        // SOLUTION: Remove global ViewJsonStrategy to prevent HTML over-escaping
        // JSON responses are handled explicitly in controller actions using JsonModel
        // This preserves JSON functionality while fixing theme display issues
    ],
    'view_helpers' => [
        'invokables' => [
            'derivatives' => View\Helper\Derivatives::class,
            'fileSize' => View\Helper\FileSize::class,
            'resourceValues' => View\Helper\ResourceValues::class,
        ],
        'factories' => [
            'derivativeList' => Service\ViewHelper\DerivativeListFactory::class,
            'viewerDetector' => View\Helper\ViewerDetectorFactory::class,
            // CRITICAL FIX: Override ServerUrl helper to fix URL generation issues
            'serverUrl' => View\Helper\CustomServerUrlFactory::class,
        ],
        /** @deprecated Old helpers. */
        'aliases' => [
            'derivativeMedia' => 'derivatives',
            'hasDerivative' => 'derivativeList',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        'factories' => [
            Form\VideoThumbnailBlockForm::class => Service\Form\VideoThumbnailBlockFormFactory::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'derivativeMedia' => Site\ResourcePageBlockLayout\DerivativeMedia::class,
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'videoThumbnail' => Service\Site\BlockLayout\VideoThumbnailFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'DerivativeMedia\Service\VideoThumbnailService' => Service\VideoThumbnailServiceFactory::class,
            'DerivativeMedia\Service\DebugManager' => Service\DebugManagerFactory::class,
            'DerivativeMedia\Service\ViewerDetector' => Service\ViewerDetectorFactory::class,
            'DerivativeMedia\Service\EventListener' => Service\EventListenerFactory::class,
            'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer' => Service\VideoAwareThumbnailerFactory::class,
        ],
        'aliases' => [
            // UNIVERSAL THUMBNAILER: Handles ALL media types (videos, PDFs, images, etc.)
        ],
    ],
    'thumbnails' => [
        'factories' => [
            'DerivativeMedia\VideoThumbnailer' => Service\File\Thumbnailer\VideoThumbnailerFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'DerivativeMedia\Controller\Index' => Controller\IndexController::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'checkFfmpeg' => Mvc\Controller\Plugin\CheckFfmpeg::class,
            'checkGhostscript' => Mvc\Controller\Plugin\CheckGhostscript::class,
        ],
        'factories' => [
            'createDerivative' => Service\ControllerPlugin\CreateDerivativeFactory::class,
        ],
    ],
    'jobs' => [
        'invokables' => [
            'DerivativeMedia\Job\GenerateVideoThumbnails' => Job\GenerateVideoThumbnails::class,
        ],
    ],
    'router' => [
        'routes' => [
            // Dynamic formats.
            'derivative' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/derivative/:id/:type',
                    'constraints' => [
                        'id' => '\d+',
                        'type' => 'alto|iiif-2|iiif-3|pdf2xml|pdf|text|txt|zipm|zipo|zip',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'DerivativeMedia\Controller',
                        'controller' => 'Index',
                        'action' => 'index',
                    ],
                ],
            ],
            // Debug route for viewer detection
            'derivative-debug' => [
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/derivative-debug',
                    'defaults' => [
                        '__NAMESPACE__' => 'DerivativeMedia\Controller',
                        'controller' => 'Index',
                        'action' => 'debug',
                    ],
                ],
            ],
            // Video player route that respects preferred viewer setting
            'derivative-video-player' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/s/:site-slug/video-player/:media-id',
                    'constraints' => [
                        'site-slug' => '[a-zA-Z0-9_-]+',
                        'media-id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'DerivativeMedia\Controller',
                        'controller' => 'Index',
                        'action' => 'video-player',
                    ],
                ],
            ],
            // CRITICAL FIX: Missing download route for file serving
            'derivative-download-files' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/download/files/:folder/:id/:filename',
                    'constraints' => [
                        'folder' => '[a-zA-Z0-9_-]+',
                        'id' => '\d+',
                        'filename' => '[a-zA-Z0-9._-]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'DerivativeMedia\Controller',
                        'controller' => 'Index',
                        'action' => 'download-file',
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'derivativemedia' => [
        'settings' => [
            'derivativemedia_enable' => [],
            'derivativemedia_update' => 'all_live',
            'derivativemedia_max_size_live' => 30,
            'derivativemedia_converters_audio' => [
                'mp3/{filename}.mp3' => '-c copy -c:a libmp3lame -qscale:a 2',
                'ogg/{filename}.ogg' => '-c copy -vn -c:a libopus',
            ],
            'derivativemedia_converters_video' => [
                '# The webm converter is designed for modern browsers. Keep it first if used.' => '',
                'webm/{filename}.webm' => '-c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -deadline realtime -pix_fmt yuv420p -c:a libopus',
                '# This format keeps the original quality and is compatible with almost all browsers.' => '',
                'mp4/{filename}.mp4' => "-c copy -c:v libx264 -movflags +faststart -filter:v crop='floor(in_w/2)*2:floor(in_h/2)*2' -crf 22 -level 3 -preset medium -tune film -pix_fmt yuv420p -c:a libmp3lame -qscale:a 2",
            ],
            'derivativemedia_converters_pdf' => [
                '# The default setting "/screen" output the smallest pdf readable on a screen.' => '',
                'pdfs/{filename}.pdf' => '-dCompatibilityLevel=1.7 -dPDFSETTINGS=/screen',
                '# The default setting "/ebook" output a medium size pdf readable on any device.' => '',
                'pdfe/{filename}.pdf' => '-dCompatibilityLevel=1.7 -dPDFSETTINGS=/ebook',
            ],
            'derivativemedia_append_original_audio' => false,
            'derivativemedia_append_original_video' => false,
            'derivativemedia_video_thumbnail_percentage' => 25,
            'derivativemedia_video_thumbnail_enabled' => true,
            'derivativemedia_ffmpeg_path' => '/usr/bin/ffmpeg',
            'derivativemedia_ffprobe_path' => '/usr/bin/ffprobe',
            'derivativemedia_preferred_viewer' => 'auto',
            // FIXED: New settings to control item page behavior
            'derivativemedia_enable_item_page_enhancements' => false,
            'derivativemedia_enable_custom_file_renderers' => true,
            // SECURITY: Download prevention setting
            'derivativemedia_disable_video_downloads' => false,
            // DEBUG: Enhanced debugging options
            'derivativemedia_debug_enabled' => true,
            'derivativemedia_debug_level' => 'detailed',
            'derivativemedia_debug_log_file' => 'DerivativeMedia_debug.log',
            'derivativemedia_debug_log_path' => null, // Auto-detect if null
        ],
    ],
];
