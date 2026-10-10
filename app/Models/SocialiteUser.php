<?php

namespace App\Models;

use DutchCodingCompany\FilamentSocialite\Models\SocialiteUser as BaseSocialiteUser;
use Unico\Core\Models\SocialiteUser as CoreSocialiteUser;

class SocialiteUser extends CoreSocialiteUser
{
    protected $casts = [
        'is_personal' => 'boolean',
    ];
}
