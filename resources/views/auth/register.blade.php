@extends('layouts.app')
@section('title', 'Regisztráció | Edzések v2')
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
    @if(config('services.google.client_id') && config('services.google.client_secret'))
        <a href="{{ route('google.redirect') }}" class="btn btn-outline-primary mb-3">Folytatás Google-fiókkal</a>
    @endif
    <h1 class="h3 mb-4">Regisztráció</h1>
    <p>A hozzáféréshez email-megerősítés és admin jóváhagyás szükséges.</p>
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <x-auth-input name="name" label="Név" autocomplete="name" />
        <x-auth-input name="email" label="Emailcím" type="email" autocomplete="username" />
        <x-auth-input name="password" label="Jelszó (legalább 8 karakter)" type="password" autocomplete="new-password" />
        <x-auth-input name="password_confirmation" label="Jelszó megerősítése" type="password" autocomplete="new-password" />
        <button class="btn btn-primary" type="submit">Regisztráció</button>
    </form>
    <p class="mt-3"><a href="{{ route('login') }}">Már van fiókom</a></p>
</div></div>
@endsection
