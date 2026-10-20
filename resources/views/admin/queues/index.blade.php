<x-layouts.admin title="Antrean | SatSet Admin">
    <header class="admin-page-header">
        <div>
            <h1>Semua antrean</h1>
            <p>Riwayat dan antrean aktif dari seluruh outlet.</p>
        </div>
    </header>

    <section class="admin-panel">
        <form class="admin-filter-bar" method="get" action="{{ route('admin.queues.index') }}">
            <label class="sr-only" for="queue-search">Cari antrean</label>
            <input id="queue-search" type="search" name="q" value="{{ $search }}" placeholder="Cari nomor antrean atau nomor WhatsApp">
            <label class="sr-only" for="queue-status">Status</label>
            <select id="queue-status" name="status">
                <option value="all" @selected($status === 'all')>Semua status</option>
                @foreach (\App\Models\QueueTicket::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="admin-button admin-button-primary" type="submit">Terapkan</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Restoran</th>
                        <th>Jumlah tamu</th>
                        <th>WhatsApp</th>
                        <th>Waktu masuk</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="admin-mono"><strong>{{ $ticket->queue_number }}</strong></td>
                            <td>
                                @if ($ticket->restaurant)
                                    <a class="admin-link" href="{{ route('admin.restaurants.queue', $ticket->restaurant) }}">{{ $ticket->restaurant->name }}</a>
                                @else
                                    –
                                @endif
                            </td>
                            <td>{{ $ticket->party_size }} orang</td>
                            <td class="admin-mono">{{ $ticket->phone_number ?? '–' }}</td>
                            <td>{{ $ticket->joined_at?->format('d M, H:i') ?? '–' }}</td>
                            <td><span class="admin-badge admin-status-{{ $ticket->status }}">{{ $ticket->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-empty">Tidak ada antrean yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">{{ $tickets->links() }}</div>
    </section>
</x-layouts.admin>
