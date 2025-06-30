<?php declare(strict_types=1);

namespace DerivativeMedia\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\Between;

class VideoThumbnailBlockForm extends Form
{
    public function init()
    {
        // Add media selection field
        $this->add([
            'name' => 'media_id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Video Media', // @translate
                'empty_option' => 'Select a video...', // @translate
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'id' => 'media_id',
                'class' => 'video-select',
                'required' => false,
            ],
        ]);

        // Add thumbnail position override field
        $this->add([
            'name' => 'override_percentage',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Thumbnail Position (%)', // @translate
                'info' => 'Override the default thumbnail capture position (0-100%). Leave blank to use the default setting.', // @translate
            ],
            'attributes' => [
                'min' => 0, 
                'max' => 100,
                'step' => 1,
                'placeholder' => 'e.g., 25',
                'required' => false,
            ],
        ]);

        // Add heading field
        $this->add([
            'name' => 'heading',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Heading', // @translate
                'info' => 'Optional heading for the video thumbnail block.', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Featured Video',
                'required' => false,
            ],
        ]);

        // Add template field
        $this->add([
            'name' => 'template',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Display Template', // @translate
                'value_options' => [
                    '' => 'Default', // @translate
                    'grid' => 'Grid', // @translate
                    'list' => 'List', // @translate
                ],
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);

        // Set up input filter for validation
        $inputFilter = new InputFilter();

        // Media ID validation
        $inputFilter->add([
            'name' => 'media_id',
            'required' => false,
            'filters' => [
                ['name' => 'StripTags'],
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'Digits',
                    'options' => [
                        'messages' => [
                            'notDigits' => 'Media ID must be a number'
                        ]
                    ]
                ]
            ],
        ]);

        // Override percentage validation
        $inputFilter->add([
            'name' => 'override_percentage',
            'required' => false,
            'filters' => [
                ['name' => 'ToNull', 'options' => ['type' => 'string']],
            ],
            'validators' => [
                [
                    'name' => Between::class,
                    'options' => [
                        'min' => 0,
                        'max' => 100,
                        'inclusive' => true,
                        'messages' => [
                            Between::NOT_BETWEEN => 'Thumbnail Position (%) must be between 0 and 100.' // @translate
                        ]
                    ]
                ]
            ]
        ]);

        // Heading validation
        $inputFilter->add([
            'name' => 'heading',
            'required' => false,
            'filters' => [
                ['name' => 'StripTags'],
                ['name' => 'StringTrim'],
            ],
        ]);

        // Template validation
        $inputFilter->add([
            'name' => 'template',
            'required' => false,
            'filters' => [
                ['name' => 'StripTags'],
                ['name' => 'StringTrim'],
            ],
        ]);

        $this->setInputFilter($inputFilter);
    }
}
