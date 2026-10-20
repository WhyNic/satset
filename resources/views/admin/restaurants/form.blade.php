<x-layouts.admin title="Restoran - SatSet">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Restaurant Management</p>
            <h1>{{ $restaurant->exists ? 'Edit restoran' : 'Tambah restoran' }}</h1>
            <p>Pengaturan outlet dan pendaftaran antrean.</p>
        </div>
        <a class="admin-secondary-button" href="{{ route('admin.restaurants.index') }}">Kembali</a>
    </section>

    <section class="admin-panel admin-form-panel">
        <form class="admin-form" method="post" action="{{ $restaurant->exists ? route('admin.restaurants.update', $restaurant) : route('admin.restaurants.store') }}">
            @csrf
            @if ($restaurant->exists)
                @method('PUT')
            @endif

            <div class="admin-form-grid">
                <label>Nama <input name="name" value="{{ old('name', $restaurant->name) }}" required>@error('name')<small>{{ $message }}</small>@enderror</label>
                <label>Category <input name="category" value="{{ old('category', $restaurant->category) }}" required>@error('category')<small>{{ $message }}</small>@enderror</label>
            </div>
            <label>Description <textarea name="description" rows="4">{{ old('description', $restaurant->description) }}</textarea>@error('description')<small>{{ $message }}</small>@enderror</label>
            <label>Image URL <input name="image" value="{{ old('image', $restaurant->image) }}">@error('image')<small>{{ $message }}</small>@enderror</label>
            <div class="admin-form-grid three">
                <label>Prefix <input name="prefix" value="{{ old('prefix', $restaurant->prefix) }}" required>@error('prefix')<small>{{ $message }}</small>@enderror</label>
                <label>Rata-rata waktu layanan per tamu atau pesanan (menit) <input type="number" name="average_service_minutes" min="1" value="{{ old('average_service_minutes', $restaurant->average_service_minutes ?? 5) }}" required>@error('average_service_minutes')<small>{{ $message }}</small>@enderror</label>
                <label>Max Wait Time <input type="number" name="max_wait_minutes" min="1" value="{{ old('max_wait_minutes', $restaurant->max_wait_minutes ?? 30) }}" required>@error('max_wait_minutes')<small>{{ $message }}</small>@enderror</label>
            </div>
            <input type="hidden" name="active" value="0">
            <label class="admin-checkbox"><input type="checkbox" name="active" value="1" @checked(old('active', $restaurant->active ?? true))> Active</label>
            <label>Mode antrean
                <select name="queue_mode" required>
                    <option value="seating" @selected(old('queue_mode', $restaurant->queue_mode ?? 'seating') === 'seating')>Antrean meja (ada kursi, customer mengisi jumlah tamu)</option>
                    <option value="pickup" @selected(old('queue_mode', $restaurant->queue_mode ?? 'seating') === 'pickup')>Ambil di counter (tanpa kursi, customer mengambil pesanan)</option>
                </select>
                @error('queue_mode')<small>{{ $message }}</small>@enderror
            </label>
            <label>Jumlah tamu maksimal per kelompok (hanya untuk antrean meja) <input type="number" name="max_party_size" min="1" max="100" value="{{ old('max_party_size', $restaurant->max_party_size ?? 6) }}" required>@error('max_party_size')<small>{{ $message }}</small>@enderror</label>
            <input type="hidden" name="queue_open" value="0">
            <label class="admin-checkbox"><input type="checkbox" name="queue_open" value="1" @checked(old('queue_open', $restaurant->queue_open ?? true))> Buka pendaftaran antrean</label>
            <button class="admin-primary-button" type="submit">Simpan</button>
        </form>
    </section>
</x-layouts.admin>
