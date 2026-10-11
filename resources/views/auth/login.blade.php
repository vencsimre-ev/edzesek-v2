@extends('layouts.app')
@section('title', 'Belépés | Edzések v2')
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
    @if(config('services.google.client_id') && config('services.google.client_secret'))
        <a href="{{ route('google.redirect') }}" class="btn btn-outline-primary mb-3">Folytatás Google-fiókkal</a>
    @endif
    <h1 class="h3 mb-4">Belépés</h1>
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <x-auth-input name="email" label="Emailcím" type="email" autocomplete="username" />
        <x-auth-input name="password" label="Jelszó" type="password" autocomplete="current-password" />
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
            <label class="form-check-label" for="remember">Maradjak belépve</label>
        </div>
        <button class="btn btn-primary" type="submit">Belépés</button>
    </form>
    <p class="mt-3"><a href="{{ route('password.request') }}">Elfelejtett jelszó</a></p>
    <p>Még nincs fiókod? <a href="{{ route('register') }}">Regisztráció</a></p>
</div></div>
@endsection
