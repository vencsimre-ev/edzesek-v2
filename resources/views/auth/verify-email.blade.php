@extends('layouts.app')
@section('title', 'Emailcím megerősítése | Edzések v2')
@section('content')
<h1 class="h3">Erősítsd meg az emailcímedet</h1>
<p>A folytatáshoz kattints a regisztrációkor küldött levélben lévő linkre. Ezután admin jóváhagyás szükséges.</p>
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="btn btn-primary">Megerősítő levél újraküldése</button>
</form>
@endsection
