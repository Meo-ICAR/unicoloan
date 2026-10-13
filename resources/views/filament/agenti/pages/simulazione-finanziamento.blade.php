<x-filament-panels::page>
    <form wire:submit="simulate">
        {{ $this->form }}
    </form>

    @if ($profilo)
        <x-filament::section heading="Profilo cliente">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tipo contratto</dt>
                    <dd class="font-medium">{{ $profilo['tipo_contratto'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Sesso</dt>
                    <dd class="font-medium">{{ $profilo['sesso'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tipo amministrazione</dt>
                    <dd class="font-medium">{{ $profilo['tipo_rapporto'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Durata</dt>
                    <dd class="font-medium">{{ $profilo['durata_etichetta'] }}</dd>
                </div>
                @if ($profilo['quota_cedibile'] !== null)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Quota cedibile</dt>
                        <dd class="font-medium">€ {{ number_format($profilo['quota_cedibile'], 2, ',', '.') }}</dd>
                    </div>
                @endif
                @if ($profilo['importo_richiesto'] !== null)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Importo richiesto</dt>
                        <dd class="font-medium">€ {{ number_format($profilo['importo_richiesto'], 2, ',', '.') }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Data decorrenza</dt>
                    <dd class="font-medium">{{ $profilo['data_decorrenza'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Età alla decorrenza</dt>
                    <dd class="font-medium">{{ $profilo['eta_decorrenza'] }} anni</dd>
                </div>
                @if ($profilo['anzianita_mesi'] !== null)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Anzianità di servizio alla decorrenza</dt>
                        <dd class="font-medium">{{ intdiv($profilo['anzianita_mesi'], 12) }} anni e {{ $profilo['anzianita_mesi'] % 12 }} mesi</dd>
                    </div>
                @endif
            </dl>
        </x-filament::section>

        @if (! $preventivoDeciso)
            <x-filament::section heading="Memorizzo preventivo?" class="mt-6">
                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="button" wire:click="memorizzaPreventivo" icon="heroicon-o-bookmark">
                        Sì
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="scartaPreventivo">
                        No
                    </x-filament::button>
                </div>
            </x-filament::section>
        @elseif ($preventivoIdentificativo)
            <x-filament::section heading="Preventivo memorizzato" class="mt-6">
                <p class="text-sm">
                    Identificativo del produttore:
                    <strong class="text-lg">{{ $preventivoIdentificativo }}</strong>
                </p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
