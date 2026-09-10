<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

/**
 * Catalogo delle risorse gestibili dal motore RBAC (App\Models\EmployeeTypePermission).
 * Le chiavi devono corrispondere agli slug delle Filament Resource.
 */
class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            ['key' => 'audits', 'name' => 'Audit', 'group' => 'Compliance'],
            ['key' => 'audit-findings', 'name' => 'Rilievi Audit', 'group' => 'Compliance'],
            ['key' => 'complaint-registries', 'name' => 'Registro Reclami', 'group' => 'Compliance'],
            ['key' => 'suspicious-activity-reports', 'name' => 'Segnalazioni Operazioni Sospette', 'group' => 'Compliance'],
            ['key' => 'remediations', 'name' => 'Piani di Rimedio', 'group' => 'Compliance'],
            ['key' => 'oam-semestrales', 'name' => 'Semestrale OAM', 'group' => 'Compliance'],
            ['key' => 'documents', 'name' => 'Documenti', 'group' => 'Anagrafiche'],
            ['key' => 'document-schedules', 'name' => 'Scadenzario Documenti', 'group' => 'Anagrafiche'],
            ['key' => 'employees', 'name' => 'Dipendenti', 'group' => 'Anagrafiche'],
            ['key' => 'companies', 'name' => 'Aziende', 'group' => 'Anagrafiche'],
            ['key' => 'branches', 'name' => 'Sedi', 'group' => 'Anagrafiche'],
            ['key' => 'websites', 'name' => 'Siti Web', 'group' => 'Anagrafiche'],
            ['key' => 'users', 'name' => 'Utenti', 'group' => 'System'],
            ['key' => 'employee-types', 'name' => 'Ruoli', 'group' => 'System'],
            ['key' => 'email-templates', 'name' => 'Template Email', 'group' => 'System'],
            ['key' => 'tasks', 'name' => 'Attivita', 'group' => 'System'],
        ];

        foreach ($resources as $resource) {
            Resource::query()->updateOrCreate(
                ['app_name' => 'admin', 'key' => $resource['key']],
                ['name' => $resource['name'], 'group' => $resource['group'], 'min_plan' => 'BASE'],
            );
        }
    }
}
