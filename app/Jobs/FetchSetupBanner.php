<?php

namespace App\Jobs;

use App\Domain\Games\GameArtwork;
use App\Models\Setup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Banner zoeken voor een speelplek na aanmaken of aanpassen (naam, type, spellen, MAME-romnaam).
 * Draait na het antwoord aan de backoffice (dispatchAfterResponse), dus zonder queue-worker.
 */
class FetchSetupBanner implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $setupId)
    {
    }

    public function handle(GameArtwork $artwork): void
    {
        @set_time_limit(120);
        @ignore_user_abort(true);

        $setup = Setup::query()->find($this->setupId);
        if (! $setup) {
            return;
        }

        try {
            $artwork->autoSetup($setup, force: true);
        } catch (Throwable) {
            // volgende keer opnieuw (nachtelijke run of knop "Alles automatisch")
        }
    }
}
