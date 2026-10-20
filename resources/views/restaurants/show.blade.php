<x-layouts.customer :title="$restaurant->name.' | SatSet'">
    <article class="outlet-page" data-outlet-status="{{ route('outlets.status', $restaurant->slug) }}">
        <div class="outlet-photo"><x-customer.restaurant-image :restaurant="$restaurant" variant="detail" loading="eager" /><span class="photo-label">ANTRE DENGAN SATSET</span></div>
        <div class="outlet-body">
            <div class="outlet-heading">
                <p class="eyebrow">{{ $restaurant->category }}</p>
                <h1>{{ $restaurant->name }}</h1>
                <p class="description">{{ $restaurant->description }}</p>
                <span class="availability {{ $snapshot['available'] ? '' : 'closed' }}" data-outlet-availability>{{ $snapshot['available'] ? 'Antrean sedang dibuka' : 'Antrean sedang ditutup' }}</span>
            </div>
            <section class="queue-overview" aria-label="Kondisi antrean">
                <div class="serving-row"><span>{{ $restaurant->isPickup() ? 'Sedang diambil' : 'Sedang dilayani' }}</span><strong data-outlet-current>{{ $snapshot['current_number'] ?? 'Belum ada' }}</strong></div>
                <div class="queue-metrics">
                    <div><span>Sedang menunggu</span><strong><span data-outlet-waiting>{{ $snapshot['waiting_count'] }}</span> <small>antrean</small></strong></div>
                    <div><span>Estimasi waktu</span><strong><span class="estimate-sign">&plusmn;</span><span data-outlet-estimate>{{ $snapshot['estimated_wait'] }}</span> <small>menit</small></strong></div>
                </div>
                <p class="live-note" data-live-note>Estimasi dapat berubah sesuai pelayanan outlet.</p>
            </section>
            <div class="outlet-action">
                <a class="button button-green" href="{{ route('outlets.join', $restaurant->slug) }}" @if(!$snapshot['available']) hidden @endif data-outlet-join>Ambil antrean <span aria-hidden="true">&nearr;</span></a>
                <p data-outlet-closed @if($snapshot['available']) hidden @endif>Antrean sedang ditutup. Silakan hubungi staf outlet.</p>
                <small>Tanpa login. {{ $restaurant->isPickup() ? 'Isi email Anda, lalu nomor pesanan dibuat setelah Anda kirim.' : 'Isi jumlah tamu dan email, lalu nomor antrean dibuat setelah Anda kirim.' }}</small>
            </div>
            <div class="outlet-next"><div><span class="step-number">01</span>@if($restaurant->isPickup())<p><strong>Isi email Anda</strong><span>Konfirmasi dan nomor pesanan dikirim ke email Anda.</span></p>@else<p><strong>Isi jumlah tamu dan email</strong><span>Konfirmasi dan posisi antrean dikirim ke email Anda.</span></p>@endif</div><div><span class="step-number">02</span>@if($restaurant->isPickup())<p><strong>Ambil pesanan Anda</strong><span>Kami kirim email saat pesanan siap di counter.</span></p>@else<p><strong>Nikmati waktu Anda</strong><span>Kami kirim email saat giliran sudah dekat.</span></p>@endif</div></div>
            @unless($restaurant->isPickup())
                <p class="party-note">Maksimal {{ $restaurant->max_party_size }} orang per kelompok. Satu kelompok, satu nomor antrean.</p>
            @endunless
        </div>
    </article>
</x-layouts.customer>
