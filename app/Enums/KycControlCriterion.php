<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycControlCriterion: string implements HasLabel
{
    case Shares = 'quota_25';
    case MajorityVotes = 'maggioranza_voti';
    case DominantVotes = 'influenza_voti';
    case DominantContract = 'influenza_contratti';
    case Management = 'poteri_amministrazione';

    public function getLabel(): string
    {
        return match ($this) {
            self::Shares => 'Titolarità di una quota superiore al 25% del capitale',
            self::MajorityVotes => 'Maggioranza dei voti esercitabili in assemblea ordinaria',
            self::DominantVotes => 'Voti sufficienti per esercitare un\'influenza dominante in assemblea ordinaria',
            self::DominantContract => 'Influenza dominante in virtù di particolari vincoli contrattuali',
            self::Management => 'Poteri di amministrazione o rappresentanza',
        };
    }
}
