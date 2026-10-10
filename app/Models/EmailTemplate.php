<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Unico\Core\Models\EmailTemplate as CoreEmailTemplate;

class EmailTemplate extends CoreEmailTemplate
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('app_isolation', function (Builder $builder) {

            // Evita crash se esegui codice fuori dal contesto HTTP di Filament (es. php artisan db:seed)
            if (! app()->runningInConsole() && Filament::getCurrentPanel()) {
                $currentPanelId = Filament::getCurrentPanel()->getId();
                $currentApp = ($currentPanelId === 'admin') ? 'UnicoOAM' : 'UnicoFin';
            } else {
                // Valore di fallback generico se siamo in console o fuori da Filament
                // In questo modo legge tutto ciò che non è esplicitamente dell'altra app
                $currentApp = config('app.identifier', 'UnicoOAM');
            }

            // Il raggruppamento delle condizioni OR deve usare il Builder corretto
            $builder->where(function (Builder $query) use ($currentApp) {
                $query->where('app_identifier', $currentApp)
                    ->orWhereNull('app_identifier')
                    ->orWhere('app_identifier', '');
            });
        });
    }
}
