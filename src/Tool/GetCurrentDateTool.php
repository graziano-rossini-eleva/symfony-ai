<?php

namespace App\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Tool pattern: CONTEXT
 *
 * Provides the agent with the real-world current date and time.
 *
 * This is the first of four tools that together implement the architectural
 * pattern: retrieval → context → computation → output.
 *
 * WHY THIS TOOL EXISTS
 * --------------------
 * Large language models have a training-data cutoff and no access to a clock.
 * Any response that depends on "today", "this week", "last 30 days", etc. would
 * be based on the model's internal (and wrong) assumption of the current date.
 * Injecting the real date as a tool call result gives the agent a reliable
 * temporal anchor before it constructs date-filtered database queries.
 *
 * ROLE IN THE ORCHESTRATION
 * -------------------------
 * Called as the very first step of the report pipeline. The date returned here
 * is then embedded into the natural-language sub-questions passed to
 * `query_database`, ensuring that "this month" or "last 7 days" resolve to
 * actual calendar ranges rather than arbitrary estimates.
 */
#[AsTool(
    name: 'get_current_date',
    description: <<<DESC
        Restituisce la data e l'ora correnti del server nel fuso orario Europe/Rome.
        Chiamare SEMPRE come primo passo quando la richiesta riguarda un periodo temporale
        (questa settimana, questo mese, ultimi N giorni, ecc.).
        Non accetta parametri.
        DESC
)]
class GetCurrentDateTool
{
    /**
     * Returns the current server date and time in Italian locale format.
     *
     * @return string Date and time string, e.g. "17/05/2026 14:32 (Europe/Rome)".
     */
    public function __invoke(): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Rome'));

        return $now->format('d/m/Y H:i') . ' (Europe/Rome)';
    }
}
