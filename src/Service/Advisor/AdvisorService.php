<?php

namespace App\Service\Advisor;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Orchestrates the multi-step AI advisor backed by the `execute_sql` tool.
 *
 * The advisor agent receives the user's question together with the full
 * database schema in the system prompt. It autonomously writes SQL queries,
 * calls `execute_sql` as many times as needed, then synthesises a final
 * natural-language answer in Italian.
 *
 * WHY `execute_sql` INSTEAD OF `query_database`
 * ---------------------------------------------
 * The original `query_database` tool translated natural language to SQL via a
 * second inner Claude call (inside SqlService). This caused N inner API calls
 * on top of the outer agentic loop, reliably hitting Anthropic's rate limit.
 *
 * With the database schema injected directly into this system prompt, the
 * advisor agent can write SQL itself — `execute_sql` only validates and runs
 * it. Total API calls drop from 2N+rounds to just the outer agent rounds.
 */
class AdvisorService
{
    private const SYSTEM_PROMPT_TEMPLATE = <<<PROMPT
Sei un assistente analitico esperto per una piattaforma di corsi online. Rispondi sempre in italiano.

Il tuo compito è rispondere alle domande dell'utente sui dati della piattaforma: corsi, studenti,
istruttori, iscrizioni, recensioni e progressi.

════════════════════════════════════════════════════════════
SCHEMA DEL DATABASE
════════════════════════════════════════════════════════════
{SCHEMA}

════════════════════════════════════════════════════════════
ISTRUZIONI SQL
════════════════════════════════════════════════════════════
- Scrivi query SQL SELECT usando i nomi esatti dello schema (snake_case).
- Filtra sempre i record eliminati: WHERE <tabella>.deleted = 0
- Nessun LIMIT nelle query.
- Solo SELECT: nessun INSERT, UPDATE, DELETE, DROP o ALTER.

════════════════════════════════════════════════════════════
COME OPERARE
════════════════════════════════════════════════════════════
1. Scrivi le query SQL necessarie e chiamale tramite `execute_sql`.
2. Puoi invocare `execute_sql` più volte con query diverse per raccogliere
   tutte le informazioni necessarie.
3. Analizza i dati restituiti e fornisci una risposta chiara, completa e ben strutturata.
4. Se i dati non sono sufficienti o non esistono, dillo esplicitamente.
5. Non inventare dati: rispondi solo in base a ciò che il tool restituisce.
6. Usa elenchi, tabelle testuali o paragrafi in base a cosa rende la risposta più leggibile.
PROMPT;

    private readonly string $systemPrompt;

    /**
     * @param AgentInterface $advisor    Symfony AI agent wired to the Anthropic Claude model
     *                                   with the ExecuteSqlTool in its toolbox.
     * @param string         $projectDir Kernel project directory used to load doc/db.md.
     */
    public function __construct(
        private readonly AgentInterface $advisor,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->systemPrompt = $this->buildSystemPrompt($projectDir);
    }

    /**
     * Sends the user's question to the advisor agent and returns its final answer.
     *
     * The agent may call `execute_sql` multiple times internally before
     * producing the response — the caller receives only the final synthesis.
     *
     * @param string $question The user's natural-language question in Italian or English.
     *
     * @return string The agent's final natural-language answer in Italian.
     */
    public function ask(string $question): string
    {
        $messages = new MessageBag(
            new SystemMessage($this->systemPrompt),
            new UserMessage(new Text($question)),
        );

        $result = $this->advisor->call($messages);

        return trim((string) $result->getContent());
    }

    /**
     * Loads doc/db.md and injects it into the system prompt template.
     *
     * @param string $projectDir Absolute path to the Symfony project root.
     *
     * @return string The fully assembled system prompt.
     *
     * @throws \RuntimeException If doc/db.md cannot be read.
     */
    private function buildSystemPrompt(string $projectDir): string
    {
        $schemaPath = $projectDir . '/doc/db.md';

        if (!is_readable($schemaPath)) {
            throw new \RuntimeException('Database schema file (doc/db.md) not found or not readable.');
        }

        return str_replace('{SCHEMA}', (string) file_get_contents($schemaPath), self::SYSTEM_PROMPT_TEMPLATE);
    }
}
