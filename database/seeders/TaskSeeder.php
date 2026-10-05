<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            [
                'name' => 'OnBoarding',
                'description' => 'Attività e documenti richiesti per il caricamento di una nuovo produttore.',
                'taskable' => 'fornitore',
                'trigger_field' => 'oam_at',
                'trigger_state' => 'empty',
                'is_active' => false,
            ],
            [
                'name' => 'OAM-Agenti',
                'description' => 'Attività e controlli per il rinnovo periodico OAM',
                'taskable' => 'fornitore',
                'trigger_field' => 'oam_at',
                'trigger_state' => 'filled',
                'exclude_field' => 'ivass_section',
                'exclude_state' => 'equals',
                'exclude_value' => 'E',
            ],
            [
                'name' => 'IVASS-Agenti',
                'description' => 'Attività e controlli per il rinnovo periodico IVASS',
                'taskable' => 'fornitore',
                'trigger_field' => 'ivass_section',
                'trigger_state' => 'equals',
                'trigger_value' => 'E',
            ],
            [
                'name' => 'OAM-dipendenti',
                'description' => 'Attività e controlli per il rinnovo periodico OAM',
                'taskable' => 'employee',
                'trigger_field' => 'oam_at',
                'trigger_state' => 'filled',
            ],
            [
                'name' => 'OAM-cda',
                'description' => 'Attività per il rinnovo periodico OAM dei CdA',
                'taskable' => 'employee',
                'trigger_field' => 'employee_type',
                'trigger_state' => 'equals',
                'trigger_value' => 'cda',
                'exclude_field' => 'oam_at',
                'exclude_state' => 'filled',
            ],
            [
                'name' => 'OAM-Semestrale',
                'description' => 'Documenti aziendali semestrale OAM',
                'taskable' => 'company',
            ],
            [
                'name' => 'Renewal',
                'description' => 'Attività e controlli per il rinnovo periodico delle convenzioni o contratti.',
                'taskable' => 'company',
                'is_active' => false,
            ],
            [
                'name' => 'Audit',
                'description' => 'Attività di controllo conformità e verifica della documentazione interna.',
                'taskable' => 'audit',
                'is_active' => false,
            ],
            [
                'name' => 'Ispezione',
                'description' => 'Attività di ispezione in sede',
                'taskable' => 'audit',
                'is_active' => false,
            ],
            [
                'name' => 'OffBoarding',
                'description' => 'Attività e documenti richiesti per la chiusura di una anagrafica.',
                'taskable' => 'fornitore',
                'trigger_field' => 'dismissed_at',
                'trigger_state' => 'filled',
                'is_active' => false,
            ],
        ];

        foreach ($tasks as $task) {
            Task::firstOrCreate(['name' => $task['name']], $task);
        }
    }
}
