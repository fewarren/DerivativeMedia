<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Derivatives extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/derivatives';

    /**
     * Get the list of derivatives of a resource as html.
     *
     *@param array $options Managed options:
     * - heading (string): the title in the output.
     * - class (string): a class to add to the main div.
     * - warn (bool): add css/js to warn user before download.
     * - template (string): the template to use instead of the default one.
     * Other options are passed to the template.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();

        if (isset($options['divclass'])) {
            $options['class'] = isset($options['class']) ? trim($options['class'] . ' ' . $options['divclass']) : trim((string) $options['divclass']);
            $view->logger()->warn(
                'The option "divclass" has been renamed "class". Check your theme and use blocks settings.' // @translate
            );
        }

        $options += [
            'site' => null,
            'derivatives' => $view->derivativeList($resource),
            'heading' => '',
            'class' => '',
            'warn' => false,
            'template' => self::PARTIAL_NAME,
        ];

        $vars = ['resource' => $resource] + $options;

        $template = $options['template'] ?? self::PARTIAL_NAME;
        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }
}
