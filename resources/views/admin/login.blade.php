<x-layouts.admin-auth title="Masuk | SatSet Admin">
    <section class="admin-login-card">
        <div class="admin-login-head">
            <img class="admin-brand-mark admin-login-logo" src="{{ asset('images/logo-128.png') }}" alt="SatSet" width="48" height="48">
            <h1>Masuk ke SatSet Admin</h1>
            <p>Kelola outlet, antrean, dan komunikasi customer.</p>
        </div>
        <form class="admin-form" method="post" action="{{ route('admin.login.store') }}">
            @csrf
            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email') <small class="admin-error">{{ $message }}</small> @enderror
            </label>
            <label>
                Password
                <input type="password" name="password" autocomplete="current-password" required>
                @error('password') <small class="admin-error">{{ $message }}</small> @enderror
            </label>
            <button class="admin-button admin-button-primary admin-button-block" type="submit">Masuk</button>
        </form>
    </section>
</x-layouts.admin-auth>
