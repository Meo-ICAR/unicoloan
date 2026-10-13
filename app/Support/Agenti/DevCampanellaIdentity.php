<?php

namespace App\Support\Agenti;

use App\Enums\UserRole;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Identità di sviluppo idempotente per simulare il produttore Campanella nel portale agenti.
 */
final class DevCampanellaIdentity
{
    /**
     * @return array{fornitore_name: string, email: string, piva: string}
     */
    public static function config(): array
    {
        /** @var array{fornitore_name: string, email: string, piva: string} $identity */
        $identity = config('agenti.dev_bypass.identity');

        return $identity;
    }

    public static function user(): ?User
    {
        $identity = self::config();

        $fornitore = Fornitore::query()
            ->where('piva', $identity['piva'])
            ->first();

        if ($fornitore?->user_id === null) {
            return null;
        }

        $user = User::query()->find($fornitore->user_id);

        if ($user === null || $user->role !== UserRole::AGENT->value) {
            return null;
        }

        return $user;
    }

    public static function ensure(): User
    {
        $identity = self::config();

        return DB::connection('mysql')->transaction(function () use ($identity): User {
            $user = User::query()->firstOrCreate(
                ['email' => $identity['email']],
                [
                    'name' => $identity['fornitore_name'],
                    'role' => UserRole::AGENT->value,
                ],
            );

            if ($user->role !== UserRole::AGENT->value) {
                $user->forceFill(['role' => UserRole::AGENT->value])->save();
            }

            $fornitore = Fornitore::query()->firstOrCreate(
                ['piva' => $identity['piva']],
                [
                    'name' => $identity['fornitore_name'],
                    'email' => $identity['email'],
                ],
            );

            if ((int) $fornitore->user_id !== (int) $user->getKey()) {
                $fornitore->forceFill([
                    'user_id' => $user->getKey(),
                    'name' => $identity['fornitore_name'],
                    'email' => $identity['email'],
                ])->save();
            }

            return $user->fresh();
        });
    }
}
