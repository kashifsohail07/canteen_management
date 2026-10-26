<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\DailySession;
use App\Models\Vendor;
use App\Policies\CustomerPolicy;
use App\Policies\DailySessionPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Paginator::useBootstrapFive();
        Vite::useBuildDirectory('dist');

        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(DailySession::class, DailySessionPolicy::class);
    }
}
