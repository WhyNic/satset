<x-layouts.admin :title="'Antrean '.$restaurant->name.' | SatSet Admin'">
    <header class="admin-page-header">
        <div>
            <a class="admin-back" href="{{ route('admin.dashboard') }}">&larr; Dashboard</a>
            <h1>{{ $restaurant->name }}</h1>
            <p>
                {{ $restaurant->category }} &middot;
                <span @class(['admin-badge', 'admin-badge-green' => $snapshot['available'], 'admin-badge-amber' => ! $snapshot['available']])>{{ $snapshot['available'] ? 'Pendaftaran dibuka' : 'Pendaftaran ditutup' }}</span>
            </p>
        </div>
        <a class="admin-button admin-button-secondary" href="{{ route('admin.restaurants.edit', $restaurant) }}">Pengaturan outlet</a>
    </header>

    <section class="admin-queue-console">
        <div class="admin-now-serving">
            <span>Sedang dilayani</span>
            <strong>{{ $current?->queue_number ?? '–' }}</strong>
            <p>{{ $current ? $current->party_size.' orang · dipanggil '.$current->called_at?->format('H:i') : ($waiting->isEmpty() ? 'Belum ada antrean yang menunggu.' : 'Siap memanggil kelompok berikutnya.') }}</p>
            <div class="admin-actions">
                @if ($current)
                    <form method="post" action="{{ route('admin.restaurants.queue.complete', $restaurant) }}">@csrf<button class="admin-button admin-button-primary admin-button-large" type="submit">Tandai selesai</button></form>
                    <form method="post" action="{{ route('admin.restaurants.queue.skip', $restaurant) }}">@csrf<button class="admin-button admin-button-ghost admin-button-large" type="submit">Lewati</button></form>
                @else
                    <form method="post" action="{{ route('admin.restaurants.queue.call-next', $restaurant) }}">@csrf<button class="admin-button admin-button-primary admin-button-large" type="submit" @disabled($waiting->isEmpty())>Panggil berikutnya{{ $waiting->first() ? ' ('.$waiting->first()->queue_number.')' : '' }}</button></form>
                @endif
            </div>
        </div>
        <div class="admin-console-stats">
            <article><span>Menunggu</span><strong>{{ $waiting->count() }} <small>kelompok</small></strong></article>
            <article><span>Estimasi antrean</span><strong>{{ $snapshot['estimated_wait'] }} <small>menit</small></strong></article>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div>
                <h2>Antrean aktif</h2>
                <p>Satu nomor antrean untuk satu kelompok, diurutkan dari yang paling awal.</p>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Jumlah tamu</th>
                        <th>WhatsApp</th>
                        <th>Waktu masuk</th>
                        <th>Status</th>
                        <th class="admin-cell-action"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activeTickets as $ticket)
                        <tr @class(['admin-row-highlight' => $ticket->status === 'called'])>
                            <td class="admin-mono"><strong>{{ $ticket->queue_number }}</strong></td>
                            <td>{{ $ticket->party_size }} orang</td>
                            <td class="admin-mono">{{ $ticket->phone_number ?? '–' }}</td>
                            <td>{{ $ticket->joined_at?->format('H:i') ?? '–' }}</td>
                            <td><span class="admin-badge admin-status-{{ $ticket->status }}">{{ $ticket->statusLabel() }}</span></td>
                            <td class="admin-cell-action">
                                <form method="post" action="{{ route('admin.restaurants.queue.cancel', [$restaurant, $ticket]) }}" onsubmit="return confirm('Batalkan antrean {{ $ticket->queue_number }}?')">
                                    @csrf
                                    <button class="admin-text-button admin-text-danger" type="submit">Batalkan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-empty">Belum ada antrean aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
