<?php declare(strict_types=1);

namespace DerivativeMedia\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class CheckGhostscript extends AbstractPlugin
{
    /**
     * Check if gs (ghostscript) is available.
     *
     * @todo Use Omeka Cli.
     */
    public function __invoke(): bool
    {
        // @link http://stackoverflow.com/questions/592620/check-if-a-program-exists-from-a-bash-script
        return !shell_exec('hash gs 2>&- || echo 1');
    }
}
