<?php

namespace App\Services\Signature;

use InvalidArgumentException;

class SignatureProviderManager
{
    /** @var array<string, SignatureProvider> */
    private array $resolved = [];

    public function provider(?string $name = null): SignatureProvider
    {
        $name ??= (string) config('signature.driver');

        return $this->resolved[$name] ??= match ($name) {
            'fake' => new FakeSignatureProvider,
            'yousign' => app(YousignSignatureProvider::class),
            default => throw new InvalidArgumentException("Provider di firma [{$name}] non supportato."),
        };
    }
}
