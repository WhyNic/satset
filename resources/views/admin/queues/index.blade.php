<x-layouts.admin title="Antrean - SatSet Admin">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Admin Queue Overview</p>
            <h1>Seluruh antrean</h1>
            <p>Riwayat kelompok dan antrean aktif dari seluruh outlet.</p>
        </div>
    </section>

    <section class="admin-panel admin-queue-overview">
        <form class="admin-filter-bar" method="get" action="{{ route('admin.queues.index') }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search queue number or customer">
            <select name="status">
                @foreach (['all' => 'All', 'waiting' => 'Waiting', 'called' => 'Called', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'skipped' => 'Skipped'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="admin-primary-button" type="submit">Filter</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Restoran</th>
                        <th>Jumlah Tamu</th>
                        <th>Kontak</th>
                        <th>Waktu Masuk</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><strong>{{ $ticket->queue_number }}</strong></td>
                            <td>{{ $ticket->restaurant?->name ?? '-' }}</td>
                            <td>{{ $ticket->party_size }} orang</td>
                            <td>{{ $ticket->maskedPhoneNumber() ?? $ticket->maskedEmail() ?? 'Tidak tersedia' }}</td>
                            <td>{{ $ticket->joined_at?->format('d M H:i') ?? '-' }}</td>
                            <td><span class="admin-status admin-status-{{ $ticket->status }}">{{ ucfirst($ticket->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Tidak ada ticket yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">{{ $tickets->links() }}</div>
    </section>
</x-layouts.admin>
