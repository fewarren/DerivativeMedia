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
     * Generates formatted HTML output displaying all metadata values of a resource.
     *
     * Iterates over each property of the given resource, optionally filtering values by language and locale. Renders each property with its label and values, formatting linked resources as hyperlinks, URIs as clickable links, and literals as escaped text.
     *
     * @param AbstractResourceEntityRepresentation $resource The resource whose metadata values will be displayed.
     * @param array|null $valueLang Optional array of language codes to filter values.
     * @param bool $filterLocale Whether to apply language filtering to the values.
     * @return string HTML markup representing the resource's metadata values.
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
