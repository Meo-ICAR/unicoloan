<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use Illuminate\Support\Collection;

class ModuleSuggester
{
    /**
     * Moduli attivi pertinenti per il tipo di prodotto della pratica e il tipo di cliente.
     *
     * @return Collection<int, PdfModule>
     */
    public function suggest(Pratica $pratica, Client $client): Collection
    {
        return PdfModule::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (PdfModule $module) => $module->appliesTo($pratica->tipo_prodotto, (bool) $client->is_person))
            ->values();
    }
}
