<?php

namespace App\Tool;

use App\Service\Sql\SqlService;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Exposes the SqlService as a callable AI tool.
 *
 * The agent can call this tool autonomously — multiple times, with different
 * natural-language questions — to collect all the data it needs before
 * composing its final answer. Each call triggers a full AI-generated SQL
 * SELECT executed via DBAL.
 */
#[AsTool(
    name: 'query_database',
    description: 'Interroga il database in linguaggio naturale per ottenere dati su
        corsi, utenti, iscrizioni, recensioni e progressi degli studenti.
        Chiama questo tool ogni volta che hai bisogno di dati dal database.
        Puoi invocarlo più volte con domande diverse per raccogliere
        tutte le informazioni necessarie prima di rispondere.'
)]
class DatabaseQueryTool
{
    /**
     * @param SqlService $sqlService Handles AI SQL generation and safe DBAL execution.
     */
    public function __construct(
        private readonly SqlService $sqlService,
    ) {
    }

    /**
     * Executes a natural-language database question and returns the result
     * as a compact, human-readable string suitable for agent consumption.
     *
     * @param string $question The natural-language question about the data to retrieve.
     *
     * @return string Formatted result set, or an error message on failure.
     */
    public function __invoke(string $question): string
    {
        try {
            $result = $this->sqlService->query($question);
        } catch (\RuntimeException $e) {
            return 'Errore nella query: ' . $e->getMessage();
        }

        if (empty($result['rows'])) {
            return 'Nessun risultato trovato per questa domanda.';
        }

        $lines   = [];
        $lines[] = "Risultati ({$result['total']} righe):";
        $lines[] = implode(' | ', $result['columns']);
        $lines[] = str_repeat('—', 60);

        // Cap at 100 rows to avoid overloading the context window.
        $displayRows = array_slice($result['rows'], 0, 100);
        foreach ($displayRows as $row) {
            $lines[] = implode(' | ', array_map(
                static fn ($v) => $v !== null ? (string) $v : 'NULL',
                array_values($row)
            ));
        }

        if ($result['total'] > 100) {
            $lines[] = '... e altri ' . ($result['total'] - 100) . ' risultati non mostrati.';
        }

        return implode("\n", $lines);
    }
}
