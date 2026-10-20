<x-layouts.admin title="Dashboard | SatSet Admin">
    <header class="admin-page-header">
        <div>
            <h1>{{ auth()->user()->isOwner() ? 'Dashboard' : 'Outlet saya' }}</h1>
            <p>Ringkasan operasional hari ini, {{ now()->translatedFormat('d M Y') }}.</p>
        </div>
        @can('owner')
            <a class="admin-button admin-button-secondary" href="{{ route('admin.restaurants.create') }}">Tambah restoran</a>
        @endcan
    </header>

    @if (! auth()->user()->isOwner() && $restaurants->isEmpty())
        <div class="admin-notice admin-notice-warning">Akun Anda belum terhubung ke outlet mana pun. Hubungi pemilik SatSet untuk mendapatkan akses.</div>
    @endif

    <section class="admin-stats">
        <article><span>Total restoran</span><strong>{{ $totalRestaurants }}</strong></article>
        <article><span>Antrean aktif</span><strong>{{ $activeQueue }}</strong></article>
        <article><span>Tamu hari ini</span><strong>{{ $customersToday }}</strong></article>
        <article><span>Rata-rata tunggu</span><strong>{{ $averageWaiting }} <small>menit</small></strong></article>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div>
                <h2>Status restoran</h2>
                <p>Kondisi antrean di setiap outlet.</p>
            </div>
            <a class="admin-link" href="{{ route('admin.queues.index') }}">Lihat semua antrean</a>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Restoran</th>
                        <th>Pendaftaran</th>
                        <th>Sedang dilayani</th>
                        <th>Menunggu</th>
                        <th>Estimasi tunggu</th>
                        <th class="admin-cell-action"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($restaurants as $restaurant)
                        @php($snapshot = $restaurant->getAttribute('queue_snapshot'))
                        <tr>
                            <td><strong>{{ $restaurant->name }}</strong><span class="admin-sub">{{ $restaurant->category }}</span></td>
                            <td><span @class(['admin-badge', 'admin-badge-green' => $snapshot['available'], 'admin-badge-amber' => ! $snapshot['available']])>{{ $snapshot['available'] ? 'Dibuka' : 'Ditutup' }}</span></td>
                            <td class="admin-mono">{{ $restaurant->getAttribute('current_number') ?? '–' }}</td>
                            <td>{{ $restaurant->getAttribute('waiting_count') }} kelompok</td>
                            <td>{{ $snapshot['estimated_wait'] }} menit</td>
                            <td class="admin-cell-action"><a class="admin-button admin-button-small" href="{{ route('admin.restaurants.queue', $restaurant) }}">Kelola antrean</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-empty">Belum ada restoran.@can('owner') <a class="admin-link" href="{{ route('admin.restaurants.create') }}">Tambah restoran pertama</a>.@endcan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
