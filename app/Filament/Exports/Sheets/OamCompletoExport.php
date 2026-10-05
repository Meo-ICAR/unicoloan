<?php

namespace App\Filament\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OamCompletoExport implements WithMultipleSheets
{
    protected array $datiAnagrafica;

    protected array $datiEconomici;

    protected array $datiInformativo;

    protected array $datiSedi;

    protected array $datiPrudenziale;

    public function __construct(
        array $datiAnagrafica,
        array $datiEconomici,
        array $datiInformativo,
        array $datiSedi,
        array $datiPrudenziale
    ) {
        $this->datiAnagrafica = $datiAnagrafica;
        $this->datiEconomici = $datiEconomici;
        $this->datiInformativo = $datiInformativo;
        $this->datiSedi = $datiSedi;
        $this->datiPrudenziale = $datiPrudenziale;
    }

    /**
     * Ritorna l'array di tutti i fogli che comporranno il file Excel
     */
    public function sheets(): array
    {
        return [
            new M510AnagraficaSheet($this->datiAnagrafica),
            new M510EconomicoBaseSheet($this->datiEconomici),
            new M510InformativoSheet($this->datiInformativo),
            new M510SediSheet($this->datiSedi),
            new M510PrudenzialeSheet($this->datiPrudenziale),
        ];
    }
}
