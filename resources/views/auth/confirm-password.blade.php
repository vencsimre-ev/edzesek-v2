@extends('layouts.app')
@section('title', 'Jelszó megerősítése | '.config('app.public_name'))
@section('content')
<h1 class="h3">Jelszó megerősítése</h1>
<form method="POST" action="{{ route('password.confirm.store') }}">
    @csrf
    <x-auth-input name="password" label="Jelszó" type="password" autocomplete="current-password" />
    <button class="btn btn-primary" type="submit">Megerősítés</button>
</form>
@endsection
