<?php

namespace App\Providers;

use App\Contracts\AuditLogger;
use App\Listeners\AuditAuthenticationEvents;
use App\Services\Audit\AuditManager;
use App\Services\Audit\SpatieAuditLogger;
use App\Support\Audit\AuditIntegrityConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuditLogger::class, SpatieAuditLogger::class);

        // Scoped binding keeps the context isolated per request and is reset by
        // Laravel between queued jobs. AuditManager also exposes resetContext().
        $this->app->scoped(AuditManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        AuditIntegrityConfiguration::assertConfigured();
        $this->registerAuditListeners();
    }

    /**
     * Authentication events are the only global listeners registered for audit.
     * Model logging stays explicit and domain-owned.
     */
    protected function registerAuditListeners(): void
    {
        Event::listen(Login::class, [AuditAuthenticationEvents::class, 'login']);
        Event::listen(Logout::class, [AuditAuthenticationEvents::class, 'logout']);
        Event::listen(Failed::class, [AuditAuthenticationEvents::class, 'failed']);
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
