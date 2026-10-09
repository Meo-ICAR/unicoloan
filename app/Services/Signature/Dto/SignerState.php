<?php

namespace App\Services\Signature\Dto;

use App\Enums\SignerStatus;
use Carbon\CarbonInterface;

final readonly class SignerState
{
    public function __construct(
        public string $providerSignerRef,
        public SignerStatus $status,
        public ?CarbonInterface $signedAt,
    ) {}
}
