<x-layouts.customer :title="$ticket->queue_number.' | SatSet'">
    <section class="ticket-page" data-status-url="{{ route('queue.status', $ticket->public_token) }}" data-initial-status="{{ $ticket->status }}">
        <a class="text-link" href="{{ route('outlets.show', $ticket->restaurant->slug) }}">&larr; {{ $ticket->restaurant->name }}</a>
        <div class="ticket-heading"><p class="eyebrow">ANTREAN ANDA</p><h1>{{ $ticket->restaurant->name }}</h1><span class="availability" data-ticket-status>{{ ['waiting'=>'Menunggu','called'=>'Giliran Anda','completed'=>'Selesai','cancelled'=>'Dibatalkan','skipped'=>'Dilewati'][$ticket->status] }}</span></div>
        <div class="ticket-number">{{ $ticket->queue_number }}</div>
        <p class="ticket-party">{{ $ticket->party_size }} orang &middot; {{ $ticket->joined_at?->format('d M Y, H:i') }}</p>
        <div class="queue-metrics ticket-metrics">
            <div><span>Antrean di depan</span><strong data-ticket-before>{{ max($position - 1, 0) }}</strong></div>
            <div><span>Estimasi waktu</span><strong><span data-ticket-estimate>{{ $estimatedWait }}</span> <small>menit</small></strong></div>
        </div>
        <p class="ticket-message" data-ticket-message>{{ $ticket->status === 'called' ? 'Giliran Anda sudah tiba. Silakan menuju staf outlet.' : ($ticket->isActive() ? 'Tunggu dengan nyaman. Pembaruan antrean dikirim melalui WhatsApp.' : 'Antrean ini sudah berakhir. Terima kasih sudah menggunakan SatSet.') }}</p>
        <p class="live-note" data-live-note>Estimasi diperbarui berkala.</p>
        @if($ticket->isActive())
            <form method="post" action="{{ route('queue.cancel', $ticket->public_token) }}" data-ticket-cancel onsubmit="return confirm('Batalkan antrean ini?')">@csrf<button class="button button-outline" type="submit">Batalkan antrean</button></form>
        @endif
    </section>
    <script src="{{ asset('js/queue-poll.js') }}" defer></script>
</x-layouts.customer>
