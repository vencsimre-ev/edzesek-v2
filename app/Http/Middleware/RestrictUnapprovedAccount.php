<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictUnapprovedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->hasVerifiedEmail() || ! $user->isApproved())
            && ! $request->routeIs('logout', 'verification.*')) {
            return redirect()->route($user->hasVerifiedEmail() ? 'approval.pending' : 'verification.notice');
        }

        return $next($request);
    }
}
