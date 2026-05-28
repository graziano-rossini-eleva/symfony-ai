<?php

namespace App\Tool;

use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Tool pattern: COMPUTATION
 *
 * Calculates descriptive statistics on a JSON-encoded array of numeric values.
 *
 * This is the third of four tools that together implement the architectural
 * pattern: retrieval → context → computation → output.
 *
 * WHY THIS TOOL EXISTS
 * --------------------
 * LLMs are probabilistic text generators — they are not calculators. While a
 * model may produce plausible-looking numbers for mean, median or standard
 * deviation, those values are unreliable on real datasets:
 *
 *  - Floating-point arithmetic is executed symbolically, not numerically.
 *  - The model has no access to the raw rows; it only sees a text summary.
 *  - Rounding and approximation errors compound across multi-step reasoning.
 *
 * Offloading statistical computation to this PHP tool guarantees IEEE 754
 * precision and reproducible results regardless of dataset size.
 *
 * ROLE IN THE ORCHESTRATION
 * -------------------------
 * After `query_database` returns raw numeric columns (ratings, enrollment counts,
 * completion percentages, prices, etc.), the agent extracts the numeric values
 * and passes them as a JSON array to this tool. The tool returns exact statistics
 * that the agent then quotes verbatim in the report, never approximating.
 */
#[AsTool(
    name: 'calculate_statistics',
    description: <<<DESC
        Calcola statistiche descrittive precise (conteggio, media, mediana, minimo, massimo,
        deviazione standard, somma) su un array di valori numerici passato come stringa JSON.

        IMPORTANTE: usa SEMPRE questo tool per calcoli statistici su dati reali del database.
        Non eseguire mai questi calcoli internamente: la precisione floating-point su dataset
        reali richiede un tool dedicato. Anche per array piccoli, usa questo tool.

        Parametro "valuesJson": stringa JSON contenente un array di numeri,
        es. "[4.5, 3.8, 5.0, 4.2, 3.1]"
        DESC
)]
#[McpTool(
    name: 'calculate_statistics',
    description: 'Calculates precise descriptive statistics (count, mean, median, min, max, std dev, sum) on a JSON-encoded array of numeric values.',
)]
class CalculateStatisticsTool
{
    /**
     * Computes descriptive statistics for an array of numbers encoded as JSON.
     *
     * Non-numeric entries in the array are silently discarded. If the resulting
     * clean array is empty, an error message is returned instead of throwing.
     *
     * @param string $valuesJson JSON-encoded array of numeric values, e.g. "[1.5, 2.3, 4.0]".
     *
     * @return string Multi-line string with labelled statistics, or an error message.
     */
    public function __invoke(string $valuesJson): string
    {
        $raw = json_decode($valuesJson, true);

        if (!is_array($raw) || empty($raw)) {
            return 'Errore: parametro non valido o array vuoto. Passa un JSON array di numeri, es. "[1, 2, 3]".';
        }

        // Filter out non-numeric entries silently to tolerate minor AI formatting quirks.
        $values = array_values(array_filter(
            array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $raw),
            fn ($v) => $v !== null
        ));

        if (empty($values)) {
            return 'Errore: nessun valore numerico trovato nell\'array.';
        }

        $n   = count($values);
        $sum = array_sum($values);
        $avg = $sum / $n;

        $sorted = $values;
        sort($sorted);

        $median = $n % 2 === 0
            ? ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2.0
            : $sorted[(int) ($n / 2)];

        // Population standard deviation: suitable for full dataset analysis.
        $variance = array_sum(array_map(fn ($v) => ($v - $avg) ** 2, $values)) / $n;
        $stddev   = sqrt($variance);

        return implode("\n", [
            "Statistiche su {$n} valori:",
            sprintf('- Media:               %.4f', $avg),
            sprintf('- Mediana:             %.4f', $median),
            sprintf('- Minimo:              %.4f', min($values)),
            sprintf('- Massimo:             %.4f', max($values)),
            sprintf('- Deviazione standard: %.4f', $stddev),
            sprintf('- Somma:               %.4f', $sum),
        ]);
    }
}
