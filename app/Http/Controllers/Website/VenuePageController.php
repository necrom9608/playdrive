<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VenuePageController extends Controller
{
    /**
     * Toont de publieke venuepagina op /venues/{slug}.
     *
     * Tenants zijn enkel publiek zichtbaar als public_status = 'live' en
     * public_slug ingevuld is. Anders 404 om geen drafts te tonen.
     */
    public function show(string $slug)
    {
        $tenant = Tenant::publiclyVisible()
            ->where('public_slug', $slug)
            ->with(['photos', 'links', 'activities', 'amenities'])
            ->first();

        if (! $tenant) {
            throw new NotFoundHttpException();
        }

        return view('venue.show', [
            'tenant' => $tenant,
        ]);
    }
}
