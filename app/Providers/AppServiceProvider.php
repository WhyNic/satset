<?php

namespace App\Providers;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('owner', fn (User $user): bool => $user->isOwner());
        Gate::define('manage-restaurant', fn (User $user, Restaurant $restaurant): bool => $user->canManage($restaurant));
    }
}
