<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Quattro plichi documentali della cessione, uno per gruppo di tipologia lavorativa.
 * I nomi con anno o banca specifica vengono normalizzati (2026 in corso, 2025 corrente,
 * 2024 precedente); un tipo già presente col nome normalizzato (o che lo contiene, come il
 * fascicolo retail) si riusa senza rinominare gli altri. La forma precedente («ultimo anno»,
 * «anno precedente») viene rinominata, così non restano due tipi per lo stesso documento.
 */
class PlichiCessioneGruppoSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const BANK_BRANDS = [
        'Intesa Sanpaolo',
        'IBL Banca',
        'Banco BPM',
        'Findomestic',
        'Prestitalia',
        'UniCredit',
        'Santander',
        'COMPASS',
        'Pitagora',
        'Dynamica',
        'Bibanca',
        'Futuro',
        'Agos',
        'Fides',
        'Sigla',
        'BNL',
        'IBL',
    ];

    /**
     * @var array<int, string>
     */
    private const INVIO_BANCA = [
        'Fascicolo completo retail CQ',
        'Delibera banca',
        'Informazioni europee di base sul credito (SECCI)',
    ];

    /**
     * Nomi di partenza: 2026/2025/2024 e il marchio banca si normalizzano prima del salvataggio.
     *
     * @var array<string, array<int, string>>
     */
    private const ISTRUTTORIA = [
        'pensionati' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Cedolino Pensione',
            'Certificazione Unica (CU) 2026',
            'Certificazione Unica (CU) 2025',
            'Comunicazione Quota Cedibile INPS',
            'Estratto Conto INPS',
            'Modello OBISM',
            'Polizza Rischio Vita',
            'patronato',
            'Richiesta Conteggio Estintivo COMPASS',
        ],
        'privati' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Busta paga 2026',
            'Busta paga 2025',
            'Busta paga 2024',
            'Certificazione Unica (CU) 2026',
            'Certificazione Unica (CU) 2025',
            'Contratto di lavoro',
            'Modello 730 / Redditi 2026',
            'Modello 730 / Redditi 2025',
            'Modello 730 / Redditi 2024',
            'Estratto conto bancario',
            'Polizza Rischio Vita',
            'Polizza Rischio Impiego / Perdit Lavoro',
            'Richiesta Conteggio Estintivo COMPASS',
        ],
        'pubblici' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Busta paga 2026',
            'Busta paga 2025',
            'Busta paga 2024',
            'Certificazione Unica (CU) 2026',
            'Certificazione Unica (CU) 2025',
            'Certificato di stipendio e quota cedibile',
            'Delega richiesta allegati statali',
            'Polizza Rischio Vita',
            'patronato',
            'Richiesta Conteggio Estintivo COMPASS',
        ],
        'parapubblici' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Busta paga 2026',
            'Busta paga 2025',
            'Busta paga 2024',
            'Certificazione Unica (CU) 2026',
            'Certificazione Unica (CU) 2025',
            'Certificato di stipendio e quota cedibile',
            'Contratto di lavoro',
            'Polizza Rischio Vita',
            'Polizza Rischio Impiego / Perdit Lavoro',
            'Richiesta Conteggio Estintivo COMPASS',
        ],
    ];

    public function run(): void
    {
        foreach (self::ISTRUTTORIA as $group => $istruttoria) {
            $documents = [];

            foreach ($istruttoria as $label) {
                $documents[] = [$label, 'istruttoria'];
            }

            foreach (self::INVIO_BANCA as $label) {
                $documents[] = [$label, 'invio_banca'];
            }

            $this->plico($group, $documents);
        }
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $documents
     */
    private function plico(string $group, array $documents): void
    {
        $task = Task::query()->withoutGlobalScopes()->updateOrCreate(
            ['name' => "Cessione · {$group}"],
            [
                'description' => "Documenti di istruttoria e invio banca per la cessione del quinto, gruppo {$group}.",
                'taskable' => 'pratica',
                'trigger_field' => 'tipo_prodotto',
                'trigger_state' => 'equals',
                'trigger_value' => 'Cessione',
                'trigger_subfield' => 'employment_document_group',
                'trigger_subvalue' => $group,
                'is_active' => true,
            ],
        );

        $sync = [];

        foreach ($documents as $index => [$label, $phase]) {
            $type = $this->resolveType($label);
            $sync[$type->getKey()] = [
                'is_required' => $this->isRequired($label),
                'sort_order' => $index + 1,
                'phase' => $phase,
            ];
        }

        $task->documentTypes()->sync($sync);
    }

    private function resolveType(string $label): DocumentType
    {
        $name = $this->normalizeDocumentName($label);
        $existing = DocumentType::query()->where('name', $name)->first();

        if ($existing === null) {
            $legacy = $this->legacyNormalizedName($label);
            if ($legacy !== $name && preg_match('/ultimo anno|anno precedente/', $legacy) === 1) {
                $existing = DocumentType::query()->where('name', $legacy)->first();
                if ($existing !== null) {
                    $existing->forceFill([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($name, $existing->getKey()),
                    ])->save();
                }
            }
        }

        if ($existing === null) {
            $like = '%'.addcslashes($name, '%_\\').'%';
            $existing = DocumentType::query()
                ->where('name', 'like', $like)
                ->orderByRaw('CHAR_LENGTH(`name`)')
                ->orderBy('id')
                ->first();
        }

        if ($existing === null) {
            return DocumentType::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'source' => 'cliente',
                'is_signed' => $name === 'Informazioni europee di base sul credito (SECCI)',
            ]);
        }

        return $existing;
    }

    private function normalizeDocumentName(string $name): string
    {
        $normalized = preg_replace('/\b2026\b/', 'in corso', $name) ?? $name;
        $normalized = preg_replace('/\b2025\b/', 'corrente', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b2024\b/', 'precedente', $normalized) ?? $normalized;

        foreach (self::BANK_BRANDS as $brand) {
            $normalized = preg_replace('/\b'.preg_quote($brand, '/').'\b/iu', 'banca', $normalized) ?? $normalized;
        }

        return preg_replace('/\s{2,}/', ' ', trim($normalized)) ?? trim($normalized);
    }

    /** Forma usata prima: 2025 «ultimo anno», 2024 «anno precedente». Il 2026 non aveva un’etichetta. */
    private function legacyNormalizedName(string $name): string
    {
        $normalized = preg_replace('/\b2025\b/', 'ultimo anno', $name) ?? $name;
        $normalized = preg_replace('/\b2024\b/', 'anno precedente', $normalized) ?? $normalized;

        foreach (self::BANK_BRANDS as $brand) {
            $normalized = preg_replace('/\b'.preg_quote($brand, '/').'\b/iu', 'banca', $normalized) ?? $normalized;
        }

        return preg_replace('/\s{2,}/', ' ', trim($normalized)) ?? trim($normalized);
    }

    private function isRequired(string $label): bool
    {
        return ! in_array($this->normalizeDocumentName($label), [
            'Patente di Guida',
            'Delega richiesta allegati statali',
            'patronato',
        ], true);
    }

    private function uniqueSlug(string $name, int|string|null $exceptId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'documento-cessione';
        $suffix = 2;

        while (DocumentType::withTrashed()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
