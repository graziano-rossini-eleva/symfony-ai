<?php

namespace App\Service\Dql;

use Doctrine\DBAL\Connection;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Translates a natural-language database question into a safe SQL SELECT query
 * using the Claude AI agent, then executes it through Doctrine DBAL.
 *
 * Isolation of responsibilities:
 *  - Schema loading  : reads doc/db.md from disk once per request
 *  - Query generation: sends schema + user prompt to Claude; strips markdown artefacts
 *  - Safety check    : rejects any non-SELECT statement before execution
 *  - Execution       : uses DBAL Connection (read-only SELECT, no ORM overhead)
 */
class DqlService
{
    /**
     * @param AgentInterface $default    Symfony AI agent wired to the Anthropic Claude model.
     * @param Connection     $connection Doctrine DBAL connection used to run raw SQL.
     * @param string         $projectDir Kernel project directory, resolved via #[Autowire].
     */
    public function __construct(
        private readonly AgentInterface $default,
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Accepts a natural-language prompt, asks Claude to produce a SQL SELECT,
     * executes it safely and returns the result set.
     *
     * @param string $prompt The user's question in plain Italian or English.
     *
     * @return array{sql: string, columns: list<string>, rows: list<array<string, mixed>>, total: int}
     *
     * @throws \RuntimeException If the schema file is missing, the AI returns a non-SELECT
     *                           statement, or query execution fails.
     */
    public function query(string $prompt): array
    {
        $schema = $this->loadSchema();
        $sql    = $this->generateSql($schema, $prompt);

        return $this->execute($sql);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Reads the database schema markdown from doc/db.md.
     *
     * @return string Raw schema content.
     *
     * @throws \RuntimeException If the file cannot be read.
     */
    private function loadSchema(): string
    {
        $path = $this->projectDir . '/doc/db.md';

        if (!is_readable($path)) {
            throw new \RuntimeException('Database schema file (doc/db.md) not found or not readable.');
        }

        return (string) file_get_contents($path);
    }

    /**
     * Asks Claude to generate a SQL SELECT query for the given prompt.
     *
     * The system prompt injects the full schema and imposes strict rules:
     * respond with SQL only, no markdown, no explanation, SELECT only.
     *
     * @param string $schema The raw db.md content.
     * @param string $prompt The user's natural-language question.
     *
     * @return string A clean SQL SELECT string, ready for execution.
     *
     * @throws \RuntimeException If the AI response is not a SELECT statement.
     */
    private function generateSql(string $schema, string $prompt): string
    {
        $systemPrompt = <<<PROMPT
Sei un assistente SQL esperto per MySQL 8. Il tuo unico compito è generare query SQL SELECT
basandoti sul prompt dell'utente e sullo schema del database fornito.

REGOLE ASSOLUTE — rispettale senza eccezioni:
1. Rispondi ESCLUSIVAMENTE con la query SQL. Zero spiegazioni, zero markdown,
   zero backtick, zero commenti prima o dopo la query.
2. Usa solo istruzioni SELECT. Non generare mai INSERT, UPDATE, DELETE, DROP,
   ALTER, CREATE, TRUNCATE o qualsiasi altra istruzione di modifica.
3. Usa i nomi di tabella e colonna ESATTAMENTE come definiti nello schema (snake_case).
4. Se la richiesta non è soddisfabile con i dati disponibili, scrivi:
   SELECT 'Dati non disponibili nel database' AS messaggio
5. Applica JOIN, WHERE, ORDER BY, GROUP BY dove necessario per rispondere alla domanda.
6. Non aggiungere LIMIT: la paginazione è gestita dall'applicazione.
7. Filtra sempre i record soft-deleted con WHERE <tabella>.deleted = 0
   (o AND <tabella>.deleted = 0 nelle JOIN) a meno che l'utente non chieda
   esplicitamente i record eliminati.

SCHEMA DEL DATABASE:
{$schema}
PROMPT;

        $messages = new MessageBag(
            new SystemMessage($systemPrompt),
            new UserMessage(new Text($prompt)),
        );

        $result = $this->default->call($messages);
        $sql    = trim((string) $result->getContent());

        // Strip markdown code fences the model may have added despite instructions.
        $sql = (string) preg_replace('/^```(?:sql)?\s*/i', '', $sql);
        $sql = (string) preg_replace('/\s*```$/m', '', $sql);
        $sql = trim($sql);

        // Safety guard: reject anything that is not a bare SELECT.
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            throw new \RuntimeException(
                'The AI generated a non-SELECT statement. Execution refused for safety.'
            );
        }

        return $sql;
    }

    /**
     * Executes the validated SQL query via DBAL and returns structured results.
     *
     * @param string $sql A safe, pre-validated SELECT query.
     *
     * @return array{sql: string, columns: list<string>, rows: list<array<string, mixed>>, total: int}
     *
     * @throws \RuntimeException If DBAL execution fails (syntax error, unknown column, etc.).
     */
    private function execute(string $sql): array
    {
        try {
            $result  = $this->connection->executeQuery($sql);
            $rows    = $result->fetchAllAssociative();
            $columns = !empty($rows) ? array_keys($rows[0]) : [];

            return [
                'sql'     => $sql,
                'columns' => $columns,
                'rows'    => $rows,
                'total'   => count($rows),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Query execution failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
