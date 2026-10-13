<?php

namespace Tests\Unit;

use App\Enums\FinancingSection;
use App\Enums\FinancingType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FinancingTypeTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?FinancingType}>
     */
    public static function products(): array
    {
        return [
            'Cessione' => ['Cessione', FinancingType::Cessione],
            'Cessione del Quinto Pensione' => ['Cessione del Quinto Pensione', FinancingType::Cessione],
            'Delega' => ['Delega', FinancingType::Delega],
            'ALTRA DELEGAZIONE' => ['ALTRA DELEGAZIONE IMPORTO CONTENUTO', FinancingType::Delega],
            'TFS' => ['TFS', FinancingType::Tfs],
            'TFS - Trattamento Fine Servizio' => ['TFS - Trattamento Fine Servizio', FinancingType::Tfs],
            'Prestito' => ['Prestito', FinancingType::Prestito],
            'CHIROGRAFARIO' => ['CHIROGRAFARIO', FinancingType::Prestito],
            'Mutuo' => ['Mutuo', FinancingType::Mutuo],
            'IPOTECARIO' => ['IPOTECARIO', FinancingType::Mutuo],
            'Aziendale' => ['Aziendale', FinancingType::Impresa],
            'Prestito Aziendale' => ['PRESTITO AZIENDALE', FinancingType::Impresa],
            'Leasing' => ['LEASING', FinancingType::Impresa],
            'Polizza' => ['Polizza', null],
            'vuoto' => ['  ', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('products')]
    public function test_product_resolves_to_its_financing_type(?string $product, ?FinancingType $expected): void
    {
        $this->assertSame($expected, FinancingType::fromProduct($product));
    }

    public function test_without_a_financing_type_every_section_but_the_type_specific_ones_is_visible(): void
    {
        foreach (FinancingSection::cases() as $section) {
            $this->assertSame($section !== FinancingSection::PraticaDatiImmobile, $section->isVisibleFor(null), $section->value);
        }
    }
}
