<?php

namespace App\Jobs;

use App\Domain\Games\GameMetadata;
use App\Models\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Gegevens ophalen voor nieuwe games die Playscope net meldde (categorieën + spelers).
 * Draait na het antwoord aan Playscope (dispatchAfterResponse), dus zonder queue-worker.
 * Max. 40 per keer; de rest doet de backoffice-knop "Gegevens ophalen".
 */
class FetchGameMetadata implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $tenantId, public int $limit = 40)
    {
    }

    public function handle(GameMetadata $metadata): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        Game::query()
            ->where('tenant_id', $this->tenantId)
            ->where('source', 'steam')
            ->whereNull('metadata_fetched_at')
            ->orderBy('id')
            ->limit($this->limit)
            ->get()
            ->each(function (Game $game) use ($metadata) {
                try {
                    $metadata->enrich($game);
                } catch (Throwable) {
                    // volgende game
                }
                usleep(300_000);   // Steam: ±200 verzoeken per 5 minuten
            });
    }
}
