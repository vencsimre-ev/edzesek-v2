@extends('layouts.app')
@section('title', 'Elfelejtett jelszó | '.config('app.public_name'))
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
    <h1 class="h3 mb-4">Elfelejtett jelszó</h1>
    <p>Küldünk egy linket, amellyel új jelszót állíthatsz be.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-auth-input name="email" label="Emailcím" type="email" autocomplete="username" />
        <button class="btn btn-primary" type="submit">Visszaállító link küldése</button>
    </form>
    <p class="mt-3"><a href="{{ route('login') }}">Vissza a belépéshez</a></p>
</div></div>
@endsection
