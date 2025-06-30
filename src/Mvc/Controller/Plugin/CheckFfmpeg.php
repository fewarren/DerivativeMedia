<?php declare(strict_types=1);

namespace DerivativeMedia\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class CheckFfmpeg extends AbstractPlugin
{
    /**
     * Check if ffmpeg is available.
     *
     * @todo Use Omeka Cli.
     */
    public function __invoke(): bool
    {
        // @link http://stackoverflow.com/questions/592620/check-if-a-program-exists-from-a-bash-script
        return !shell_exec('hash ffmpeg 2>&- || echo 1');
    }
}
