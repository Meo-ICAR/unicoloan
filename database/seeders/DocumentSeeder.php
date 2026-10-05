<?php

namespace Database\Seeders;

use App\Models\PROFORMA\Fornitore;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company_id = Company::first()->id;
        $tasks = Task::where('is_active', true)->get();

        $fornitori = Fornitore::where('is_active', true)->get();
        $employees = Employee::where('is_active', true)->get();

        // Manteniamo l'Eager Loading per le performance
        $tasks = Task::with('documentTypes')->where('is_active', true)->get();
        $createdCount = 0;

        foreach ($tasks as $task) {
            // Caso AZIENDA: il record id coincide con il $company_id
            if ($task->taskable === 'company') {
                $createdCount += $task->createDocumentation($company_id, $company_id, true);
            }

            // Caso FORNITORE: cicliamo sui fornitori e passiamo l'id del singolo fornitore
            if ($task->taskable === 'fornitore') {
                foreach ($fornitori as $fornitore) {
                    if ($task->getAvailableFor($fornitore)->contains($task)) {
                        $createdCount += $task->createDocumentation($company_id, $fornitore->id, true);
                    }
                }
            }

            // Caso DIPENDENTE: cicliamo sui dipendenti e passiamo l'id del singolo dipendente
            if (($task->taskable === 'employee')) {
                foreach ($employees as $employee) {
                    if ($task->getAvailableFor($employee)->contains($task)) {
                        $createdCount += $task->createDocumentation($company_id, $employee->id, true);
                    }
                }
            }
        }
    }
}
