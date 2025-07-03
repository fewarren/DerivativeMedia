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
     * Display resource values/metadata in a formatted way.
     *
     * @param AbstractResourceEntityRepresentation $resource The resource (item, media, etc.)
     * @param array|null $valueLang Language filter for values
     * @param bool $filterLocale Whether to filter by locale
     * @return string HTML output of resource values
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
                        // SECURITY FIX: Add rel="noopener" to prevent window.opener access
                        $output .= '<a href="' . $escape($uri) . '" target="_blank" rel="noopener">' . $escape($label) . '</a>';
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
