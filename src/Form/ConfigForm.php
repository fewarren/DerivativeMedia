<?php declare(strict_types=1);

namespace DerivativeMedia\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'fieldset_derivative_items',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Create derivatives by items', // @translate
                ],
            ]);

        $fieldset = $this->get('fieldset_derivative_items');
        $fieldset
            ->add([
                'name' => 'query',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Query items', // @translate
                    'query_resource_type' => 'items',
                ],
                'attributes' => [
                    'id' => 'query',
                ],
            ])
            ->add([
                'name' => 'process_derivative_items',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Create derivative files by items in background', // @translate
                ],
                'attributes' => [
                    'id' => 'process_derivative_items',
                    'value' => 'Process', // @translate
                ],
            ]);

        $this
            ->add([
                'name' => 'fieldset_derivative_media',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Create derivatives by media', // @translate
                ],
            ]);

        $fieldset = $this->get('fieldset_derivative_media');
        $fieldset
            ->add([
                'name' => 'query_items',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Query items', // @translate
                    'query_resource_type' => 'items',
                ],
                'attributes' => [
                    'id' => 'query_items',
                ],
            ])
            ->add([
                'name' => 'item_sets',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'label' => 'Item sets', // @translate
                ],
                'attributes' => [
                    'id' => 'item_sets',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'required' => false,
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'ingesters',
                'type' => CommonElement\MediaIngesterSelect::class,
                'options' => [
                    'label' => 'Ingesters', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'ingesters',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select media ingesters…', // @ translate
                ],
            ])
            ->add([
                'name' => 'renderers',
                'type' => CommonElement\MediaRendererSelect::class,
                'options' => [
                    'label' => 'Renderers', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'renderers',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select media renderers…', // @ translate
                ],
            ])
            ->add([
                'name' => 'media_types',
                'type' => CommonElement\MediaTypeSelect::class,
                'options' => [
                    'label' => 'Media types', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'media_types',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select media types…', // @ translate
                ],
            ])
            ->add([
                'name' => 'media_ids',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Media ids', // @translate
                ],
                'attributes' => [
                    'id' => 'media_ids',
                    'placeholder' => '2-6 8 38-52 80-', // @ translate
                ],
            ])
            ->add([
                'name' => 'process_derivative_media',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Create derivative files in background', // @translate
                ],
                'attributes' => [
                    'id' => 'process_derivative_media',
                    'value' => 'Process', // @translate
                ],
            ])
            ->add([
                'name' => 'process_metadata_media',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Store metadata for existing files in directories', // @translate
                    'info' => 'When files are created outside of Omeka and copied in the right directories (webm/, mp3/, etc.) with the right names (same as original and extension), Omeka should record some metadata to be able to render them.', // @translate
                ],
                'attributes' => [
                    'id' => 'process_metadata_media',
                    'value' => 'Update metadata', // @translate
                ],
            ]);
    }
}
