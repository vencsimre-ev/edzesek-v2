<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Edzések v2')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    <header class="bg-dark text-white">
        <div class="container py-3 d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <a href="{{ url('/') }}" class="text-white text-decoration-none fs-4">
                Edzések v2
            </a>
            <nav aria-label="Fő navigáció" class="d-flex gap-3 align-items-center">
                <a href="{{ route('check-in.index') }}" class="text-white">Edzésre jöttem</a>
                @auth
                    <a href="{{ route('home') }}" class="text-white">Kezdőoldal</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Kijelentkezés</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-white">Belépés</a>
                    <a href="{{ route('register') }}" class="text-white">Regisztráció</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="@yield('main-class', 'container py-5')">
        @if(session('message'))
            <div class="alert alert-success" role="status">{{ session('message') }}</div>
        @endif
        @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') === 'verification-link-sent' ? 'Elküldtük az új megerősítő levelet.' : __(session('status')) }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
