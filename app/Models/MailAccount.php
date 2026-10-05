<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mysql';
    protected $orderBy = 'name';
    protected $orderDirection = 'asc';

    protected $fillable = [
        'name',
        'email_address',
        'email_reply_to',
        'is_pec',
        'incoming_protocol',
        'incoming_host',
        'incoming_port',
        'incoming_username',
        'incoming_password',
        'incoming_encryption',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'is_active',
        'mailable_type',
        'mailable_id',
    ];

    protected $casts = [
        'is_pec' => 'boolean',
        'is_active' => 'boolean',
        'incoming_port' => 'integer',
        'smtp_port' => 'integer',
        'incoming_password' => 'encrypted',  // Cifra la password nel DB in modo sicuro
        'smtp_password' => 'encrypted',  // Cifra la password nel DB in modo sicuro
    ];

    /**
     * Relazione Polimorfica che ora supporta sia ID interi che UUID stringhe.
     */
    public function mailable(): MorphTo
    {
        return $this->morphTo();
    }
}
