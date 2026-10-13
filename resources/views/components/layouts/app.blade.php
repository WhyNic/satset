<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0F172A">
    <title>{{ $title ?? 'SatSet' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/satset.css') }}">
</head>
<body>
    <div class="ambient-bg" aria-hidden="true"></div>
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark">S</span>
            <span>
                SatSet
                <small>Antre lebih cerdas</small>
            </span>
        </a>
        <nav>
            @auth
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="link-button" type="submit">Logout</button>
                </form>
            @else
                <a href="{{ route('admin.login') }}">Admin</a>
            @endauth
        </nav>
    </header>

    <main class="page">
        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
