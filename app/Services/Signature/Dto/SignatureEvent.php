<?php

namespace App\Services\Signature\Dto;

final readonly class SignatureEvent
{
    public function __construct(
        public string $providerRef,
        public string $type,
        public string $eventId,
    ) {}
}
