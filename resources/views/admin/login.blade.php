<x-layouts.admin-auth title="Admin Login - SatSet">
    <section class="admin-login-card">
        <div class="admin-login-copy">
            <span class="admin-login-mark">S</span>
            <p class="admin-kicker">SatSet Admin</p>
            <h1>Masuk ke Dashboard</h1>
            <p>Kelola outlet, antrean, dan komunikasi customer.</p>
        </div>
        <form class="admin-form" method="post" action="{{ route('admin.login.store') }}">
            @csrf
            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
                @error('email') <small>{{ $message }}</small> @enderror
            </label>
            <label>
                Password
                <input type="password" name="password" autocomplete="current-password" required>
                @error('password') <small>{{ $message }}</small> @enderror
            </label>
            <button class="admin-primary-button" type="submit">Masuk ke Dashboard</button>
        </form>
    </section>
</x-layouts.admin-auth>
