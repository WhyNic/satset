<x-layouts.admin title="QR Code | SatSet Admin">
    <header class="admin-page-header">
        <div>
            <h1>QR Code outlet</h1>
            <p>Cetak dan tempel di outlet. QR mengarah langsung ke halaman antrean outlet tersebut.</p>
        </div>
    </header>

    <section class="admin-panel">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Restoran</th>
                        <th>Alamat halaman antrean</th>
                        <th>Pendaftaran</th>
                        <th class="admin-cell-action"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($restaurants as $restaurant)
                        <tr>
                            <td><strong>{{ $restaurant->name }}</strong><span class="admin-sub">{{ $restaurant->category }}</span></td>
                            <td class="admin-mono admin-url">{{ $baseUrl.route('outlets.show', $restaurant->slug, false) }}</td>
                            <td><span @class(['admin-badge', 'admin-badge-green' => $restaurant->active && $restaurant->queue_open, 'admin-badge-amber' => ! ($restaurant->active && $restaurant->queue_open)])>{{ $restaurant->active && $restaurant->queue_open ? 'Dibuka' : 'Ditutup' }}</span></td>
                            <td class="admin-cell-action"><a class="admin-button admin-button-small" href="{{ route('admin.qr.show', $restaurant) }}">Lihat QR</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="admin-empty">Tambahkan restoran untuk membuat QR.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
