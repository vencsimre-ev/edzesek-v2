@extends('layouts.app')
@section('title', 'Jóváhagyásra vár | '.config('app.public_name'))
@section('content')
<h1 class="h3">A fiókod admin jóváhagyásra vár</h1>
<p>Az emailcímed már megerősítetted. A jóváhagyásról emailben értesítünk.</p>
<a href="{{ route('approval.pending') }}" class="btn btn-outline-primary">Állapot ellenőrzése</a>
<x-google-link />
@endsection
