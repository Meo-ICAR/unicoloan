<?php

namespace Database\Seeders;

use App\Models\MailAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MailAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $racesId = DB::table('companies')->where('vat_number', '05822361007')->value('id');
        MailAccount::create([
            'name' => 'Amministrazione PEC Races',
            'email_address' => 'amministrazione@pec.racesfinance.it',
            'is_pec' => 1,
            'protocol' => 'imap',
            'imap_host' => 'imaps.pec.aruba.it',
            'imap_port' => 993,
            'imap_username' => 'amministrazione@pec.racesfinance.it',
            'imap_password' => 'SuperSecretPecPassword2026!',
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtps.pec.aruba.it',
            'smtp_port' => 465,
            'smtp_username' => 'amministrazione@pec.racesfinance.it',
            'smtp_password' => 'SuperSecretPecPassword2026!',
            'smtp_encryption' => 'ssl',
            'is_active' => 1,
            // Associazione polimorfica usando l'UUID stringa della Company
            'mailable_type' => 'company',
            'mailable_id' => $racesId,
        ]);

        MailAccount::create([
            'name' => 'Compilance PEC Races',
            'email_address' => 'compilance@races.it',
            'is_pec' => 1,
            'protocol' => 'imap',
            'imap_host' => 'imaps.pec.aruba.it',
            'imap_port' => 993,
            'imap_username' => 'compilance@races.it',
            'imap_password' => 'SuperSecretPecPassword2026!',
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtps.pec.aruba.it',
            'smtp_port' => 465,
            'smtp_username' => 'compilance@races.it',
            'smtp_password' => 'SuperSecretPecPassword2026!',
            'smtp_encryption' => 'ssl',
            'is_active' => 1,
            // Associazione polimorfica usando l'UUID stringa della Company
            'mailable_type' => 'company',
            'mailable_id' => $racesId,
        ]);
    }
}
