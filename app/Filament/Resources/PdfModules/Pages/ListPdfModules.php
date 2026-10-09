<?php

namespace App\Filament\Resources\PdfModules\Pages;

use App\Filament\Resources\PdfModules\PdfModuleResource;
use Filament\Resources\Pages\ListRecords;

class ListPdfModules extends ListRecords
{
    protected static string $resource = PdfModuleResource::class;
}
