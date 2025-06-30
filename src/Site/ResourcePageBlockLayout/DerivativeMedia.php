<?php declare(strict_types=1);

namespace DerivativeMedia\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class DerivativeMedia implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Derivative Media List'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            'media',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/derivative-media', [
            'resource' => $resource,
        ]);
    }
}
