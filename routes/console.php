<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\WhatsappLog;
use App\Services\WhatsAppService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:send-pending', function (WhatsAppService $whatsapp): void {
    WhatsappLog::query()->where('direction', 'outbound')->where('status', 'pending')
        ->orderBy('id')->limit(100)->get()->each(fn (WhatsappLog $log) => $whatsapp->deliver($log));
    $this->info('Pending WhatsApp messages processed.');
})->purpose('Deliver durable WhatsApp outbox messages');

Schedule::command('whatsapp:send-pending')->everyMinute()->withoutOverlapping();

Artisan::command('satset:admin {email} {--name=SatSet Admin}', function (): void {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || \App\Models\User::where('email', $email)->exists()) {
        $this->error('Email tidak valid atau akun sudah tersedia. Akun existing tidak diubah.');

        return;
    }
    $password = $this->secret('Password admin baru (minimal 12 karakter)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Password harus minimal 12 karakter.');

        return;
    }
    \App\Models\User::create(['name' => $this->option('name'), 'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($password), 'role' => 'owner', 'active' => true]);
    $this->info('Akun pemilik SatSet dibuat. Akun admin outlet dibuat dari menu Akun admin.');
})->purpose('Create a SatSet owner account without a default password');

Artisan::command('satset:lan {--port=8000}', function (): void {
    $port = (int) $this->option('port');
    $addresses = collect(gethostbynamel(gethostname()) ?: [])
        ->filter(fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE))
        // VirtualBox/VMware host-only adapters are not reachable from a phone.
        ->reject(fn (string $ip): bool => str_starts_with($ip, '192.168.56.'))
        ->values();

    if ($addresses->isEmpty()) {
        $this->error('Alamat jaringan tidak ditemukan. Pastikan laptop terhubung ke Wi-Fi.');

        return;
    }

    $this->info('Buka salah satu alamat ini di browser LAPTOP, lalu scan QR-nya dengan HP (HP harus di Wi-Fi yang sama):');
    foreach ($addresses as $ip) {
        $this->line("  Admin QR : http://{$ip}:{$port}/admin/qr");
    }
    $this->newLine();
    $this->comment('Jika HP tidak bisa membuka halaman, izinkan PHP di Windows Firewall untuk jaringan Private.');
    $this->newLine();

    $this->call('serve', ['--host' => '0.0.0.0', '--port' => $port]);
})->purpose('Serve SatSet on the local network so phones can scan outlet QR codes');
