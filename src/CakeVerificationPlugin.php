<?php
declare(strict_types=1);

namespace CakeVerification;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use CakeVerification\Command\InstallCommand;

final class CakeVerificationPlugin extends BasePlugin
{
    /**
     * Register console commands.
     *
     * @param \Cake\Console\CommandCollection $commands Commands
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('verification:install', InstallCommand::class);

        return $commands;
    }
}
