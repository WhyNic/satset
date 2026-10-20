<x-layouts.customer :title="'Ambil Antrean - '.$restaurant->name.' | SatSet'" :show-header="false">
    <x-customer.app-header title="Ambil Antrean" eyebrow="Lewat Email" :fallback="route('outlets.show', $restaurant->slug)" />

    <style>
        .join-page { max-width: 520px; margin: 0 auto; padding: 16px; display: grid; gap: 20px; }
        .join-page h1 { margin: 4px 0 0; font-size: clamp(24px, 6vw, 30px); line-height: 1.15; color: var(--ink); }
        .join-page .lead { margin: 0; color: var(--muted); line-height: 1.55; }
        .join-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .join-stats div { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: white; }
        .join-stats span { display: block; color: var(--muted); font-size: 13px; }
        .join-stats strong { display: block; margin-top: 4px; color: var(--ink); font-size: 18px; }
        .join-form { display: grid; gap: 16px; }
        .join-field { display: grid; gap: 6px; }
        .join-field > span { font-weight: 700; color: var(--ink); font-size: 14px; }
        .join-field input,
        .join-field select { width: 100%; min-height: 48px; padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px; background: white; color: var(--ink); font-size: 16px; }
        .join-field input:focus-visible,
        .join-field select:focus-visible { outline: 3px solid var(--lime); outline-offset: 2px; border-color: var(--green); }
        .join-field small { color: var(--muted); font-size: 13px; line-height: 1.4; }
        .join-error { color: #b42318; font-size: 13px; font-weight: 600; }
        .join-alert { border: 1px solid #f5c2bb; background: #fef3f2; color: #7a271a; border-radius: 8px; padding: 12px 14px; line-height: 1.45; margin: 0; }
        .join-submit { min-height: 52px; font-size: 16px; }
        .join-privacy { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.5; }
    </style>

    <section class="join-page">
        <div>
            <p class="eyebrow">{{ $restaurant->name }}</p>
            <h1>Ambil antrean lewat email</h1>
        </div>

        <p class="lead">Masukkan jumlah tamu dan email Anda. Kami kirim konfirmasi, posisi antrean, dan pemberitahuan saat giliran mendekat.</p>

        <div class="join-stats">
            <div><span>Sedang menunggu</span><strong>{{ $snapshot['waiting_count'] }} antrean</strong></div>
            <div><span>Estimasi saat ini</span><strong>&plusmn; {{ $snapshot['estimated_wait'] }} menit</strong></div>
        </div>

        @if ($errors->has('restaurant'))
            <p class="join-alert" role="alert">{{ $errors->first('restaurant') }}</p>
        @endif

        <form method="post" action="{{ route('outlets.join.store', $restaurant->slug) }}" class="join-form">
            @csrf

            <label class="join-field">
                <span>Jumlah tamu</span>
                <select name="party_size" required aria-describedby="party-help">
                    @for ($i = 1; $i <= $restaurant->max_party_size; $i++)
                        <option value="{{ $i }}" @selected((int) old('party_size', 1) === $i)>{{ $i }} orang</option>
                    @endfor
                </select>
                <small id="party-help">Maksimal {{ $restaurant->max_party_size }} orang per kelompok. Satu kelompok, satu nomor antrean.</small>
                @error('party_size')<span class="join-error" role="alert">{{ $message }}</span>@enderror
            </label>

            <label class="join-field">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="nama@contoh.com" aria-describedby="email-help">
                <small id="email-help">Dipakai hanya untuk pemberitahuan antrean ini.</small>
                @error('email')<span class="join-error" role="alert">{{ $message }}</span>@enderror
            </label>

            <button class="button button-green join-submit" type="submit" @disabled(! $snapshot['available'])>Ambil Antrean</button>
            @unless ($snapshot['available'])
                <p class="join-privacy">Antrean outlet sedang ditutup. Silakan hubungi staf outlet.</p>
            @endunless
        </form>

        <p class="join-privacy">Nomor antrean dibuat setelah Anda kirim. Email dihapus dari sistem setelah antrean selesai, dibatalkan, atau dilewati.</p>
    </section>
</x-layouts.customer>
