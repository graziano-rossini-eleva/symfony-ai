<?php

namespace App\Tool;

use App\Service\Sql\SqlService;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Exception\RateLimitExceededException;

/**
 * Exposes the SqlService as a callable AI tool.
 *
 * The agent can call this tool autonomously — multiple times, with different
 * natural-language questions — to collect all the data it needs before
 * composing its final answer. Each call triggers a full AI-generated SQL
 * SELECT executed via DBAL.
 *
 * RATE-LIMIT HANDLING
 * -------------------
 * Every invocation of this tool triggers an inner Anthropic API call (SQL
 * generation inside SqlService). When the agent calls this tool N times in
 * rapid succession, those N inner calls stack on top of the outer agent call
 * and can quickly exhaust the per-minute request quota.
 *
 * Two mitigations are applied:
 *  1. A fixed 2-second pause before each call spreads the load over time.
 *  2. On RateLimitExceededException the call is retried up to MAX_RETRIES
 *     times with linear back-off (RETRY_DELAY_SECONDS × attempt number),
 *     giving the API quota time to recover without failing the whole report.
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
     * Seconds to sleep before every call to pace concurrent tool invocations
     * and reduce the risk of hitting the Anthropic per-minute request quota.
     */
    private const PRE_CALL_PAUSE_SECONDS = 2;

    /**
     * Maximum number of retry attempts on RateLimitExceededException.
     * After this many failures the error is returned as a string to the agent.
     */
    private const MAX_RETRIES = 3;

    /**
     * Base delay in seconds between retries; multiplied by the attempt number
     * (attempt 1 → 10 s, attempt 2 → 20 s, attempt 3 → 30 s).
     */
    private const RETRY_DELAY_SECONDS = 10;

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
     * Each call sleeps for PRE_CALL_PAUSE_SECONDS before hitting the AI to
     * spread the load when the tool is invoked multiple times in one agent
     * turn. On rate-limit errors the call is retried with linear back-off
     * up to MAX_RETRIES times before giving up and returning an error string.
     *
     * @param string $question The natural-language question about the data to retrieve.
     *
     * @return string Formatted result set, or an error message on failure.
     */
    public function __invoke(string $question): string
    {
        // Pace consecutive tool calls to stay within Anthropic's request quota.
        sleep(self::PRE_CALL_PAUSE_SECONDS);

        $result = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = $this->sqlService->query($question);
                break;
            } catch (RateLimitExceededException) {
                if ($attempt === self::MAX_RETRIES) {
                    return sprintf(
                        'Errore: limite di richieste AI raggiunto dopo %d tentativi. '
                        . 'Attendi qualche secondo e ripeti la domanda.',
                        self::MAX_RETRIES
                    );
                }
                // Linear back-off: 10 s, 20 s, 30 s …
                sleep(self::RETRY_DELAY_SECONDS * $attempt);
            } catch (\RuntimeException $e) {
                return 'Errore nella query: ' . $e->getMessage();
            }
        }

        if ($result === null) {
            return 'Nessun risultato trovato per questa domanda.';
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
