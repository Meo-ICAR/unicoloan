<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\PdfModuleField as CorePdfModuleField;

/** Mappatura campo AcroForm -> chiave dati (stringa: i valori di App\Enums\ModuleSourceKey, che qui fa da catalogo). */
class PdfModuleField extends CorePdfModuleField
{
    use HasFactory;

    public function module(): BelongsTo
    {
        return $this->belongsTo(PdfModule::class, 'pdf_module_id');
    }
}
