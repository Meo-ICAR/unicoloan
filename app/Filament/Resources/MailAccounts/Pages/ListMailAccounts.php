<?php

namespace App\Filament\Resources\MailAccounts\Pages;

use App\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;  // CORRETTO
use Illuminate\Support\HtmlString;

class ListMailAccounts extends ListRecords
{
    protected static string $resource = MailAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
