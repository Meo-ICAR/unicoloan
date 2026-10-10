<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Unico\Core\Models\Organization as CoreOrganization;

class Organization extends CoreOrganization
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Qui in futuro potrai aggiungere le relazioni, ad esempio:
    // public function communications()
    // {
    //     return $this->hasMany(Communication::class);
    // }
}
