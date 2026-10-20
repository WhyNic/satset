<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Failed jobs keep their payload, which can include an email address. Prune them daily.
Schedule::command('queue:prune-failed', ['--hours' => 24])->daily();

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
