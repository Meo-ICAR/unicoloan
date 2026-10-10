<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Website;
use App\Services\Signature\SignatureProviderManager;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SignatureProviderManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // unicoloan possiede il proprio database: le tabelle del pacchetto si creano con il normale `php artisan migrate`
        // (e quindi anche con `migrate:fresh`, nei test), registrate nella tabella `migrations` dell'app.
        $this->loadMigrationsFrom(\Unico\Core\UnicoCoreServiceProvider::migrationsPath());

        Relation::morphMap([
            'branch' => Branch::class,
            'client' => Client::class,
            'cliente' => Clienti::class,
            'company' => Company::class,
            'document' => Document::class,
            'employee' => Employee::class,
            'fornitore' => Fornitore::class,
            'pratica' => Pratica::class,
            'website' => Website::class,
        ]);

        Event::listen(
            SocialiteWasCalled::class,
            [MicrosoftExtendSocialite::class, 'handle']
        );
        Event::listen(
            SocialiteWasCalled::class,
            [GoogleExtendSocialite::class, 'handle']
        );
    }
}
