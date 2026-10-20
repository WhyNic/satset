<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b2118">
    <title>{{ $title ?? 'SatSet Admin' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <script src="{{ asset('js/admin.js') }}" defer></script>
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                <span>S</span>
                <strong>SatSet Admin</strong>
            </a>

            <nav class="admin-nav" aria-label="Admin navigation">
                <a @class(['active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a @class(['active' => request()->routeIs('admin.queues.*')]) href="{{ route('admin.queues.index') }}">Antrean</a>
                <a @class(['active' => request()->routeIs('admin.restaurants.*')]) href="{{ route('admin.restaurants.index') }}">Restoran</a>
                <a @class(['active' => request()->routeIs('admin.whatsapp-logs.*')]) href="{{ route('admin.whatsapp-logs.index') }}">WhatsApp Logs</a>
                <a @class(['active' => request()->routeIs('admin.qr.*')]) href="{{ route('admin.qr.index') }}">QR Code</a>
            </nav>

            <form method="post" action="{{ route('admin.logout') }}" class="admin-logout">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div>
                    <strong>Operational Console</strong>
                    <span>{{ now()->format('d M Y, H:i') }}</span>
                </div>
                <a href="{{ route('home') }}" target="_blank" rel="noopener">Lihat Customer Web</a>
            </header>

            <main class="admin-content">
                @if (session('status'))
                    <div class="admin-notice">{{ session('status') }}</div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
