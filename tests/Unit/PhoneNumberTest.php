<?php

namespace Tests\Unit;

use App\Services\Signature\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'mobile italiano con spazio' => ['338 1234567', '+393381234567'],
            'prefisso 0039' => ['0039 338 1234567', '+393381234567'],
            'prefisso +39 con trattino' => ['+39 338-1234567', '+393381234567'],
            'parentesi e punti' => ['(338) 123.4567', '+393381234567'],
            'estero invariato' => ['+33612345678', '+33612345678'],
            'vuoto' => ['', null],
            'null' => [null, null],
            'non numerico' => ['abc', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_to_e164(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($raw));
    }
}
