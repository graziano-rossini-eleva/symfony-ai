<?php

namespace App\Service\Report;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Orchestrates the multi-tool report-generation agent.
 *
 * This service demonstrates a four-step agentic pipeline where each step
 * maps to a distinct tool category:
 *
 *   1. CONTEXT    — `get_current_date`    : anchors the agent to real-world time
 *   2. RETRIEVAL  — `execute_sql`         : fetches raw data from the platform DB
 *   3. COMPUTATION— `calculate_statistics`: derives precise aggregates from raw numbers
 *   4. OUTPUT     — `save_report`         : persists the final Markdown report to disk
 *
 * WHY `execute_sql` INSTEAD OF `query_database`
 * ---------------------------------------------
 * `query_database` (used by the Advisor) translates natural language to SQL via
 * a second inner Claude call. For the report agent this would mean:
 *
 *   outer call → N × inner SQL-generation calls → outer call (results) → …
 *
 * With 4-6 data queries per report that is 9-13 total API calls, reliably
 * hitting Anthropic's per-minute rate limit.
 *
 * The report agent receives the full database schema in its system prompt and
 * can write SQL directly. `execute_sql` just validates and runs it — zero
 * extra API calls, no rate-limit risk.
 *
 * The schema is loaded from `doc/db.md` once per request and injected into the
 * system prompt at construction time.
 */
class ReportService
{
    private const SYSTEM_PROMPT_TEMPLATE = <<<PROMPT
Sei un generatore di report analitico professionale per una piattaforma di corsi online.
Rispondi sempre in italiano. Sei preciso, metodico e non inventi mai dati.

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
- Nessun INSERT, UPDATE, DELETE, DROP o ALTER.

════════════════════════════════════════════════════════════
PROCESSO OBBLIGATORIO — esegui sempre queste quattro fasi in ordine
════════════════════════════════════════════════════════════

FASE 1 — CONTESTO  →  tool: get_current_date
─────────────────────────────────────────────
Chiama `get_current_date` per conoscere la data odierna.
Usala per costruire filtri temporali precisi nelle query (es. ultimi 30 giorni).

FASE 2 — RACCOLTA DATI  →  tool: execute_sql  (N volte)
────────────────────────────────────────────────────────
Scrivi query SQL SELECT e chiamale tramite `execute_sql`.
Ogni chiamata deve coprire un aspetto distinto del report.
Pianifica tutte le query necessarie prima di iniziare.

FASE 3 — CALCOLO STATISTICHE  →  tool: calculate_statistics  (N volte)
────────────────────────────────────────────────────────────────────────
Per ogni serie numerica significativa (rating, percentuali, conteggi, prezzi)
chiama `calculate_statistics` passando i valori come array JSON.
NON calcolare mai statistiche a mente: usa sempre il tool per precisione.

FASE 4 — OUTPUT  →  tool: save_report  (1 volta)
─────────────────────────────────────────────────
Assembla il report completo in Markdown con:
- Titolo principale con data
- Sezioni tematiche con intestazioni ## e ###
- Tabelle per i dati tabulari
- Valori statistici esatti dal tool (non approssimazioni)
- Sezione finale "Metodologia" con le query eseguite

Chiama `save_report` con titolo e contenuto Markdown completo.
Poi fornisci una sintesi di 3-5 frasi sui punti salienti del report.

════════════════════════════════════════════════════════════
REGOLE GENERALI
════════════════════════════════════════════════════════════
- Non inventare dati: cita solo ciò che i tool restituiscono.
- Se un tool restituisce un errore, segnalalo nella risposta finale.
- Il titolo del report deve includere il tipo di analisi e la data odierna.
PROMPT;

    private readonly string $systemPrompt;

    /**
     * @param AgentInterface $report     Symfony AI agent wired to the Anthropic Claude model
     *                                   with all four report tools in its toolbox.
     * @param string         $projectDir Kernel project directory used to load doc/db.md.
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
     * The agent autonomously executes the four-phase pipeline (context → retrieval →
     * computation → output) before producing the summary. The full report is written
     * to disk by `SaveReportTool`; retrieve the download token from that service
     * after this method returns.
     *
     * @param string $prompt The user's natural-language report request in Italian.
     *
     * @return string The agent's final 3-5 sentence summary of the generated report.
     */
    public function generate(string $prompt): string
    {
        $messages = new MessageBag(
            new SystemMessage($this->systemPrompt),
            new UserMessage(new Text($prompt)),
        );

        $result = $this->report->call($messages);

        return trim((string) $result->getContent());
    }

    /**
     * Builds the system prompt by loading the database schema from doc/db.md
     * and injecting it into the template.
     *
     * Having the full schema in the prompt allows the agent to write SQL directly
     * via `execute_sql` without triggering a second AI call for translation,
     * eliminating the rate-limit risk of the double-AI pattern used by Advisor.
     *
     * @param string $projectDir Absolute path to the Symfony project root.
     *
     * @return string The fully assembled system prompt string.
     *
     * @throws \RuntimeException If doc/db.md cannot be read.
     */
    private function buildSystemPrompt(string $projectDir): string
    {
        $schemaPath = $projectDir . '/doc/db.md';

        if (!is_readable($schemaPath)) {
            throw new \RuntimeException('Database schema file (doc/db.md) not found or not readable.');
        }

        $schema = (string) file_get_contents($schemaPath);

        return str_replace('{SCHEMA}', $schema, self::SYSTEM_PROMPT_TEMPLATE);
    }
}
