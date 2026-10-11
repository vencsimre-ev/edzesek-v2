<?php

namespace App\Services;

use App\Actions\AuthenticateWithGoogle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthentication
{
    public function redirect(Request $request, bool $link = false): RedirectResponse
    {
        $request->session()->put('google.intent', $link ? $request->user()->id : 'login');

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request, AuthenticateWithGoogle $authenticate): RedirectResponse
    {
        $intent = $request->session()->pull('google.intent');
        if (! $intent || ($intent === 'login' && $request->user())
            || ($intent !== 'login' && (! $request->user() || $request->user()->id !== $intent))) {
            throw ValidationException::withMessages(['google' => 'A Google-belépést vagy összekapcsolást indítsd újra ebből a böngészőből.']);
        }

        // Keep Socialite's session state verification enabled.
        $google = Socialite::driver('google')->user();
        $user = $authenticate->authenticate($google, $intent === 'login' ? null : $request->user());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('message', $intent === 'login'
            ? 'Sikeres Google-belépés.' : 'A Google-fiók összekapcsolva.');
    }
}
