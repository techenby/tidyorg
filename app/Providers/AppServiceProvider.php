<?php

namespace App\Providers;

use App\Modules\AllowAllModules;
use App\Modules\Contracts\ModuleEntitlements;
use App\Modules\ModuleRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singletonIf(ModuleRegistry::class);
        $this->app->bindIf(ModuleEntitlements::class, AllowAllModules::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTrustedProxies();

        $this->app->make(ModuleRegistry::class)->validate();
    }

    /**
     * Trust the reverse proxies named in the configuration to report the visitor's address and scheme.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = config('tessera.trusted_proxies');

        if (is_string($proxies) && trim($proxies) !== '') {
            TrustProxies::at(trim($proxies) === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
