@extends('layouts.app')
@section('title', 'Kezdőoldal | Edzések v2')
@section('content')
<h1 class="h3">Üdvözlünk, {{ auth()->user()->name }}!</h1>
<p>A fiókod megerősített és jóváhagyott.</p>
@if(auth()->user()->isAdmin())
    <a class="btn btn-primary" href="{{ route('admin.training-sessions.index') }}">Edzések</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.participants.index') }}">Résztvevők</a>
    <a class="btn btn-primary" href="{{ route('admin.approvals.index') }}">Jóváhagyásra váró felhasználók</a>
@endif
<x-google-link />
@endsection
