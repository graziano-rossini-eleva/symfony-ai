<?php

namespace App\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tool pattern: OUTPUT
 *
 * Persists the generated report as a Markdown file and makes it available
 * for browser download via a secure token.
 *
 * This is the fourth of four tools that together implement the architectural
 * pattern: retrieval → context → computation → output.
 *
 * WHY THIS TOOL EXISTS
 * --------------------
 * Without an output tool, the agent's final report would exist only as a text
 * string inside the API response. This tool introduces a side effect: the
 * content is written to disk, a cryptographically random token is generated,
 * and the controller can retrieve that token after the agent call completes
 * to offer the user a download link — all without exposing the file path.
 *
 * ROLE IN THE ORCHESTRATION
 * -------------------------
 * Called as the very last step, after `query_database` has gathered all data
 * and `calculate_statistics` has computed precise aggregates. The agent
 * assembles the complete Markdown report and passes it here. The final answer
 * to the user is then a brief summary; the full report is available as a file.
 *
 * TOKEN SECURITY
 * --------------
 * Files are stored in `var/reports/` (outside `public/`) with a 128-bit random
 * hex prefix. The download controller validates the token against a strict
 * allowlist regex before resolving the file path, preventing path traversal.
 */
#[AsTool(
    name: 'save_report',
    description: <<<DESC
        Salva il report generato come file Markdown (.md) scaricabile dal browser.
        Chiamare come ULTIMO passo, dopo aver raccolto tutti i dati e calcolato le statistiche.
        Dopo aver chiamato questo tool, fornisci all'utente una breve sintesi (3-5 frasi)
        del contenuto del report generato.

        Parametri:
        - "title":   titolo del report, usato come nome file
                     (es. "Report Salute Piattaforma Maggio 2026")
        - "content": contenuto completo del report in formato Markdown,
                     con sezioni, intestazioni e tutti i dati raccolti
        DESC
)]
class SaveReportTool
{
    /**
     * Token of the most recently saved report file, without extension.
     * Format: "{32-char hex}_{url-safe-slug}".
     * Null if no report has been saved in the current request.
     *
     * @var string|null
     */
    private ?string $lastSavedToken = null;

    /**
     * @param string $projectDir Symfony kernel project directory, injected via #[Autowire].
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Writes the report content to `var/reports/{token}_{slug}.md` and stores
     * the token so the controller can expose a download link after the agent call.
     *
     * @param string $title   Human-readable report title; used to derive the filename slug.
     * @param string $content Full Markdown content of the report.
     *
     * @return string Confirmation message returned to the agent.
     *
     * @throws \RuntimeException If the reports directory cannot be created or the file cannot be written.
     */
    public function __invoke(string $title, string $content): string
    {
        $token = bin2hex(random_bytes(16));
        $slug  = $this->slugify($title);

        $dir = $this->projectDir . '/var/reports';

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create reports directory: ' . $dir);
        }

        $filename = $token . '_' . $slug . '.md';
        $bytes    = file_put_contents($dir . '/' . $filename, $content);

        if ($bytes === false) {
            throw new \RuntimeException('Unable to write report file: ' . $dir . '/' . $filename);
        }

        $this->lastSavedToken = $token . '_' . $slug;

        return 'Report salvato con successo. Il download sarà disponibile nell\'interfaccia utente.';
    }

    /**
     * Returns the token of the last saved report, or null if none was saved.
     *
     * The controller calls this method after the agent completes its run to
     * include the download token in the JSON response without the agent needing
     * to expose it explicitly in its final text answer.
     *
     * @return string|null Token string used to construct the download URL, or null.
     */
    public function getLastSavedToken(): ?string
    {
        return $this->lastSavedToken;
    }

    /**
     * Converts a title string into a URL-safe, lowercase, hyphen-delimited slug.
     *
     * Non-alphanumeric sequences are replaced with hyphens, leading/trailing
     * hyphens are trimmed, and the result is capped at 80 characters to keep
     * filenames manageable.
     *
     * @param string $title The raw title string.
     *
     * @return string A URL-safe slug, e.g. "report-salute-piattaforma-maggio-2026".
     */
    private function slugify(string $title): string
    {
        $slug = strtolower($title);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 80);

        return $slug !== '' ? $slug : 'report';
    }
}
