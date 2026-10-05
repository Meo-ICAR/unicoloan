<?php
namespace App\Filament\Exports;

use App\Filament\Exports\Sheets\M510AnagraficaSheet;
use App\Filament\Exports\Sheets\M510EconomicoBaseSheet;
use App\Filament\Exports\Sheets\M510InformativoSheet;
use App\Filament\Exports\Sheets\M510PrudenzialeSheet;
use App\Filament\Exports\Sheets\M510SediSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class M510MasterExport implements WithMultipleSheets
{
    use Exportable;

    protected array $datiAnagrafica;
    protected array $datiEconomico;
    protected array $datiPrudenziale;
    protected array $datiInformativo;
    protected array $datiSedi;

    public function __construct(
        array $datiAnagrafica,
        array $datiEconomico,
        array $datiPrudenziale,
        array $datiInformativo,
        array $datiSedi
    ) {
        $this->datiAnagrafica = $datiAnagrafica;
        $this->datiEconomico = $datiEconomico;
        $this->datiPrudenziale = $datiPrudenziale;
        $this->datiInformativo = $datiInformativo;
        $this->datiSedi = $datiSedi;
    }

    public function sheets(): array
    {
        return [
            new M510AnagraficaSheet($this->datiAnagrafica),
            new M510EconomicoBaseSheet($this->datiEconomico),
            new M510PrudenzialeSheet($this->datiPrudenziale),
            new M510InformativoSheet($this->datiInformativo),
            new M510SediSheet($this->datiSedi),
        ];
    }
}
