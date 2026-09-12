<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <x-filament::section icon="heroicon-o-chat-bubble-left-right" icon-color="primary">
            <x-slot name="heading">
                Chiedi all'Assistente AI
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Risponde usando il manuale operativo dell'applicazione. Se non trova la risposta nel manuale, te lo dirà invece di inventarla.
            </p>

            <form wire:submit="send" class="space-y-4">
                <x-filament::input.wrapper>
                    <x-filament::input.textarea
                        wire:model="prompt"
                        rows="3"
                        placeholder="Es: come emetto un proforma?"
                    />
                </x-filament::input.wrapper>

                <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                    Chiedi
                </x-filament::button>
            </form>

            <div wire:loading wire:target="send" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Sto cercando nel manuale...
            </div>

            @if ($error)
                <div class="mt-4 rounded-lg bg-danger-50 dark:bg-danger-500/10 p-4 text-sm text-danger-700 dark:text-danger-400">
                    {{ $error }}
                </div>
            @endif

            @if ($answer)
                <div class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-800 p-4 prose dark:prose-invert max-w-none">
                    {!! Str::markdown($answer) !!}
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
