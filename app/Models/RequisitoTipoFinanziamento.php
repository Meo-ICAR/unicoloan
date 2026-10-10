<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\DocumentRequirement as CoreDocumentRequirement;

/**
 * Quali requisiti servono per un sottotipo di prodotto. Nel pacchetto è `document_requirements`:
 * `pratica_requisito_id` → `document_type_id`, `obbligatorio` → `is_required`, `ordine` → `sort_order`.
 */
class RequisitoTipoFinanziamento extends CoreDocumentRequirement
{
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(PraticaRequisito::class, 'document_type_id');
    }

    public function subTipoProdotto(): BelongsTo
    {
        return $this->belongsTo(TipoprodottoSub::class, 'tipoprodotto_sub_id');
    }
}
