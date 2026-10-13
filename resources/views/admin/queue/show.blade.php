<x-layouts.admin title="Queue {{ $restaurant->name }} - SatSet">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Queue Management</p>
            <h1>{{ $restaurant->name }}</h1>
            <p>{{ $restaurant->category }} · {{ $restaurant->active ? 'Restoran active' : 'Restoran disabled' }}</p>
        </div>
        <a class="admin-secondary-button" href="{{ route('admin.restaurants.index') }}">Kembali ke Restoran</a>
    </section>

    <section class="admin-summary-grid">
        <article>
            <span>Sedang dilayani</span>
            <strong>{{ $current?->queue_number ?? '-' }}</strong>
        </article>
        <article>
            <span>Menunggu</span>
            <strong>{{ $waiting->count() }}</strong>
        </article>
        <article>
            <span>Estimasi antrean</span>
            <strong>{{ $snapshot['estimated_wait'] }} menit</strong>
        </article>
        <article>
            <span>Status restoran</span>
            <strong>{{ $restaurant->active ? 'Active' : 'Disabled' }}</strong>
        </article>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div>
                <h2>Control Antrean</h2>
                <p>Gunakan action ini untuk customer yang sedang dilayani atau waiting paling awal.</p>
            </div>
            <div class="admin-actions">
                <form method="post" action="{{ route('admin.restaurants.queue.call-next', $restaurant) }}">@csrf<button class="admin-primary-button" type="submit">Panggil Berikutnya</button></form>
                <form method="post" action="{{ route('admin.restaurants.queue.complete', $restaurant) }}">@csrf<button class="admin-secondary-button" type="submit">Tandai Selesai</button></form>
                <form method="post" action="{{ route('admin.restaurants.queue.skip', $restaurant) }}">@csrf<button class="admin-warning-button" type="submit">Lewati</button></form>
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div>
                <h2>Active Queue</h2>
                <p>Daftar antrean called dan waiting untuk restoran ini.</p>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Waktu Masuk</th>
                        <th>Status</th>
                        <th>Push Notification</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activeTickets as $ticket)
                        <tr>
                            <td><strong>{{ $ticket->queue_number }}</strong></td>
                            <td>{{ $ticket->joined_at?->format('H:i') ?? '-' }}</td>
                            <td><span class="admin-status admin-status-{{ $ticket->status }}">{{ ucfirst($ticket->status) }}</span></td>
                            <td>
                                <span class="admin-status {{ $ticket->push_subscriptions_count > 0 ? 'admin-status-push-active' : 'admin-status-push-inactive' }}">
                                    {{ $ticket->push_subscriptions_count > 0 ? 'Active' : 'Not Active' }}
                                </span>
                            </td>
                            <td class="admin-inline-actions">
                                @if ($ticket->status === 'called')
                                    <form method="post" action="{{ route('admin.restaurants.queue.complete', $restaurant) }}">
                                        @csrf
                                        <button class="admin-table-action" type="submit">Selesai</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.restaurants.queue.skip', $restaurant) }}">
                                        @csrf
                                        <button class="admin-warning-link" type="submit">Lewati</button>
                                    </form>
                                @elseif ($ticket->status === 'waiting')
                                    @if (! $current && $waiting->first()?->is($ticket))
                                        <form method="post" action="{{ route('admin.restaurants.queue.call-next', $restaurant) }}">
                                            @csrf
                                            <button class="admin-table-action" type="submit">Panggil</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.restaurants.queue.cancel', [$restaurant, $ticket]) }}" onsubmit="return confirm('Batalkan antrean ini?')">
                                        @csrf
                                        <button class="admin-danger-link" type="submit">Batalkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada antrean aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
