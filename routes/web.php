<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminQueueController;
use App\Http\Controllers\Admin\AdminQueueOverviewController;
use App\Http\Controllers\Admin\AdminRestaurantController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])
    ->name('restaurants.show');

Route::get('/restaurants/{restaurant}/recommendations', [RestaurantController::class, 'recommendations'])
    ->name('restaurants.recommendations');

Route::get('/restaurants/{restaurant}/queue/new', [QueueController::class, 'create'])
    ->name('queue.create');

Route::post('/restaurants/{restaurant}/queue', [QueueController::class, 'store'])
    ->name('queue.store');

Route::get('/queue/{ticket:public_token}', [QueueController::class, 'show'])
    ->name('queue.show');

Route::get('/queue/{ticket:public_token}/status', [QueueController::class, 'status'])
    ->name('queue.status');

Route::post('/queue/{ticket:public_token}/cancel', [QueueController::class, 'cancel'])
    ->name('queue.cancel');

Route::post('/queue/{ticket:public_token}/push-subscription', [PushSubscriptionController::class, 'store'])
    ->name('queue.push-subscription.store');

Route::delete('/queue/{ticket:public_token}/push-subscription', [PushSubscriptionController::class, 'destroy'])
    ->name('queue.push-subscription.destroy');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AdminAuthController::class, 'login'])
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

        Route::post('/restaurants/{restaurant}/queue/reset', [AdminQueueController::class, 'reset'])
            ->name('restaurants.queue.reset');

        Route::post('/restaurants/{restaurant}/queue/{ticket}/cancel', [AdminQueueController::class, 'cancel'])
            ->name('restaurants.queue.cancel');
    });
});