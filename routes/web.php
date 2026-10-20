<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminQueueController;
use App\Http\Controllers\Admin\AdminQueueOverviewController;
use App\Http\Controllers\Admin\AdminRestaurantController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\Admin\AdminWhatsAppLogController;
use App\Http\Controllers\Admin\AdminQrController;
use App\Http\Controllers\EmailJoinController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/r/{restaurant:slug}', [RestaurantController::class, 'show'])->name('outlets.show');
Route::get('/r/{restaurant:slug}/whatsapp', [RestaurantController::class, 'whatsapp'])->middleware('throttle:30,1')->name('outlets.whatsapp');
Route::get('/r/{restaurant:slug}/status', [RestaurantController::class, 'status'])->middleware('throttle:120,1')->name('outlets.status');
Route::get('/r/{restaurant:slug}/join', [EmailJoinController::class, 'create'])->middleware('throttle:60,1')->name('outlets.join');
Route::post('/r/{restaurant:slug}/join', [EmailJoinController::class, 'store'])->middleware('throttle:10,1')->name('outlets.join.store');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.webhook');

Route::get('/restaurants/{restaurant}', fn (\App\Models\Restaurant $restaurant) => redirect()->route('outlets.show', $restaurant->slug))
    ->name('restaurants.show');

Route::get('/restaurants/{restaurant}/recommendations', [RestaurantController::class, 'recommendations'])
    ->name('restaurants.recommendations');

Route::get('/restaurants/{restaurant}/queue/new', fn (\App\Models\Restaurant $restaurant) => redirect()->route('outlets.show', $restaurant->slug))
    ->name('queue.create');

Route::get('/queue/{ticket:public_token}', [QueueController::class, 'show'])
    ->name('queue.show');

Route::get('/queue/{ticket:public_token}/status', [QueueController::class, 'status'])
    ->name('queue.status');

Route::post('/queue/{ticket:public_token}/cancel', [QueueController::class, 'cancel'])
    ->name('queue.cancel');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::middleware('auth')->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.dashboard'))
            ->name('home');

        Route::post('/logout', [AdminAuthController::class, 'logout'])
            ->name('logout');

        Route::get('/dashboard', AdminDashboardController::class)
            ->name('dashboard');

        Route::get('/queues', AdminQueueOverviewController::class)
            ->name('queues.index');
        Route::get('/whatsapp-logs', AdminWhatsAppLogController::class)->name('whatsapp-logs.index');
        Route::get('/qr', [AdminQrController::class, 'index'])->name('qr.index');
        Route::get('/restaurants/{restaurant}/qr', [AdminQrController::class, 'show'])->name('qr.show');
        Route::get('/restaurants/{restaurant}/qr.svg', [AdminQrController::class, 'image'])->name('qr.image');

        Route::resource('/restaurants', AdminRestaurantController::class)
            ->except(['show']);

        Route::get('/restaurants/{restaurant}/queue', [AdminQueueController::class, 'show'])
            ->name('restaurants.queue');

        Route::post('/restaurants/{restaurant}/queue/call-next', [AdminQueueController::class, 'callNext'])
            ->name('restaurants.queue.call-next');

        Route::post('/restaurants/{restaurant}/queue/complete', [AdminQueueController::class, 'complete'])
            ->name('restaurants.queue.complete');

        Route::post('/restaurants/{restaurant}/queue/skip', [AdminQueueController::class, 'skip'])
            ->name('restaurants.queue.skip');

        Route::post('/restaurants/{restaurant}/queue/{ticket}/cancel', [AdminQueueController::class, 'cancel'])
            ->name('restaurants.queue.cancel');
    });
});
