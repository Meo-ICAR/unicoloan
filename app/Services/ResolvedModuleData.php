<?php

namespace App\Services;

use App\Enums\ModuleSourceKey;

/**
 * Valori grezzi risolti per una pratica, indicizzati per valore di ModuleSourceKey.
 * Un valore e' "assente" se e' null o stringa vuota; false e 0 sono valori validi.
 */
final class ResolvedModuleData
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public readonly array $values) {}

    public function get(ModuleSourceKey $key): mixed
    {
        return $this->values[$key->value] ?? null;
    }

    public function has(ModuleSourceKey $key): bool
    {
        $value = $this->get($key);

        return $value !== null && $value !== '';
    }

    public function isTruthy(ModuleSourceKey $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * Confronto di stringa con il valore risolto (enum -> valore, null -> '').
     */
    public function equals(ModuleSourceKey $key, string $value): bool
    {
        $actual = $this->get($key);

        if ($actual instanceof \BackedEnum) {
            $actual = $actual->value;
        }

        return (string) $actual === $value;
    }

    /**
     * @param  iterable<ModuleSourceKey>  $keys
     * @return array<int, ModuleSourceKey>
     */
    public function missingAmong(iterable $keys): array
    {
        $missing = [];

        foreach ($keys as $key) {
            if (! $this->has($key) && ! in_array($key, $missing, true)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
