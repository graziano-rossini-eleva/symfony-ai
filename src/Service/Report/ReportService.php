<?php

namespace App\Service\Report;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Orchestrates the multi-tool report-generation agent.
 *
 * This service demonstrates a three-tool agentic pipeline:
 *
 *   1. RETRIEVAL   — `execute_sql`          : fetches raw data from the platform DB
 *   2. COMPUTATION — `calculate_statistics` : derives precise aggregates from raw numbers
 *   3. OUTPUT      — `save_report`          : persists the final Markdown report to disk
 *
 * The current date is injected directly into the system prompt by PHP, removing
 * the need for a `get_current_date` tool call and saving one API round-trip.
 * This is important on the Anthropic free tier (5 req/min, 10K tokens/min).
 *
 * WHY `execute_sql` INSTEAD OF `query_database`
 * ---------------------------------------------
 * `query_database` translates natural language to SQL via a second inner Claude
 * call. The report agent receives the compact schema in its system prompt and
 * writes SQL directly — `execute_sql` only validates and runs it, zero extra
 * API calls per query.
 */
class ReportService
{
    private const SYSTEM_PROMPT_TEMPLATE = <<<PROMPT
Sei un generatore di report analitico professionale per una piattaforma di corsi online.
Rispondi sempre in italiano. Sei preciso, metodico e non inventi mai dati.

Data odierna: {DATE}

════════════════════════════════════════════════════════════
SCHEMA DEL DATABASE
════════════════════════════════════════════════════════════
{SCHEMA}

════════════════════════════════════════════════════════════
ISTRUZIONI SQL
════════════════════════════════════════════════════════════
- Scrivi query SQL SELECT usando i nomi esatti dello schema (snake_case).
- Filtra sempre i record eliminati: WHERE <tabella>.deleted = 0
- Nessun LIMIT nelle query: la paginazione è gestita altrove.
- Solo SELECT: nessun INSERT, UPDATE, DELETE, DROP o ALTER.

════════════════════════════════════════════════════════════
PROCESSO — tre fasi in ordine
════════════════════════════════════════════════════════════

FASE 1 — RACCOLTA DATI  →  tool: execute_sql  (max 3 volte)
────────────────────────────────────────────────────────────
Scrivi query SQL SELECT e chiamale tramite `execute_sql`.
Pianifica le query in modo da coprire tutti gli aspetti necessari
con il minor numero di chiamate possibile (massimo 3).

FASE 2 — CALCOLO STATISTICHE  →  tool: calculate_statistics  (se necessario)
──────────────────────────────────────────────────────────────────────────────
Per serie numeriche significative (rating, percentuali, conteggi) chiama
`calculate_statistics` passando i valori come array JSON.
NON calcolare mai statistiche a mente: usa sempre il tool per precisione.

FASE 3 — OUTPUT  →  tool: save_report  (1 volta)
─────────────────────────────────────────────────
Assembla il report completo in Markdown con:
- Titolo principale con la data odierna
- Sezioni tematiche con intestazioni ## e ###
- Tabelle per i dati tabulari
- Valori statistici esatti dal tool

Chiama `save_report` con titolo e contenuto Markdown completo.
Poi fornisci una sintesi di 3-5 frasi sui punti salienti del report.

════════════════════════════════════════════════════════════
REGOLE GENERALI
════════════════════════════════════════════════════════════
- Non inventare dati: cita solo ciò che i tool restituiscono.
- Se un tool restituisce un errore, segnalalo nella risposta finale.
PROMPT;

    /**
     * Maximum number of attempts before propagating RateLimitExceededException
     * to the controller. Each retry waits RETRY_DELAY_SECONDS × attempt number.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Base wait in seconds between retries (attempt 1 → 30 s, attempt 2 → 60 s).
     */
    private const RETRY_DELAY_SECONDS = 30;

    private readonly string $systemPrompt;

    /**
     * @param AgentInterface $report     Symfony AI agent with ExecuteSqlTool,
     *                                   CalculateStatisticsTool and SaveReportTool.
     * @param string         $projectDir Kernel project directory used to load doc/db_compact.md.
     */
    public function __construct(
        private readonly AgentInterface $report,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->systemPrompt = $this->buildSystemPrompt($projectDir);
    }

    /**
     * Submits the user's report request to the agent and returns its final summary.
     *
     * On RateLimitExceededException the call is retried up to MAX_ATTEMPTS times
     * with linear back-off before propagating the exception to the controller.
     *
     * @param string $prompt The user's natural-language report request in Italian.
     *
     * @return string The agent's final 3-5 sentence summary of the generated report.
     *
     * @throws RateLimitExceededException If all retry attempts are exhausted.
     */
    public function generate(string $prompt): string
    {
        $messages = new MessageBag(
            new SystemMessage($this->systemPrompt),
            new UserMessage(new Text($prompt)),
        );

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $result = $this->report->call($messages);

                return trim((string) $result->getContent());
            } catch (RateLimitExceededException $e) {
                $lastException = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep(self::RETRY_DELAY_SECONDS * $attempt);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Builds the system prompt by injecting the current date and the compact
     * database schema into the template.
     *
     * The date is resolved in PHP so the agent does not need a `get_current_date`
     * tool call, saving one API round-trip per report generation.
     *
     * @param string $projectDir Absolute path to the Symfony project root.
     *
     * @return string The fully assembled system prompt string.
     *
     * @throws \RuntimeException If doc/db_compact.md cannot be read.
     */
    private function buildSystemPrompt(string $projectDir): string
    {
        $schemaPath = $projectDir . '/doc/db_compact.md';

        if (!is_readable($schemaPath)) {
            throw new \RuntimeException('Compact schema file (doc/db_compact.md) not found or not readable.');
        }

        $schema = (string) file_get_contents($schemaPath);
        $date   = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Rome')))->format('d/m/Y');

        return str_replace(['{DATE}', '{SCHEMA}'], [$date, $schema], self::SYSTEM_PROMPT_TEMPLATE);
    }
}
