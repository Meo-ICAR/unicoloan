<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AgentiPanelProvider;
use App\Providers\Filament\UnicofinPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    UnicofinPanelProvider::class,
    AgentiPanelProvider::class,
];
