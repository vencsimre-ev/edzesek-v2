<?php

namespace App\Http\Controllers\Web;

use App\Actions\AuthenticateWithGoogle;
use App\Http\Controllers\Controller;
use App\Services\GoogleAuthentication;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleAuthentication $google): RedirectResponse
    {
        return $google->redirect($request);
    }

    public function link(Request $request, GoogleAuthentication $google): RedirectResponse
    {
        return $google->redirect($request, true);
    }

    public function callback(Request $request, GoogleAuthentication $google, AuthenticateWithGoogle $authenticate): RedirectResponse
    {
        try {
            return $google->callback($request, $authenticate);
        } catch (InvalidStateException|GuzzleException) {
            $errors = ['google' => 'A Google-belépés nem sikerült vagy lejárt. Próbáld újra.'];
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        return redirect()->route($request->user() ? 'home' : 'login')->withErrors($errors);
    }
}
