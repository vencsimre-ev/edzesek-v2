<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Edzések v2')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="bg-dark text-white">
        <div class="container py-3">
            <a href="{{ url('/') }}" class="text-white text-decoration-none fs-4">
                Edzések v2
            </a>
        </div>
    </header>

    <main class="container py-5">
        @yield('content')
    </main>
</body>
</html>