<?php

namespace App\Filament\Pages;

use App\Neuron\ManualAssistantAgent;
use BackedEnum;
use Filament\Pages\Page;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

/**
 * Assistente AI che risponde a domande sull'uso dell'applicazione, indicizzato
 * da `php artisan manual:sync` (vedi ManualAssistantAgent).
 */
class AssistenteAi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected string $view = 'filament.pages.assistente-ai';

    protected static ?string $navigationLabel = 'Assistente AI';

    protected static ?string $title = 'Assistente AI';

    protected static ?string $slug = 'assistente-ai';

    public string $prompt = '';

    public ?string $answer = null;

    public ?string $error = null;

    public function send(): void
    {
        $this->error = null;
        $this->answer = null;

        if (blank($this->prompt)) {
            return;
        }

        try {
            $reply = ManualAssistantAgent::make()->chat(new UserMessage($this->prompt))->getMessage();
            $this->answer = (string) $reply->getContent();
        } catch (Throwable $e) {
            $this->error = "Non riesco a rispondere in questo momento: {$e->getMessage()}";
        }
    }
}
