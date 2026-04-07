<?php

namespace App\Console;

use App\Console\Commands\ImportLegacyMembersCommand;
use App\Console\Commands\ImportLoyverseCommand;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ImportLoyverseCommand::class,
        ImportLegacyMembersCommand::class,
    ];
}
