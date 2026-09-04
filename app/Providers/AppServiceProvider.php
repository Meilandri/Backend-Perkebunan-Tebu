<?php

namespace App\Providers;

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
        // Paksa https:// di production supaya URL foto (asset()/url()) tidak
        // mixed-content walau APP_URL di Railway masih http://.
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }
    }
}