<x-layouts.customer title="Tiket {{ $ticket->queue_number }} - SatSet" :show-header="false">
    <x-customer.app-header title="Antrean Saya" :fallback="route('home')" />

    @php
        $statusCopy = match ($ticket->status) {
            'waiting' => $position > 0 && $position <= 3 ? 'Hampir Giliran' : 'Menunggu',
            'called' => 'Dipanggil',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'skipped' => 'Dilewati',
            default => ucfirst($ticket->status),
        };
    @endphp

    <section class="customer-narrow-page">
        <article class="ticket ticket-{{ $ticket->status }} app-ticket" data-status-url="{{ route('queue.status', ['ticket' => $ticket->public_token]) }}">
            <div class="ticket-topline">
                @if ($ticket->status === 'called')
                    <p class="eyebrow">Giliranmu sudah tiba!</p>
                @elseif ($ticket->status === 'cancelled')
                    <p class="eyebrow danger-text">Antrean dibatalkan</p>
                @else
                    <p class="eyebrow">Antrean Anda</p>
                @endif
                <span class="badge badge-muted">{{ $statusCopy }}</span>
            </div>

            <h1>{{ $ticket->queue_number }}</h1>
            <p class="ticket-restaurant">{{ $ticket->restaurant->name }}</p>

            <div class="queue-focus-grid">
                <div>
                    <span>Masih ada</span>
                    <strong data-ticket-before>{{ max($position - 1, 0) }}</strong>
                    <small>antrean lagi</small>
                </div>
                <div>
                    <span>Estimasi</span>
                    <strong><span data-ticket-estimate>{{ $estimatedWait }}</span></strong>
                    <small>menit</small>
                </div>
            </div>

            <div class="progress-block">
                <div class="progress-label">
                    <span>Progress antrean</span>
                    <strong data-ticket-position>Posisi {{ $position ?: '-' }}</strong>
                </div>
                <div class="progress-track" aria-label="Progress antrean"><span data-ticket-progress style="width: {{ $position > 0 ? max(10, min(100, 100 - (($position - 1) * 12))) : 100 }}%"></span></div>
            </div>

            <ol class="queue-timeline" aria-label="Timeline antrean">
                <li class="done">Terdaftar</li>
                <li @class(['done' => in_array($ticket->status, ['waiting', 'called', 'completed'])])>Menunggu</li>
                <li @class(['done' => $position > 0 && $position <= 3])>Hampir Giliran</li>
                <li @class(['done' => $ticket->status === 'called'])>Giliran Anda</li>
            </ol>

            <p class="muted" data-ticket-status>Status: {{ $statusCopy }}</p>

            <section
                class="notification-card"
                data-push-card
                data-vapid-public-key="{{ $pushPublicKey }}"
                data-subscription-url="{{ route('queue.push-subscription.store', ['ticket' => $ticket->public_token]) }}"
                data-has-subscription="{{ $pushSubscriptionCount > 0 ? 'true' : 'false' }}"
            >
                <div class="notification-icon" aria-hidden="true">!</div>
                <div>
                    <h2>Jangan lewatkan giliranmu</h2>
                    <p data-push-message>{{ $pushSubscriptionCount > 0 ? 'Notifikasi aktif. Anda dapat menutup tab ini; SatSet akan memberi tahu saat giliran mendekat.' : 'Aktifkan notifikasi agar SatSet memberi tahu ketika antrean tinggal sedikit.' }}</p>
                </div>
                <button class="button secondary" type="button" data-push-enable @disabled($pushSubscriptionCount > 0)>
                    {{ $pushSubscriptionCount > 0 ? 'Notifikasi aktif' : 'Aktifkan Notifikasi' }}
                </button>
            </section>

            <div class="actions ticket-actions">
                @if ($ticket->status === 'waiting')
                    <form method="post" action="{{ route('queue.cancel', ['ticket' => $ticket->public_token]) }}" onsubmit="return confirm('Batalkan antrean?')">
                        @csrf
                        <button class="button secondary danger-soft" type="submit">Batalkan Antrean</button>
                    </form>
                @endif
            </div>
        </article>
    </section>

    <script src="{{ asset('js/queue-poll.js') }}" defer></script>
    <script src="{{ asset('js/push-subscription.js') }}" defer></script>
</x-layouts.customer>
