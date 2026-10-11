<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoogleConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'),
            503, 'A Google-belépés még nincs konfigurálva.');

        return $next($request);
    }
}
