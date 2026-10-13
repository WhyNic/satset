<x-layouts.admin title="Restoran - SatSet">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Restaurant Management</p>
            <h1>{{ $restaurant->exists ? 'Edit restaurant' : 'Create restaurant' }}</h1>
            <p>Atur data tenant, prefix nomor antrean, dan threshold estimasi tunggu.</p>
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
                <label>Average Service Time <input type="number" name="average_service_minutes" min="1" value="{{ old('average_service_minutes', $restaurant->average_service_minutes ?? 5) }}" required>@error('average_service_minutes')<small>{{ $message }}</small>@enderror</label>
                <label>Max Wait Time <input type="number" name="max_wait_minutes" min="1" value="{{ old('max_wait_minutes', $restaurant->max_wait_minutes ?? 30) }}" required>@error('max_wait_minutes')<small>{{ $message }}</small>@enderror</label>
            </div>
            <label class="admin-checkbox"><input type="checkbox" name="active" value="1" @checked(old('active', $restaurant->active ?? true))> Active</label>
            <button class="admin-primary-button" type="submit">Simpan</button>
        </form>
    </section>
</x-layouts.admin>
