<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->configurePurchaseSignLimits();
    }

    /**
     * Limieten voor de publieke ondertekenpagina (/borderel/{token}).
     *
     * Een gewone `throttle:10,1` deelt zijn teller met álle andere throttled routes van
     * hetzelfde IP (bv. de banners op de frontdesk). Aan de balie zit de verkoper op hetzelfde
     * netwerk als de kassa, dus die teller was meteen vol. Benoemde limieten tellen apart,
     * per link en per IP.
     */
    private function configurePurchaseSignLimits(): void
    {
        $key = fn (Request $request, string $scope) => 'borderel|' . $scope . '|' . $request->route('token') . '|' . $request->ip();

        RateLimiter::for('purchase-sign-view', fn (Request $request) => Limit::perMinute(120)->by($key($request, 'view')));

        RateLimiter::for('purchase-sign-files', fn (Request $request) => Limit::perMinute(600)->by($key($request, 'files')));

        RateLimiter::for('purchase-sign-submit', fn (Request $request) => Limit::perMinute(30)
            ->by($key($request, 'submit'))
            ->response(fn () => response()->json([
                'message' => 'Even te veel pogingen. Wacht een minuutje en probeer dan opnieuw.',
            ], 429)));
    }
}
