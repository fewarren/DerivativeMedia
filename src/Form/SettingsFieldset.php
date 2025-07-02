<?php declare(strict_types=1);

namespace DerivativeMedia\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Derivative Media'; // @translate

    protected $elementGroups = [
        'derivative_media' => 'Derivative Media', // @translate
    ];

    /**
     * Initializes the fieldset with form elements for configuring derivative media processing options.
     *
     * Adds controls for selecting media formats to convert, update behavior, converter commands for audio, video, and PDF, options to append original files, video thumbnail generation settings, and paths to FFmpeg and FFprobe executables. All elements are grouped under "Derivative Media" for organized configuration.
     */
    public function init(): void
    {
        $this
            ->setAttribute('id', 'derivative-media')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'derivativemedia_enable',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Formats to convert', // @translate
                    'value_options' => [
                        'audio' => 'Audio', // @translate
                        'video' => 'Video', // @translate
                        'pdf_media' => 'Pdf', // @translate
                        'zip' => 'Zip item files', // @translate
                        'zipm' => 'Zip item image/audio/video files', // @translate
                        'zipo' => 'Zip item other files', // @translate
                        'pdf' => 'Pdf from images files', // @translate
                        'txt' => 'Single text file from by-page txt files', // @translate
                        'text' => 'Single text file from property "extracted text"', // @translate
                        'alto' => 'Single xml Alto from by-page xml Alto (standard ocr format, require Iiif Search)', // @translate
                        'iiif-2' => 'Iiif manifest (version 2, require Iiif Server)', // @translate
                        'iiif-3' => 'Iiif manifest (version 3, require Iiif Server)', // @translate
                        'pdf2xml' => 'Text as xml from a single pdf, mainly for Iiif Search', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'derivativemedia_enable',
                ],
            ])
            ->add([
                'name' => 'derivativemedia_update',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Create or update derivative files on individual save (not batch process)', // @translate
                    'info' => 'Quick processes can be done during a web request (30 seconds); heavy processes are audio, video, pdf and zip with many big files and require a background job. Audio and video processes are never reprocessed, since the original cannot change.', // @translate
                    // Note: derivative currently building may not be up-to-date!
                    'value_options' => [
                        'no' => 'No (may need manual process)', // @translate
                        'existing_live' => 'Update only existing derivative files (quick processes only)', // @translate
                        'existing' => 'Update only existing derivative files', // @translate
                        'all_live' => 'Create and update all quick derivatives', // @translate
                        'all' => 'Create and update all derivatives (take care of disk space and server overload)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'derivativemedia_update',
                ],
            ])
            ->add([
                'name' => 'derivativemedia_max_size_live',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Max total media size in megabytes to prepare a derivative file live', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_max_size_live',
                ],
            ])

            ->add([
                'name' => 'derivativemedia_converters_audio',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Audio converters', // @translate
                    'info' => 'Each converter is one row with a filepath pattern, a "=", and the ffmpeg command (without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia#usage',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters_audio',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'derivativemedia_append_original_audio',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Append original audio', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_append_original_audio',
                ],
            ])

            ->add([
                'name' => 'derivativemedia_converters_video',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Video converters', // @translate
                    'info' => 'Each converter is one row with a filepath pattern, a "=", and the ffmpeg command (without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters_video',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'derivativemedia_append_original_video',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Append original video', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_append_original_video',
                ],
            ])

            ->add([
                'name' => 'derivativemedia_converters_pdf',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Pdf converters', // @translate
                    'info' => 'Each converter is one row with a filepath pattern, a "=", and the gs command (ghostscript, without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia#usage',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters_pdf',
                    'rows' => 5,
                ],
            ])

            ->add([
                'name' => 'derivativemedia_video_thumbnail_enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Enable automatic video thumbnail generation', // @translate
                    'info' => 'Automatically generate thumbnails when video files are uploaded.', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_video_thumbnail_enabled',
                ],
            ])
            ->add([
                'name' => 'derivativemedia_video_thumbnail_percentage',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'Default thumbnail position (%)', // @translate
                    'info' => 'Percentage of video duration for thumbnail capture (0-100). Default is 25%.', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_video_thumbnail_percentage',
                    'min' => 0,
                    'max' => 100,
                    'step' => 1,
                ],
            ])
            ->add([
                'name' => 'derivativemedia_ffmpeg_path',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'FFmpeg path', // @translate
                    'info' => 'Full path to the FFmpeg executable. Leave empty to use system default.', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_ffmpeg_path',
                    'placeholder' => '/usr/bin/ffmpeg',
                ],
            ])
            ->add([
                'name' => 'derivativemedia_ffprobe_path',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'derivative_media',
                    'label' => 'FFprobe path', // @translate
                    'info' => 'Full path to the FFprobe executable. Leave empty to use system default.', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_ffprobe_path',
                    'placeholder' => '/usr/bin/ffprobe',
                ],
            ])
        ;
    }
}
