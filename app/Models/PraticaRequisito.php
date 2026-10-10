<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Requisito documentale di una pratica: nel pacchetto è un tipo di documento (`document_types`).
 * I nomi italiani storici (`codice`, `descrizione`) restano come alias di `code` e `description`.
 */
class PraticaRequisito extends DocumentType
{
    protected function codice(): Attribute
    {
        return Attribute::make(get: fn () => $this->code, set: fn ($value) => ['code' => $value]);
    }

    protected function descrizione(): Attribute
    {
        return Attribute::make(get: fn () => $this->description, set: fn ($value) => ['description' => $value]);
    }

    /** Regole che collegano il requisito ai sottotipi di prodotto. */
    public function regoleProdotto(): HasMany
    {
        return $this->hasMany(RequisitoTipoFinanziamento::class, 'document_type_id');
    }

    /** Istanze operative del requisito sulle singole pratiche. */
    public function operativi(): HasMany
    {
        return $this->hasMany(PraticaRequisitoOperativo::class, 'document_type_id')
            ->where('requestable_type', 'pratica');
    }
}
