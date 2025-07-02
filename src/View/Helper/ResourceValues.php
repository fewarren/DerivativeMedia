<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * View helper for displaying resource values/metadata.
 */
class ResourceValues extends AbstractHelper
{
    /**
     * Generates formatted HTML displaying all metadata values of a resource, optionally filtered by language.
     *
     * For each property of the resource, outputs a section with the property label and its values. Values are rendered as links for linked resources and URIs, or as escaped text for literals. If a language filter is provided and enabled, only values matching the specified languages (or with no language code) are included.
     *
     * @param AbstractResourceEntityRepresentation $resource The resource whose metadata values will be displayed.
     * @param array|null $valueLang Optional array of language codes to filter values.
     * @param bool $filterLocale Whether to apply the language filter to values.
     * @return string The HTML output representing the resource's properties and values.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $valueLang = null, bool $filterLocale = false): string
    {
        $view = $this->getView();
        $escape = $view->plugin('escapeHtml');
        $translate = $view->plugin('translate');
        
        $output = '';
        
        // Get all values for the resource
        $values = $resource->values();
        
        if (empty($values)) {
            return $output;
        }
        
        foreach ($values as $term => $property) {
            $propertyLabel = $property['property']->label();
            if (!$propertyLabel) {
                $propertyLabel = $property['property']->localName();
            }
            
            $propertyValues = $property['values'];
            if (empty($propertyValues)) {
                continue;
            }
            
            // Filter values by language if specified
            if ($valueLang && $filterLocale) {
                $filteredValues = [];
                foreach ($propertyValues as $value) {
                    $valueLangCode = $value->lang();
                    if (in_array($valueLangCode, $valueLang) || empty($valueLangCode)) {
                        $filteredValues[] = $value;
                    }
                }
                $propertyValues = $filteredValues;
            }
            
            if (empty($propertyValues)) {
                continue;
            }
            
            $output .= '<div class="property">' . "\n";
            $output .= '    <h4>' . $escape($propertyLabel) . '</h4>' . "\n";
            $output .= '    <div class="values">' . "\n";
            
            foreach ($propertyValues as $value) {
                $output .= '        <div class="value">';
                
                // Handle different value types
                if ($value->type() === 'resource') {
                    // Linked resource
                    $linkedResource = $value->valueResource();
                    if ($linkedResource) {
                        $output .= $linkedResource->link($linkedResource->displayTitle());
                    } else {
                        $output .= $escape($value->value());
                    }
                } elseif ($value->type() === 'uri') {
                    // URI value
                    $uri = $value->uri();
                    $label = $value->value() ?: $uri;
                    if ($uri) {
                        $output .= '<a href="' . $escape($uri) . '" target="_blank">' . $escape($label) . '</a>';
                    } else {
                        $output .= $escape($label);
                    }
                } else {
                    // Literal value
                    $output .= $escape($value->value());
                }
                
                $output .= '</div>' . "\n";
            }
            
            $output .= '    </div>' . "\n";
            $output .= '</div>' . "\n";
        }
        
        return $output;
    }
}
