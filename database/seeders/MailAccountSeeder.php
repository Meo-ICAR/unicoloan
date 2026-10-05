<?php

namespace Database\Seeders;

use App\Models\MailAccount;
use Illuminate\Database\Seeder;

class MailAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MailAccount::create([
            'name' => 'Amministrazione PEC Races',
            'email_address' => 'amministrazione@pec.racesfinance.it',
            'is_pec' => 1,
            'incoming_protocol' => 'imap',
            'incoming_host' => 'imaps.pec.aruba.it',
            'incoming_port' => 993,
            'incoming_username' => 'amministrazione@pec.racesfinance.it',
            'incoming_password' => 'SuperSecretPecPassword2026!',
            'incoming_encryption' => 'ssl',
            'smtp_host' => 'smtps.pec.aruba.it',
            'smtp_port' => 465,
            'smtp_username' => 'amministrazione@pec.racesfinance.it',
            'smtp_password' => 'SuperSecretPecPassword2026!',
            'smtp_encryption' => 'ssl',
            'is_active' => 1,
            // Associazione polimorfica usando l'UUID stringa della Company
            'mailable_type' => 'company',
            'mailable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
        ]);

        MailAccount::create([
            'name' => 'Compilance PEC Races',
            'email_address' => 'compilance@races.it',
            'is_pec' => 1,
            'incoming_protocol' => 'imap',
            'incoming_host' => 'imaps.pec.aruba.it',
            'incoming_port' => 993,
            'incoming_username' => 'compilance@races.it',
            'incoming_password' => 'SuperSecretPecPassword2026!',
            'incoming_encryption' => 'ssl',
            'smtp_host' => 'smtps.pec.aruba.it',
            'smtp_port' => 465,
            'smtp_username' => 'compilance@races.it',
            'smtp_password' => 'SuperSecretPecPassword2026!',
            'smtp_encryption' => 'ssl',
            'is_active' => 1,
            // Associazione polimorfica usando l'UUID stringa della Company
            'mailable_type' => 'company',
            'mailable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
        ]);
    }
}
