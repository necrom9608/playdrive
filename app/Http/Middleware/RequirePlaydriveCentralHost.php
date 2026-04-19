<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlaydriveCentralHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower((string) $request->getHost());
        $allowedHosts = $this->allowedHosts();

        if ($allowedHosts !== [] && ! in_array($host, $allowedHosts, true)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function allowedHosts(): array
    {
        $configured = (string) env('PLAYDRIVE_ADMIN_ALLOWED_HOSTS', '');

        $hosts = collect(explode(',', $configured))
            ->map(fn (string $host) => strtolower(trim($host)))
            ->filter()
            ->values();

        if ($hosts->isNotEmpty()) {
            return $hosts->all();
        }

        $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $appUrlHost ? [strtolower($appUrlHost)] : [];
    }
}
