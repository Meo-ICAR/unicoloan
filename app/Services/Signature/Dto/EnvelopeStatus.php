<?php

namespace App\Services\Signature\Dto;

use App\Enums\SignatureRequestStatus;

final readonly class EnvelopeStatus
{
    /**
     * @param  SignatureRequestStatus  $status  Sent|Signed|Declined|Expired|Cancelled
     * @param  array<int, SignerState>  $signers
     */
    public function __construct(
        public SignatureRequestStatus $status,
        public array $signers,
    ) {}
}
