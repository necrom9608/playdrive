<?php

namespace App\Http\Controllers\Website;

use App\Domain\Setups\SetupTypes;
use App\Http\Controllers\Controller;
use App\Models\Setup;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Doel van de QR-code op een speelplek: /s/{code}.
 *
 * Nu: landingspagina met venue + speelplek. Volgende stap (highscores) hangt hier
 * de acties aan (score indienen, leaderboard bekijken).
 */
class SetupLandingController extends Controller
{
    public function show(string $code)
    {
        $setup = $this->findSetup($code);

        return view('setup.show', [
            'setup'     => $setup,
            'tenant'    => $setup->tenant,
            'typeLabel' => SetupTypes::label($setup->type),
            'venueUrl'  => ($setup->tenant->public_status === 'live' && $setup->tenant->public_slug)
                ? url('/venues/' . $setup->tenant->public_slug)
                : null,
        ]);
    }

    /** Zelfde info als JSON — voor de member-app (QR scannen in de app). */
    public function json(string $code): JsonResponse
    {
        $setup = $this->findSetup($code);
        $tenant = $setup->tenant;

        return response()->json([
            'setup' => [
                'id'          => $setup->id,
                'name'        => $setup->name,
                'type'        => $setup->type,
                'type_label'  => SetupTypes::label($setup->type),
                'zone'        => $setup->zone,
                'description' => $setup->description,
                'games'       => $setup->games ?? [],
                'max_players' => $setup->max_players,
                'status'      => $setup->status,
                'qr_code'     => $setup->qr_code,
            ],
            'tenant' => [
                'id'           => $tenant->id,
                'display_name' => $tenant->display_name,
                'logo_url'     => $tenant->logo_url,
                'public_slug'  => $tenant->public_status === 'live' ? $tenant->public_slug : null,
            ],
        ]);
    }

    private function findSetup(string $code): Setup
    {
        $setup = Setup::query()
            ->where('qr_code', strtolower(trim($code)))
            ->where('status', '!=', Setup::STATUS_ARCHIVED)
            ->with('tenant')
            ->first();

        if (! $setup || ! $setup->tenant?->is_active) {
            throw new NotFoundHttpException();
        }

        return $setup;
    }
}
