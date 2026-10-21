<x-layouts.customer :title="$ticket->queue_number.' | SatSet'">
    <style>
        .verify-banner { margin: 14px auto 0; max-width: 420px; border: 1px solid var(--line); border-radius: 8px; background: #fffbeb; padding: 14px; display: grid; gap: 10px; text-align: center; font-size: 14px; line-height: 1.5; }
        .verify-banner p { margin: 0; }
    </style>
    <section class="ticket-page" data-status-url="{{ route('queue.status', $ticket->public_token) }}" data-initial-status="{{ $ticket->status }}" data-notify="{{ $ticket->email ? ($ticket->isEmailVerified() ? 'email' : 'pending') : 'none' }}" data-mode="{{ $ticket->restaurant->queue_mode }}">
        <a class="text-link" href="{{ route('outlets.show', $ticket->restaurant->slug) }}">&larr; {{ $ticket->restaurant->name }}</a>
        <div class="ticket-heading"><p class="eyebrow">ANTREAN ANDA</p><h1>{{ $ticket->restaurant->name }}</h1><span class="availability" data-ticket-status>{{ ['waiting'=>'Menunggu','called'=>'Giliran Anda','completed'=>'Selesai','cancelled'=>'Dibatalkan','skipped'=>'Dilewati'][$ticket->status] }}</span></div>
        <div class="ticket-number">{{ $ticket->queue_number }}</div>
        <p class="ticket-party">{{ $ticket->restaurant->isPickup() ? 'Pesanan untuk diambil' : $ticket->party_size.' orang' }} &middot; {{ $ticket->joined_at?->format('d M Y, H:i') }}</p>
        <div class="queue-metrics ticket-metrics">
            <div><span>Antrean di depan</span><strong data-ticket-before>{{ max($position - 1, 0) }}</strong></div>
            <div><span>Estimasi waktu</span><strong><span data-ticket-estimate>{{ $estimatedWait }}</span> <small>menit</small></strong></div>
        </div>
        @if(session('status'))
            <p class="live-note" role="status">{{ session('status') }}</p>
        @endif
        <p class="ticket-message" data-ticket-message>{{ $ticket->status === 'called' ? ($ticket->restaurant->isPickup() ? 'Pesanan Anda siap. Silakan ambil di staf outlet.' : 'Giliran Anda sudah tiba. Silakan menuju staf outlet.') :($ticket->isActive() ? ($ticket->email ? ($ticket->isEmailVerified() ? 'Tunggu dengan nyaman. Pembaruan antrean dikirim melalui email.' : 'Tunggu dengan nyaman. Verifikasi email Anda agar pemberitahuan bisa dikirim.') :'Tunggu dengan nyaman. Simpan halaman ini untuk memantau antrean Anda.') : 'Antrean ini sudah berakhir. Terima kasih sudah menggunakan SatSet.') }}</p>
        <p class="live-note" data-live-note>Estimasi diperbarui berkala.</p>
        @if($ticket->email)
            <p class="live-note" data-ticket-email>Email pemberitahuan: {{ $ticket->maskedEmail() }}{{ $ticket->isEmailVerified() ? '' : ' (menunggu verifikasi)' }}</p>
            @if(! $ticket->isEmailVerified() && $ticket->isActive())
                <div class="verify-banner" role="status">
                    <p>Klik tautan verifikasi di email pertama kami. Belum menerimanya? Cek folder spam, atau minta tautan baru.</p>
                    <form method="post" action="{{ route('queue.verification.resend', $ticket->public_token) }}">@csrf<button class="button button-outline" type="submit">Kirim ulang tautan verifikasi</button></form>
                </div>
            @endif
        @endif
        @if($ticket->isActive())
            <form method="post" action="{{ route('queue.cancel', $ticket->public_token) }}" data-ticket-cancel onsubmit="return confirm('Batalkan antrean ini?')">@csrf<button class="button button-outline" type="submit">Batalkan antrean</button></form>
        @endif
    </section>
    <script src="{{ asset('js/queue-poll.js') }}" defer></script>
</x-layouts.customer>
