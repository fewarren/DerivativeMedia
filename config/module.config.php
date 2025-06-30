<?php declare(strict_types=1);

namespace DerivativeMedia;

return [
    'file_renderers' => [
        'invokables' => [
            'audio' => Media\FileRenderer\AudioRenderer::class,
            'video' => Media\FileRenderer\VideoRenderer::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'derivatives' => View\Helper\Derivatives::class,
        ],
        'factories' => [
            'derivativeList' => Service\ViewHelper\DerivativeListFactory::class,
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
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'derivativeMedia' => Site\ResourcePageBlockLayout\DerivativeMedia::class,
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
        ],
    ],
];
