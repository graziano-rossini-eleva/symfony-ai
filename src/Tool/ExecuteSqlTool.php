<?php

namespace App\Tool;

use Doctrine\DBAL\Connection;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Executes a raw SQL SELECT query via Doctrine DBAL and returns formatted results.
 *
 * WHY THIS TOOL EXISTS (AND WHY IT DIFFERS FROM DatabaseQueryTool)
 * ----------------------------------------------------------------
 * `DatabaseQueryTool` translates a natural-language question into SQL by calling
 * a second Claude agent internally. This double-AI approach is intentional for
 * the Advisor feature, where the agent does not carry SQL knowledge.
 *
 * The Report agent is different: its system prompt already contains the full
 * database schema, so Claude can write SQL directly. Using `DatabaseQueryTool`
 * here would cause N inner API calls on top of the outer agentic loop, doubling
 * (or more) the total Anthropic request count and reliably triggering rate limits.
 *
 * This tool eliminates those inner calls entirely: the report agent writes the
 * SQL, this tool validates it is a SELECT and executes it. Zero extra API calls.
 */
#[AsTool(
    name: 'execute_sql',
    description: <<<DESC
        Esegue una query SQL SELECT sul database e restituisce i risultati formattati.
        Usa questo tool ogni volta che hai bisogno di dati dal database.
        Puoi invocarlo più volte con query diverse.

        Scrivi la query SQL basandoti sullo schema nel system prompt.
        Regole obbligatorie:
        - Solo SELECT: nessun INSERT, UPDATE, DELETE, DROP, ALTER.
        - Usa i nomi di tabella e colonna esatti dello schema (snake_case).
        - Filtra sempre i record eliminati: WHERE <tabella>.deleted = 0
        - Nessun LIMIT: la paginazione è gestita dall'applicazione.

        Parametro "sql": la query SQL SELECT completa e corretta da eseguire.
        DESC
)]
class ExecuteSqlTool
{
    /**
     * @param Connection $connection Doctrine DBAL connection used to run raw SELECT queries.
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Validates and executes the SQL query, returning a human-readable result table.
     *
     * Markdown code fences are stripped before validation to tolerate minor
     * formatting artefacts. Any statement that is not a bare SELECT is rejected
     * before reaching the database.
     *
     * @param string $sql The SQL SELECT query to execute.
     *
     * @return string Formatted result set, or a plain-text error message on failure.
     */
    public function __invoke(string $sql): string
    {
        $sql = trim($sql);

        // Strip markdown code fences the model may add despite instructions.
        $sql = (string) preg_replace('/^```(?:sql)?\s*/i', '', $sql);
        $sql = (string) preg_replace('/\s*```$/m', '', $sql);
        $sql = trim($sql);

        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            return 'Errore: solo istruzioni SELECT sono consentite. Riscrivi la query.';
        }

        try {
            $stmt    = $this->connection->executeQuery($sql);
            $rows    = $stmt->fetchAllAssociative();
            $columns = !empty($rows) ? array_keys($rows[0]) : [];
        } catch (\Throwable $e) {
            return 'Errore SQL: ' . $e->getMessage();
        }

        if (empty($rows)) {
            return 'Nessun risultato trovato per questa query.';
        }

        $total = count($rows);
        $lines = [];
        $lines[] = "Risultati ({$total} righe):";
        $lines[] = implode(' | ', $columns);
        $lines[] = str_repeat('—', 60);

        // Cap at 100 rows to avoid overloading the context window.
        foreach (array_slice($rows, 0, 100) as $row) {
            $lines[] = implode(' | ', array_map(
                static fn ($v) => $v !== null ? (string) $v : 'NULL',
                array_values($row)
            ));
        }

        if ($total > 100) {
            $lines[] = '... e altri ' . ($total - 100) . ' risultati non mostrati.';
        }

        return implode("\n", $lines);
    }
}
