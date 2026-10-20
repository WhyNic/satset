<x-layouts.admin title="Kelola Restoran - SatSet">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Restaurant Management</p>
            <h1>Kelola restoran</h1>
            <p>Kelola outlet, kapasitas kelompok, dan akses QR.</p>
        </div>
        <a class="admin-primary-button" href="{{ route('admin.restaurants.create') }}">Tambah Restoran</a>
    </section>

    <section class="admin-panel">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Category</th>
                        <th>Mode</th>
                        <th>Maks. Tamu</th>
                        <th>Prefix</th>
                        <th>Average Service Time</th>
                        <th>Pendaftaran</th>
                        <th>Active</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($restaurants as $restaurant)
                        <tr>
                            <td><strong>{{ $restaurant->name }}</strong></td>
                            <td>{{ $restaurant->category }}</td>
                            <td>{{ $restaurant->isPickup() ? 'Ambil di counter' : 'Antrean meja' }}</td>
                            <td>{{ $restaurant->isPickup() ? '-' : $restaurant->max_party_size.' orang' }}</td>
                            <td>{{ $restaurant->prefix }}</td>
                            <td>{{ $restaurant->average_service_minutes }} menit</td>
                            <td>{{ $restaurant->queue_open ? 'Dibuka' : 'Ditutup' }}</td>
                            <td><span class="admin-status {{ $restaurant->active ? 'admin-status-waiting' : 'admin-status-cancelled' }}">{{ $restaurant->active ? 'Active' : 'Disabled' }}</span></td>
                            <td class="admin-inline-actions">
                                <a class="admin-table-action" href="{{ route('admin.restaurants.queue', $restaurant) }}">Kelola Antrean</a>
                                <a class="admin-table-action" href="{{ route('admin.qr.show', $restaurant) }}">QR Code</a>
                                <a class="admin-table-action" href="{{ route('admin.restaurants.edit', $restaurant) }}">Edit</a>
                                <form method="post" action="{{ route('admin.restaurants.destroy', $restaurant) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="admin-danger-link" type="submit">{{ $restaurant->active ? 'Disable' : 'Enable' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
