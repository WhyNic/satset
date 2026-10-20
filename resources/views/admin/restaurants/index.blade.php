<x-layouts.admin title="Restoran | SatSet Admin">
    <header class="admin-page-header">
        <div>
            <h1>Restoran</h1>
            <p>Kelola outlet, kapasitas kelompok, dan pendaftaran antrean.</p>
        </div>
        <a class="admin-button admin-button-primary" href="{{ route('admin.restaurants.create') }}">Tambah restoran</a>
    </header>

    <section class="admin-panel">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Restoran</th>
                        <th>Prefix</th>
                        <th>Maks. tamu</th>
                        <th>Waktu layanan</th>
                        <th>Pendaftaran</th>
                        <th>Status</th>
                        <th class="admin-cell-action"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($restaurants as $restaurant)
                        <tr @class(['admin-row-muted' => ! $restaurant->active])>
                            <td><strong>{{ $restaurant->name }}</strong><span class="admin-sub">{{ $restaurant->category }}</span></td>
                            <td class="admin-mono">{{ $restaurant->prefix }}</td>
                            <td>{{ $restaurant->max_party_size }} orang</td>
                            <td>{{ $restaurant->average_service_minutes }} menit</td>
                            <td><span @class(['admin-badge', 'admin-badge-green' => $restaurant->queue_open, 'admin-badge-amber' => ! $restaurant->queue_open])>{{ $restaurant->queue_open ? 'Dibuka' : 'Ditutup' }}</span></td>
                            <td><span @class(['admin-badge', 'admin-badge-green' => $restaurant->active, 'admin-badge-grey' => ! $restaurant->active])>{{ $restaurant->active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="admin-cell-action">
                                <div class="admin-row-actions">
                                    <a class="admin-button admin-button-small" href="{{ route('admin.restaurants.queue', $restaurant) }}">Antrean</a>
                                    <a class="admin-text-button" href="{{ route('admin.qr.show', $restaurant) }}">QR</a>
                                    <a class="admin-text-button" href="{{ route('admin.restaurants.edit', $restaurant) }}">Edit</a>
                                    <form method="post" action="{{ route('admin.restaurants.destroy', $restaurant) }}" @if($restaurant->active) onsubmit="return confirm('Nonaktifkan {{ $restaurant->name }}? Customer tidak akan bisa mendaftar antrean.')" @endif>
                                        @csrf
                                        @method('DELETE')
                                        <button @class(['admin-text-button', 'admin-text-danger' => $restaurant->active]) type="submit">{{ $restaurant->active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="admin-empty">Belum ada restoran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
