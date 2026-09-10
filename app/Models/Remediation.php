<?php

namespace App\Models;

use App\Models\Concerns\LogsComplianceActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remediation extends Model
{
    use HasFactory, LogsComplianceActivity, SoftDeletes;

    protected $connection = 'mysql';

    protected $fillable = [
        'remediation_type',
        'name',
        'code',
        'description',
        'timeframe_hours',
        'timeframe_desc',
    ];

    protected $casts = [
        'timeframe_hours' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeByType($query, $type)
    {
        return $query->where('remediation_type', $type);
    }

    public function scopeAml($query)
    {
        return $query->where('remediation_type', 'AML');
    }

    public function scopePrivacy($query)
    {
        return $query->where('remediation_type', 'Privacy');
    }

    public function scopeReclami($query)
    {
        return $query->where('remediation_type', 'Gestione Reclami');
    }

    public function scopeMonitoraggio($query)
    {
        return $query->where('remediation_type', 'Monitoraggio Rete');
    }

    public function scopeTrasparenza($query)
    {
        return $query->where('remediation_type', 'Trasparenza');
    }

    public function scopeAssetto($query)
    {
        return $query->where('remediation_type', 'Assetto Organizzativo');
    }

    public function getUrgencyLevelAttribute(): string
    {
        if (! $this->timeframe_hours) {
            return 'unknown';
        }

        if ($this->timeframe_hours <= 24) {
            return 'critical';
        }
        if ($this->timeframe_hours <= 72) {
            return 'high';
        }
        if ($this->timeframe_hours <= 168) {
            return 'medium';
        }

        return 'low';
    }

    public function getUrgencyColorAttribute(): string
    {
        $level = $this->urgency_level;

        return match ($level) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray'
        };
    }

    public function getFormattedTimeframeAttribute(): string
    {
        return $this->timeframe_desc ?? 'N/A';
    }

    public static function getTypes(): array
    {
        return [
            'AML' => 'AML',
            'Gestione Reclami' => 'Gestione Reclami',
            'Monitoraggio Rete' => 'Monitoraggio Rete',
            'Privacy' => 'Privacy',
            'Trasparenza' => 'Trasparenza',
            'Assetto Organizzativo' => 'Assetto Organizzativo',
        ];
    }
}
