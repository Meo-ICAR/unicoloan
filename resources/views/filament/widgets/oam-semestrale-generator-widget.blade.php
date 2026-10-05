<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col md:flex-row items-flex-start md:items-center justify-between gap-4">

            {{-- Testo descrittivo a sinistra --}}
            <div>
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 text-success-500" />
                    Generazione Segnalazione Semestrale OAM (M510)
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Clicca sul pulsante a destra per selezionare il periodo e scaricare il report completo multi-foglio.
                </p>
            </div>

            {{-- Pulsante nativo Filament che lancia la modale --}}

             {{-- Testo descrittivo a sinistr  <div class="flex-shrink-0">   {{ $this->exportOam }}   </div> --}}


        </div>
    </x-filament::section>

    {{-- Questo tag è OBBLIGATORIO per far funzionare le finestre modali dentro i widget di Filament --}}
    <x-filament-actions::modals />
</x-filament-widgets::widget>
