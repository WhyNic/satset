<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2e6d6d">
    <link rel="icon" href="{{ asset('images/logo-128.png') }}">
    <title>{{ $title ?? 'SatSet Admin' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=ui-8">
    <script src="{{ asset('js/admin.js') }}?v=ui-8" defer></script>
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                <img class="admin-brand-mark" src="{{ asset('images/logo-128.png') }}" alt="" width="34" height="34">
                <span>SatSet <small>Admin</small></span>
            </a>

            <nav class="admin-nav" aria-label="Menu admin">
                @can('owner')
                    <a @class(['active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Dashboard</a>
                @else
                    <a @class(['active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Outlet saya</a>
                @endcan
                <a @class(['active' => request()->routeIs('admin.queues.*', 'admin.restaurants.queue')]) href="{{ route('admin.queues.index') }}">Riwayat antrean</a>
                @can('owner')
                    <a @class(['active' => request()->routeIs('admin.restaurants.index', 'admin.restaurants.create', 'admin.restaurants.edit')]) href="{{ route('admin.restaurants.index') }}">Restoran</a>
                @endcan
                <a @class(['active' => request()->routeIs('admin.qr.*')]) href="{{ route('admin.qr.index') }}">QR Code</a>
                @can('owner')
                    <a @class(['active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">Akun admin</a>
                    <a @class(['active' => request()->routeIs('admin.whatsapp-logs.*')]) href="{{ route('admin.whatsapp-logs.index') }}">Log WhatsApp</a>
                @endcan
            </nav>

            <form method="post" action="{{ route('admin.logout') }}" class="admin-logout">
                @csrf
                <span><strong>{{ auth()->user()?->name }}</strong>{{ auth()->user()?->roleLabel() }}</span>
                <button type="submit">Keluar</button>
            </form>
        </aside>

        <main class="admin-content">
            @if (session('status'))
                <div class="admin-notice" role="status">{{ session('status') }}</div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
