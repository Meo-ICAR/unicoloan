<?php

namespace App\Enums;

use Illuminate\Support\Facades\Log;

enum PlanType: string
{
    case ESSENTIAL = 'essential';
    case BASE = 'base';
    case GOLD = 'gold';
    case FULL = 'full';
    case DEBUG = 'debug';

    /**
     * Ritorna l'elenco delle funzionalità abilitate per ciascun piano.
     */
    public function features(): array
    {
        $manager = [
            'complaint-registries',
            'document-schedules',
            'documents',
            'document-relation-manager',
            'documents-relation-manager',
            'employees',
            'clientis',
            'fornitores',
            'oam-semestrales',
            'suspicious-activity-reports',
        ];

        $manager2 = [
            'companies',
            'oam-codes',
            'users',
            'branches',
            'websites',
        ];

        $systems = [
            'document-types',
            'email-templates',
            'tasks',
            'users',
        ];

        $oamSheet = [
            'globale', 'Anagrafica', 'Informativo', 'Sedi', 'Prudenziale',
        ];

        $essentialFeatures = [
            'documents',
            'document-schedules',
            'oam-semestrales',
            ...$oamSheet,
        ];

        $baseFeatures = [
            ...$essentialFeatures,
            ...$manager,
            ...$manager2,
        ];

        $goldFeatures = [
            ...$baseFeatures,
            ...$systems,
            'audits',
            'audit-resource',
            'documents-relation-manager',
        ];

        // Mappatura e gerarchia dei piani (ogni livello eredita il precedente)
        return match ($this) {
            self::ESSENTIAL => $essentialFeatures,
            self::BASE => $baseFeatures,
            self::GOLD => $goldFeatures,
            self::FULL,
            self::DEBUG => ['*'], // '*' indica che ha accesso a TUTTE le feature
        };
    }

    /**
     * Verifica se una specifica feature è inclusa nel piano attivo.
     */
    public function hasFeature(string $feature, ?string $callerClass = null): bool
    {
        $features = $this->features();

        // FULL e DEBUG sbloccano tutto (*), altrimenti verifica l'array del piano
        $hasAccess = in_array('*', $features) || in_array($feature, $features);

        // Se siamo nel piano DEBUG, registra nel log di Laravel chi sta facendo la richiesta
        if ($this === self::DEBUG) {
            $user = auth()->user();

            Log::debug('🔍 [PLAN DEBUG] Controllo visibilità feature', [
                'feature_richiesta' => $feature,
                'esito_accesso' => $hasAccess ? 'ABILITATO' : 'NEGATO',
                'chiamato_da' => $callerClass ?? $this->getCallerFromTrace(),
                'utente' => $user ? "{$user->email} (ID: {$user->id})" : 'Non autenticato',
                'ip' => request()->ip(),
                'url_corrente' => request()->fullUrl(),
            ]);
        }

        return $hasAccess;
    }

    /**
     * Recupera la classe chiamante dallo stack trace se non passata esplicitamente.
     */
    private function getCallerFromTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        return $trace[3]['class'] ?? ($trace[2]['class'] ?? 'Sconosciuto');
    }
}
