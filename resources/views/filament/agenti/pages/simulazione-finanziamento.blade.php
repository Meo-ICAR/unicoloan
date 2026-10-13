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
                    <dt class="text-gray-500 dark:text-gray-400">Tipo rapporto</dt>
                    <dd class="font-medium">{{ $profilo['tipo_rapporto'] }}</dd>
                </div>
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
    @endif
</x-filament-panels::page>
