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
    \App\Models\User::create(['name' => $this->option('name'), 'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($password)]);
    $this->info('Akun admin dibuat.');
})->purpose('Create an administrator without a default password');
