<?php
declare(strict_types=1);

namespace Verification;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Verification\Command\InstallCommand;

final class VerificationPlugin extends BasePlugin
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
