<x-layouts.customer :title="$restaurant->name.' | SatSet'">
    <article class="page" data-outlet-status="{{ route('outlets.status', $restaurant->slug) }}">
        <x-customer.restaurant-image class="outlet-photo" :restaurant="$restaurant" variant="detail" loading="eager" />

        <header class="outlet-header">
            <p class="eyebrow">{{ $restaurant->category }}</p>
            <h1>{{ $restaurant->name }}</h1>
            @if($restaurant->description)<p class="muted">{{ $restaurant->description }}</p>@endif
            <span class="pill {{ $snapshot['available'] ? 'pill-open' : 'pill-closed' }}" data-outlet-availability>{{ $snapshot['available'] ? 'Antrean sedang dibuka' : 'Antrean sedang ditutup' }}</span>
        </header>

        <section class="card" aria-label="Kondisi antrean">
            <div class="serving">
                <span>Sedang dilayani</span>
                <strong data-outlet-current>{{ $snapshot['current_number'] ?? 'Belum ada' }}</strong>
            </div>
            <div class="stats">
                <div class="stat"><span>Sedang menunggu</span><strong><span data-outlet-waiting>{{ $snapshot['waiting_count'] }}</span> <small>kelompok</small></strong></div>
                <div class="stat"><span>Estimasi tunggu</span><strong>&plusmn;<span data-outlet-estimate>{{ $snapshot['estimated_wait'] }}</span> <small>menit</small></strong></div>
            </div>
            <p class="live-note" data-live-note>Estimasi dapat berubah sesuai pelayanan outlet.</p>
        </section>

        <section class="cta">
            @if($whatsappUrl)
                <a class="button button-primary button-block" href="{{ route('outlets.whatsapp', $restaurant->slug) }}" @if(!$snapshot['available']) hidden @endif data-outlet-cta>Lanjut via WhatsApp</a>
                <p class="alert" data-outlet-closed @if($snapshot['available']) hidden @endif>Antrean sedang ditutup. Silakan hubungi staf outlet.</p>
                <p class="cta-note">Tanpa login. Nomor antrean dibuat setelah Anda konfirmasi di WhatsApp.</p>
            @else
                <p class="alert">Pendaftaran lewat WhatsApp belum tersedia. Silakan hubungi staf outlet.</p>
            @endif
        </section>

        <section class="card">
            <h2 class="card-title">Setelah ini</h2>
            <ol class="steps">
                <li><span class="step-number">1</span><div><strong>Lanjutkan di WhatsApp</strong><span>Pilih jumlah tamu dan konfirmasi antrean Anda.</span></div></li>
                <li><span class="step-number">2</span><div><strong>Nikmati waktu Anda</strong><span>Kami kabari saat giliran sudah dekat.</span></div></li>
            </ol>
            <p class="fine-print">Maksimal {{ $restaurant->max_party_size }} orang per kelompok. Satu kelompok, satu nomor antrean.</p>
        </section>
    </article>
</x-layouts.customer>
