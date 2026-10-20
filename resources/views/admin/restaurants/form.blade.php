@php
    $isOwner = auth()->user()->isOwner();
    $backUrl = $isOwner ? route('admin.restaurants.index') : route('admin.restaurants.queue', $restaurant);
@endphp
<x-layouts.admin :title="($restaurant->exists ? 'Edit restoran' : 'Tambah restoran').' | SatSet Admin'">
    <header class="admin-page-header">
        <div>
            <a class="admin-back" href="{{ $backUrl }}">&larr; {{ $isOwner ? 'Restoran' : 'Antrean' }}</a>
            <h1>{{ $restaurant->exists ? 'Edit '.$restaurant->name : 'Tambah restoran' }}</h1>
        </div>
    </header>

    <form class="admin-form admin-form-page" method="post" action="{{ $restaurant->exists ? route('admin.restaurants.update', $restaurant) : route('admin.restaurants.store') }}">
        @csrf
        @if ($restaurant->exists)
            @method('PUT')
        @endif

        <section class="admin-panel admin-fieldset">
            <div class="admin-fieldset-head">
                <h2>Informasi outlet</h2>
                <p>Ditampilkan ke customer di halaman outlet.</p>
            </div>
            <div class="admin-form-grid">
                <label>Nama restoran
                    <input name="name" value="{{ old('name', $restaurant->name) }}" required @disabled(! $isOwner)>
                    @unless($isOwner)<small class="admin-hint">Hanya bisa diubah pemilik SatSet.</small>@endunless
                    @error('name')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>Kategori
                    <input name="category" value="{{ old('category', $restaurant->category) }}" placeholder="Contoh: Ayam, Kopi, Dessert" required>
                    @error('category')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
            </div>
            <label>Deskripsi
                <textarea name="description" rows="3">{{ old('description', $restaurant->description) }}</textarea>
                @error('description')<small class="admin-error">{{ $message }}</small>@enderror
            </label>
            <label>Foto
                <input name="image" value="{{ old('image', $restaurant->image) }}" placeholder="https://... atau images/restaurants/nama.jpg">
                <small class="admin-hint">Opsional. Gunakan URL HTTPS atau path di folder public/images.</small>
                @error('image')<small class="admin-error">{{ $message }}</small>@enderror
            </label>
        </section>

        <section class="admin-panel admin-fieldset">
            <div class="admin-fieldset-head">
                <h2>Pengaturan antrean</h2>
                <p>Dipakai untuk membuat nomor antrean dan menghitung estimasi tunggu.</p>
            </div>
            <div class="admin-form-grid">
                <label>Prefix nomor antrean
                    <input name="prefix" value="{{ old('prefix', $restaurant->prefix) }}" maxlength="8" required @disabled(! $isOwner)>
                    <small class="admin-hint">{{ $isOwner ? 'Contoh: A menghasilkan nomor A-001. Tidak bisa diubah setelah ada antrean.' : 'Hanya bisa diubah pemilik SatSet.' }}</small>
                    @error('prefix')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>Jumlah tamu maksimal per kelompok
                    <input type="number" name="max_party_size" min="1" max="100" value="{{ old('max_party_size', $restaurant->max_party_size ?? 6) }}" required>
                    @error('max_party_size')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>Rata-rata waktu layanan (menit)
                    <input type="number" name="average_service_minutes" min="1" max="120" value="{{ old('average_service_minutes', $restaurant->average_service_minutes ?? 5) }}" required>
                    <small class="admin-hint">Waktu rata-rata untuk melayani satu kelompok.</small>
                    @error('average_service_minutes')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
                <label>Batas waktu tunggu (menit)
                    <input type="number" name="max_wait_minutes" min="1" max="240" value="{{ old('max_wait_minutes', $restaurant->max_wait_minutes ?? 30) }}" required>
                    @error('max_wait_minutes')<small class="admin-error">{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        <section class="admin-panel admin-fieldset">
            <div class="admin-fieldset-head">
                <h2>Status</h2>
            </div>
            @if ($isOwner)
                <input type="hidden" name="active" value="0">
                <label class="admin-switch">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $restaurant->active ?? true))>
                    <span><strong>Restoran aktif</strong><small>Restoran nonaktif tidak menerima antrean baru.</small></span>
                </label>
            @endif
            <input type="hidden" name="queue_open" value="0">
            <label class="admin-switch">
                <input type="checkbox" name="queue_open" value="1" @checked(old('queue_open', $restaurant->queue_open ?? true))>
                <span><strong>Buka pendaftaran antrean</strong><small>Matikan sementara saat outlet penuh atau tutup.</small></span>
            </label>
        </section>

        <div class="admin-form-footer">
            <a class="admin-button admin-button-ghost" href="{{ $backUrl }}">Batal</a>
            <button class="admin-button admin-button-primary" type="submit">{{ $restaurant->exists ? 'Simpan perubahan' : 'Tambah restoran' }}</button>
        </div>
    </form>
</x-layouts.admin>
