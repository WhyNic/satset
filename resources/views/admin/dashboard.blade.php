<x-layouts.admin title="Admin Dashboard - SatSet">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Dashboard SatSet</p>
            <h1>Ringkasan operasional</h1>
        </div>
        <a class="admin-secondary-button" href="{{ route('admin.restaurants.create') }}">Tambah Restoran</a>
    </section>

    <section class="admin-stat-grid">
        <article>
            <span>Total Restoran</span>
            <strong>{{ $totalRestaurants }}</strong>
        </article>
        <article>
            <span>Antrean Aktif</span>
            <strong>{{ $activeQueue }}</strong>
        </article>
        <article>
            <span>Tamu Hari Ini</span>
            <strong>{{ $customersToday }}</strong>
        </article>
        <article>
            <span>Rata-rata Tunggu</span>
            <strong>{{ $averageWaiting }} menit</strong>
        </article>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div>
                <h2>Status Restoran</h2>
                <p>Kondisi antrean seluruh outlet.</p>
            </div>
            <a href="{{ route('admin.queues.index') }}">Lihat semua antrean</a>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Restoran</th>
                        <th>Status</th>
                        <th>Sedang Dilayani</th>
                        <th>Jumlah Menunggu</th>
                        <th>Estimasi Tunggu</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($restaurants as $restaurant)
                        @php($snapshot = $restaurant->getAttribute('queue_snapshot'))
                        <tr>
                            <td>
                                <strong>{{ $restaurant->name }}</strong>
                                <span>{{ $restaurant->category }}</span>
                            </td>
                            <td><span class="admin-status {{ $snapshot['available'] ? 'admin-status-waiting' : 'admin-status-cancelled' }}">{{ $snapshot['available'] ? 'Dibuka' : 'Ditutup' }}</span></td>
                            <td>{{ $restaurant->getAttribute('current_number') ?? '-' }}</td>
                            <td>{{ $restaurant->getAttribute('waiting_count') }} kelompok</td>
                            <td>{{ $snapshot['estimated_wait'] }} menit</td>
                            <td><a class="admin-table-action" href="{{ route('admin.restaurants.queue', $restaurant) }}">Kelola Antrean</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
