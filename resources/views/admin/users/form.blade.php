@php
    $isSelf = $user->exists && auth()->user()->is($user);
    $role = old('role', $user->role);
    $selected = collect(old('restaurants', $user->exists ? $user->restaurants->modelKeys() : []))->map(fn ($id) => (int) $id);
@endphp
<x-layouts.admin :title="($user->exists ? 'Edit akun' : 'Tambah akun').' | SatSet Admin'">
    <header class="admin-page-header">
        <div>
            <a class="admin-back" href="{{ route('admin.users.index') }}">&larr; Akun admin</a>
            <h1>{{ $user->exists ? 'Edit '.$user->name : 'Tambah akun admin' }}</h1>
        </div>
    </header>

    <form class="admin-form admin-form-page" method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" data-user-form>
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <section class="admin-panel admin-fieldset">
            <div class="admin-fieldset-head">
                <h2>Data akun</h2>
            </div>
            <div class="admin-form-grid">
                <label>Nama
                    <input name="name" value="{{ old('name', $user->name) }}" placeholder="Contoh: Kasir Pagi – Ayam Bakar" required>
                    @error('name')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>Email (untuk login)
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="off" required>
                    @error('email')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>{{ $user->exists ? 'Password baru' : 'Password' }}
                    <input type="password" name="password" autocomplete="new-password" @required(! $user->exists)>
                    <small class="admin-hint">{{ $user->exists ? 'Kosongkan jika tidak ingin mengganti. ' : '' }}Minimal 8 karakter.</small>
                    @error('password')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>Ulangi password
                    <input type="password" name="password_confirmation" autocomplete="new-password" @required(! $user->exists)>
                </label>
            </div>
        </section>

        <section class="admin-panel admin-fieldset">
            <div class="admin-fieldset-head">
                <h2>Peran dan akses</h2>
                @if ($isSelf)<p>Anda tidak bisa mengubah peran atau menonaktifkan akun Anda sendiri.</p>@endif
            </div>
            <div class="admin-choice-group" role="radiogroup" aria-label="Peran">
                @foreach (\App\Models\User::ROLE_LABELS as $value => $label)
                    <label class="admin-choice">
                        <input type="radio" name="role" value="{{ $value }}" @checked($role === $value) @disabled($isSelf) data-role-input>
                        <span>
                            <strong>{{ $label }}</strong>
                            <small>{{ $value === 'owner' ? 'Akses penuh: semua outlet, akun admin, dan log WhatsApp.' : 'Hanya mengelola antrean dan pengaturan outlet yang dipilih.' }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($isSelf)<input type="hidden" name="role" value="{{ $user->role }}">@endif
            @error('role')<small class="admin-error">{{ $message }}</small>@enderror

            <fieldset class="admin-outlet-picker" data-outlet-picker @if($role === 'owner') hidden @endif>
                <legend>Outlet yang dikelola</legend>
                <div class="admin-checkbox-grid">
                    @forelse ($restaurants as $restaurant)
                        <label class="admin-switch">
                            <input type="checkbox" name="restaurants[]" value="{{ $restaurant->id }}" @checked($selected->contains($restaurant->id))>
                            <span><strong>{{ $restaurant->name }}</strong>@unless($restaurant->active)<small>Nonaktif</small>@endunless</span>
                        </label>
                    @empty
                        <p class="admin-hint">Belum ada outlet. Tambahkan restoran terlebih dahulu.</p>
                    @endforelse
                </div>
                @error('restaurants')<small class="admin-error">{{ $message }}</small>@enderror
            </fieldset>

            <input type="hidden" name="active" value="{{ $isSelf ? 1 : 0 }}">
            <label class="admin-switch">
                <input type="checkbox" name="active" value="1" @checked(old('active', $user->active ?? true)) @disabled($isSelf)>
                <span><strong>Akun aktif</strong><small>Akun nonaktif tidak bisa login dan langsung keluar dari sesi yang sedang berjalan.</small></span>
            </label>
        </section>

        <div class="admin-form-footer">
            <a class="admin-button admin-button-ghost" href="{{ route('admin.users.index') }}">Batal</a>
            <button class="admin-button admin-button-primary" type="submit">{{ $user->exists ? 'Simpan perubahan' : 'Buat akun' }}</button>
        </div>
    </form>
</x-layouts.admin>
