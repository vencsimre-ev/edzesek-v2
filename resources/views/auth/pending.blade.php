@extends('layouts.app')
@section('title', 'Jóváhagyásra vár | Edzések v2')
@section('content')
<h1 class="h3">A fiókod admin jóváhagyásra vár</h1>
<p>Az emailcímed már megerősítetted. A jóváhagyásról emailben értesítünk.</p>
<a href="{{ route('home') }}" class="btn btn-outline-primary">Állapot ellenőrzése</a>
<x-google-link />
@endsection
