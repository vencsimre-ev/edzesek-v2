@extends('layouts.app')
@section('title', 'Jelszó visszaállítása | '.config('app.public_name'))
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
    <h1 class="h3 mb-4">Jelszó visszaállítása</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-auth-input name="email" label="Emailcím" type="email" autocomplete="username" :value="$request->email" />
        <x-auth-input name="password" label="Új jelszó (legalább 8 karakter)" type="password" autocomplete="new-password" />
        <x-auth-input name="password_confirmation" label="Jelszó megerősítése" type="password" autocomplete="new-password" />
        <button class="btn btn-primary" type="submit">Új jelszó mentése</button>
    </form>
</div></div>
@endsection
