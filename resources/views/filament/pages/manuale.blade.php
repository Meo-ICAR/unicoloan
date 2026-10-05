<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <x-filament::section icon="heroicon-o-information-circle" icon-color="primary">
            <x-slot name="heading">
                Benvenuto in Proforma
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <p>Questo manuale ti aiuterà a navigare tra le funzionalità principali.</p>
                <p>Controlla innazitutto che i produttori abbiano la partita IVA, l'email ed il tipo Enasarco compilata ed eventualmente siano valorizzati i contributi mensili ed il recupero mensile anticipazioni</p>

                <h3>1. Emissione Proforma</h3>
                <p>Per calcolare il contributo, accedi alla sezione <strong>Provvigioni</strong> metti la spunta alla provvigioni desiderate POI e clicca sul pulsante "Emetti proforma".</p>

                <h3>2. Filtri Avanzati</h3>
                <p>Puoi filtrare i dati  cliccando sul simbolo dell'imbuto sulla destra della tabella.</p>
            </div>
        </x-filament::section>

        <!-- Image Gallery Section -->
        <x-filament::section>
            <x-slot name="heading">
                Istruzioni e Guide
            </x-slot>

           <!-- ... existing code ... -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
     <x-filament::card>
            <div class="p-4">
            <h4 class="font-medium">Ricerca</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Scrivere nel campo di ricerca per filtrare i risultati</p>
        </div>
         <div class="flex justify-center p-4">
            <img
                src="{{ asset('cerca.png') }}"
                alt="Cerca"
                class="max-w-full h-auto"
                style="max-height: 100px; width: auto;"
            />
        </div>

    </x-filament::card>
<br>
 <div class="border rounded-lg overflow-hidden shadow-sm">
   <x-filament::card>

            <div class="p-4">
            <h4 class="font-medium">Ricerca Completa</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Premere su imbuto per vedere tutte le opzioni di ricerca</p>
        </div>
        <div class="flex justify-center p-4">
            <img
                src="{{ asset('cercacompleto.png') }}"
                alt="Ricerca Completa"
                class="max-w-full h-auto"
                style="max-height: 300px; width: auto;"
            />
        </div>

    </x-filament::card>
<br>
   <x-filament::card>

    <div class="border rounded-lg overflow-hidden shadow-sm">
        <div class="p-4">
            <h4 class="font-medium">Colonne</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Personalizza l'ordine delle colonne visualizzate</p>
        </div>
    <div class="flex justify-center p-4">
            <img
                src="{{ asset('colonne.png') }}"
                alt="Ricerca Completa"
                class="max-w-full h-auto"
                style="max-height: 200px; width: auto;"
            />
        </div>

    </div>
  </x-filament::card>

<br>
   <x-filament::card>
    <div class="border rounded-lg overflow-hidden shadow-sm">
           <div class="p-4">
            <h4 class="font-medium">Selezione</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Selezionare di più elementi "spuntandoli" </p>
        </div>
    <div class="flex justify-center p-4">
            <img
                src="{{ asset('seleziona.png') }}"
                alt="Ricerca Completa"
                class="max-w-full h-auto"
                style="max-height: 200px; width: auto;"
            />
        </div>



    </div>
  </x-filament::card>
<br>
   <x-filament::card>
    <div class="border rounded-lg overflow-hidden shadow-sm">
               <div class="p-4">
            <h4 class="font-medium">Barra Strumenti</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Tasti che appaiono nella barra superiore quando si selezionano elementi</p>
        </div>
    <div class="flex justify-center p-4">
            <img
               src="{{ asset('tastiheader.png') }}"
                alt="Ricerca Completa"
                class="max-w-full h-auto"
                style="max-height: 200px; width: auto;"
            />
        </div>



    </div>
</div>
  </x-filament::card>
  <br>
   <x-filament::card>
<div class="border rounded-lg overflow-hidden shadow-sm">
  <div class="p-4">
            <h4 class="font-medium">Raggruppamento</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Raggruppmento dei dati</p>
        </div>
    <div class="flex justify-center p-4">
            <img
                src="{{ asset('raggruppa.png') }}"
                alt="Ricerca Completa"
                class="max-w-full h-auto"
                style="max-height: 200px; width: auto;"
            />
        </div>


    </div>
  </x-filament::card>

<!-- ... rest of the code ... -->
        </x-filament::section>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-filament::card>
                <h4 class="font-bold">Supporto Tecnico</h4>
                <p class="text-sm text-gray-500">Contatta l'assistenza a: info@hassisto.com</p>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>
