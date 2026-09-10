<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'hassistosrl@gmail.com',
                //   'is_super_admin' => true,
            ],
            [
                'name' => 'Sergio Bracale',
                'email' => 'sergio.bracale@races.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Mario',
                'email' => 'mario@globaladvisory.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Roberto Perna',
                'email' => 'segreteria@races.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Eustachio Allegretti',
                'email' => 'eustachio.allegretti@races.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Michele Ferri',
                'email' => 'avvferrimichele@gmail.com',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Framcesco Maiello',
                'email' => 'avvocatofrancescomaiello@gmail.com',
                //  'is_super_admin' => false,
            ],
        ];

        foreach ($users as $userData) {
            if (! User::where('email', $userData['email'])->exists()) {
                $user = User::factory()->create($userData);
                $user->save();
            }
        }
        $this->call([
            OrganizationSeeder::class,
            RemediationSeeder::class,
            CompanySeeder::class,
            WebsiteSeeder::class,
            BranchSeeder::class,
            MailAccountSeeder::class,
            CompanyRoleSeeder::class,
            EmployeeSeeder::class,
            EmployeeTypeSeeder::class,
            ResourceSeeder::class,
            EmailTemplateSeeder::class,
            OamCodeSeeder::class,
            DocumentTypeSeeder::class,
            TaskSeeder::class,
            TaskDocumentTypeSeeder::class,
            SuspiciousActivityReportSeeder::class,
            AuditSeeder::class,
            ComplaintRegistrySeeder::class,
            DocumentSeeder::class,
            TrainingRecordSeeder::class,
        ]);
    }
}
