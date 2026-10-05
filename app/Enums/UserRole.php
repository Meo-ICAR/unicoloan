<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'user';
    case QUALITY = 'quality';
    case INSPECTOR = 'inspector';
    case SOS = 'sos';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';

    /**
     * Ritorna l'elenco delle funzionalità consentite per ciascun ruolo.
     */
    public function features(): array
    {
        return match ($this) {
            // Admin e SuperAdmin vedono sempre tutto (bypassati anche a monte in checkPiano())
            self::ADMIN, self::SUPER_ADMIN => ['*'],

            // L'Ispettore vede solo Audit e Reclami
            self::INSPECTOR => [
                'audits',
                'complaint-registries',
                'document-relation-manager',
                'suspicious-activity-reports',
                'oam-semestrales',
            ],

            self::QUALITY => [
                'audits',
                'document-relation-manager',
            ],
            // L'Utente standard vede solo alcune risorse base
            self::USER => ['*'],
            //  'Anagrafica',    'Fornitori' ],
        };
    }

    /**
     * Verifica se il ruolo include una determinata feature.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features();

        return in_array('*', $features) || in_array($feature, $features);
    }
}
