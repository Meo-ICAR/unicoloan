<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\Website;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'branch' => Branch::class,
            'cliente' => Clienti::class,
            'company' => Company::class,
            'document' => Document::class,
            'employee' => Employee::class,
            'fornitore' => Fornitore::class,
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
