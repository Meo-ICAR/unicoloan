<?php

namespace App\Services\Agenti;

use App\Enums\AgentAccessOutcome;
use App\Enums\UserRole;
use App\Models\PROFORMA\Fornitore;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Abilita un fornitore al portale agenti: crea l'utente (ruolo agent), lo collega al fornitore
 * e invia l'email per impostare la password al primo accesso.
 */
class AgentAccessService
{
    public function enable(Fornitore $fornitore): AgentAccessOutcome
    {
        if ($fornitore->user_id !== null) {
            return AgentAccessOutcome::AlreadyEnabled;
        }

        $email = Str::lower(trim((string) $fornitore->email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return AgentAccessOutcome::MissingEmail;
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return AgentAccessOutcome::EmailInUse;
        }

        $user = DB::connection('mysql')->transaction(fn (): User => User::query()->create([
            'name' => (string) ($fornitore->name ?: $email),
            'email' => $email,
            'role' => UserRole::AGENT->value,
        ]));

        $fornitore->forceFill(['user_id' => $user->getKey()])->save();

        activity('agenti')
            ->event('accesso_portale_creato')
            ->withProperties(['fornitore_id' => $fornitore->getKey(), 'user_id' => $user->getKey()])
            ->log('Accesso al portale agenti creato');

        $this->sendPasswordSetup($user);

        return AgentAccessOutcome::Created;
    }

    /**
     * Link per scegliere la password (pannello agenti); la scadenza e' quella del broker delle password.
     */
    private function sendPasswordSetup(User $user): void
    {
        $token = Password::broker()->createToken($user);

        $notification = app(ResetPassword::class, ['token' => $token]);
        $notification->url = Filament::getPanel('agenti')->getResetPasswordUrl($token, $user);

        $user->notify($notification);
    }
}
