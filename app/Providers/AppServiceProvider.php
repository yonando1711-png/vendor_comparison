<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Only creators (staff) can submit comparisons
        Gate::define('create-comparison', fn(User $user) => $user->isCreator());
        // Only admins can manage users
        Gate::define('manage-users', fn(User $user) => $user->isAdmin());
    }
}
