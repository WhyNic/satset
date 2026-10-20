<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2e6d6d">
    <link rel="icon" href="{{ asset('images/logo-128.png') }}">
    <title>{{ $title ?? 'Masuk | SatSet Admin' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=ui-8">
</head>
<body class="admin-login-body">
    {{ $slot }}
</body>
</html>
