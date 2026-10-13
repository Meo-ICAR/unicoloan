<?php

namespace App\Services\Agenti;

use App\Enums\UserRole;
use App\Filament\Resources\Fornitores\FornitoreResource;
use App\Models\Fornitore;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * Permette ad admin/super_admin di entrare nel portale agenti come il produttore collegato.
 */
class AgentImpersonationService
{
    public const SESSION_KEY = 'agenti_impersonation';

    public function canImpersonate(?User $actor, Fornitore $fornitore): bool
    {
        if ($actor === null || ! $this->actorMayImpersonate($actor)) {
            return false;
        }

        return $this->resolveAgentUser($fornitore) !== null;
    }

    public function isImpersonating(): bool
    {
        return filled(Session::get(self::SESSION_KEY.'.impersonator_id'));
    }

    /**
     * Avvia l'impersonazione e restituisce l'URL del portale agenti.
     */
    public function start(User $actor, Fornitore $fornitore): string
    {
        if (! $this->canImpersonate($actor, $fornitore)) {
            throw new RuntimeException('Impersonazione non consentita per questo produttore.');
        }

        $agent = $this->resolveAgentUser($fornitore);

        if ($agent === null) {
            throw new RuntimeException('Utente agente non disponibile.');
        }

        Auth::login($agent);
        Session::regenerate();
        Session::put(self::SESSION_KEY, [
            'impersonator_id' => $actor->getKey(),
            'fornitore_id' => $fornitore->getKey(),
        ]);

        activity('agenti')
            ->causedBy($actor)
            ->performedOn($fornitore)
            ->event('impersonazione_iniziata')
            ->withProperties([
                'fornitore_id' => $fornitore->getKey(),
                'agent_user_id' => $agent->getKey(),
                'impersonator_id' => $actor->getKey(),
            ])
            ->log('Impersonazione portale agenti avviata');

        return Filament::getPanel('agenti')->getUrl();
    }

    /**
     * Termina l'impersonazione e restituisce l'URL di ritorno nell'admin.
     */
    public function leave(): string
    {
        $context = Session::get(self::SESSION_KEY, []);
        $impersonatorId = $context['impersonator_id'] ?? null;
        $fornitoreId = $context['fornitore_id'] ?? null;

        if ($impersonatorId === null) {
            throw new RuntimeException('Nessuna impersonazione attiva.');
        }

        $impersonator = User::query()->find($impersonatorId);

        if ($impersonator === null || ! $this->actorMayImpersonate($impersonator)) {
            Session::forget(self::SESSION_KEY);
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            throw new RuntimeException('Impersonatore non più valido.');
        }

        $agent = Auth::user();

        Auth::login($impersonator);
        Session::regenerate();
        Session::forget(self::SESSION_KEY);

        activity('agenti')
            ->causedBy($impersonator)
            ->event('impersonazione_terminata')
            ->withProperties([
                'fornitore_id' => $fornitoreId,
                'agent_user_id' => $agent instanceof User ? $agent->getKey() : null,
                'impersonator_id' => $impersonator->getKey(),
            ])
            ->log('Impersonazione portale agenti terminata');

        if ($fornitoreId !== null && Fornitore::query()->whereKey($fornitoreId)->exists()) {
            return FornitoreResource::getUrl('edit', ['record' => $fornitoreId], panel: 'admin');
        }

        return FornitoreResource::getUrl(panel: 'admin');
    }

    public function actorMayImpersonate(User $actor): bool
    {
        return in_array($actor->role, [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value], true);
    }

    private function resolveAgentUser(Fornitore $fornitore): ?User
    {
        if ($fornitore->user_id === null) {
            return null;
        }

        $user = User::query()->find($fornitore->user_id);

        if ($user === null || $user->role !== UserRole::AGENT->value) {
            return null;
        }

        if (! $user->canAccessPanel(Filament::getPanel('agenti'))) {
            return null;
        }

        return $user;
    }
}
