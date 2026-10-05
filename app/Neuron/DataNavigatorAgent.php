<?php

declare(strict_types=1);

namespace App\Neuron;

use App\Models\ChatMessage;
use App\Models\SchemaLegend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\MySQL\MySQLSchemaTool;
use NeuronAI\Tools\Toolkits\MySQL\MySQLSelectTool;
use PDO;
use RuntimeException;

/**
 * Assistente che traduce domande in linguaggio naturale in query SQL di sola
 * lettura sul database collegato dalla connessione read-only (`dbai`).
 *
 * Il DOMINIO (tabelle principali + regole di sicurezza/di dominio del system
 * prompt) è scelto tramite un "profilo" di `config/data_navigator.php`,
 * risolto dal nome del database realmente collegato: così lo stesso agente
 * serve più clienti (coorte HIV, mediatore creditizio, ...) puntando `dbai`
 * al database giusto. Sola lettura: esposti solo gli strumenti di schema e SELECT.
 */
class DataNavigatorAgent extends Agent
{
    protected ?string $threadId = null;

    protected PDO $pdo;

    protected PDO $pdodbai;

    protected string $connectionName;

    protected string $databaseName;

    /**
     * Profilo di dominio attivo.
     *
     * @var array{label?: string, databases?: list<string>, tables?: list<string>, prompt?: string, background?: string, steps?: list<string>, output?: list<string>}
     */
    protected array $profile;

    protected string $profileKey;

    public function __construct()
    {
        parent::__construct();

        $this->connectionName = (string) config('data_navigator.connection', 'dbai');

        $this->pdo = DB::connection()->getPdo();
        $this->pdodbai = DB::connection($this->connectionName)->getPdo();
        $this->databaseName = DB::connection($this->connectionName)->getDatabaseName();

        [$this->profileKey, $this->profile] = $this->resolveProfile();
    }

    public function setThreadId(string $threadId): self
    {
        $this->threadId = $threadId;

        return $this;
    }

    /**
     * Profilo di dominio: forzato da `data_navigator.profile`, altrimenti quello
     * il cui elenco `databases` contiene il database collegato, altrimenti il
     * profilo di default.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function resolveProfile(): array
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = (array) config('data_navigator.profiles', []);

        if ($profiles === []) {
            throw new RuntimeException('Nessun profilo definito in config/data_navigator.php.');
        }

        $forced = config('data_navigator.profile');

        // Un DATA_NAVIGATOR_PROFILE che non corrisponde a una chiave nota (es.
        // per errore vi è finito il nome del database) viene ignorato: si passa
        // al match per nome database e infine al profilo di default.
        $key = (is_string($forced) && isset($profiles[$forced]) ? $forced : null)
            ?: collect($profiles)
                ->search(fn (array $profile): bool => in_array(
                    $this->databaseName,
                    (array) ($profile['databases'] ?? []),
                    true,
                ))
            ?: config('data_navigator.default');

        if (! is_string($key) || ! isset($profiles[$key])) {
            throw new RuntimeException(
                "Nessun profilo DataNavigator per il database '{$this->databaseName}'. "
                .'Aggiungine uno in config/data_navigator.php o imposta DATA_NAVIGATOR_PROFILE.'
            );
        }

        return [$key, $profiles[$key]];
    }

    protected function provider(): AIProviderInterface
    {
        $key = env('ANTHROPIC_API_KEY') ?: env('ANTHROPIC_KEY');

        if (blank($key)) {
            throw new RuntimeException('Chiave API Anthropic mancante: imposta ANTHROPIC_API_KEY nel file .env');
        }

        return new Anthropic(
            key: (string) $key,
            model: (string) env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            max_tokens: 8192,
        );
    }

    protected function instructions(): string
    {
        // Escape hatch: un profilo può fornire un prompt grezzo con i segnaposto
        // {schema}, {database} e {connection}.
        if (! empty($this->profile['prompt'])) {
            return str_replace(
                ['{schema}', '{database}', '{connection}'],
                [$this->schemaSection(), $this->databaseName, $this->connectionName],
                (string) $this->profile['prompt'],
            );
        }

        return (string) new SystemPrompt(
            background: [
                trim((string) ($this->profile['background'] ?? '')),
                $this->schemaSection(),
            ],
            steps: array_values((array) ($this->profile['steps'] ?? [])),
            output: array_values((array) ($this->profile['output'] ?? [])),
        );
    }

    /**
     * Tabelle principali del profilo attivo.
     *
     * @return list<string>
     */
    protected function profileTables(): array
    {
        return array_values(array_filter(
            (array) ($this->profile['tables'] ?? []),
            'is_string',
        ));
    }

    /**
     * Sezione di schema costruita dalla legenda (SchemaLegend) per le tabelle
     * principali del profilo attivo: nomi reali dei campi, commento, categoria
     * data e valori di lookup ammessi. Le legende sono filtrate per il database
     * collegato (o senza database assegnato, per retrocompatibilità).
     */
    protected function schemaSection(): string
    {
        $tables = $this->profileTables();

        // Legende del database collegato; se non ancora sincronizzate per quel
        // database si ripiega su quelle senza database assegnato (retrocompat).
        $baseQuery = fn (): Builder => SchemaLegend::query()
            ->with('columns')
            ->whereIn('table_name', $tables)
            ->orderBy('order');

        $legends = $baseQuery()->where('database', $this->databaseName)->get();

        if ($legends->isEmpty()) {
            $legends = $baseQuery()->whereNull('database')->get();
        }

        $legends = $legends->unique('table_name')->values();

        if ($legends->isEmpty()) {
            return 'SCHEMA: legenda non ancora sincronizzata per il database '
                ."`{$this->databaseName}` (tabelle attese: ".implode(', ', $tables).'). '
                .'Usa gli strumenti di ispezione del database (schema, tabelle, colonne) '
                .'prima di scrivere qualsiasi query.';
        }

        $out = ["# SCHEMA (connessione {$this->connectionName} — database {$this->databaseName})"];

        foreach ($legends as $legend) {
            $out[] = '';
            $out[] = "## {$legend->table_name}".($legend->description ? " — {$legend->description}" : '');

            foreach ($legend->columns as $column) {
                $line = "- `{$column->name}` ({$column->data_type})";

                if (! $column->nullable) {
                    $line .= ' NOT NULL';
                }

                if ($column->comment) {
                    $line .= ' — '.Str::limit(preg_replace('/\s+/', ' ', $column->comment), 140, '…');
                }

                if ($column->date_category !== null) {
                    $line .= ' [DATE reale]';
                }

                if (is_array($column->lookup_values) && $column->lookup_values !== []) {
                    $values = collect($column->lookup_values)
                        ->map(fn (array $value): string => (string) ($value['value'] ?? ''))
                        ->map(fn (string $value): string => $value === '' ? "''" : $value)
                        ->take(40)
                        ->implode(', ');
                    $line .= " — valori ammessi: {$values}";
                } elseif ($column->lookup_table !== null) {
                    $line .= " — riferimento a `{$column->lookup_table}`";
                }

                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    /**
     * @return array<int, ToolInterface>
     */
    protected function tools(): array
    {
        return [
            MySQLSchemaTool::make($this->pdodbai),
            MySQLSelectTool::make($this->pdodbai),
        ];
    }

    protected function chatHistory(): ChatHistoryInterface
    {
        if ($this->threadId === null) {
            throw new RuntimeException('Thread ID non impostato: chiama setThreadId() prima di usare l\'agente.');
        }

        return new EloquentChatHistory(
            threadId: $this->threadId,
            modelClass: ChatMessage::class,
            contextWindow: 150000,
        );
    }
}
