<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isApproved()) {
            return $request->expectsJson()
                ? abort(403, 'Admin jóváhagyás szükséges.')
                : redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
