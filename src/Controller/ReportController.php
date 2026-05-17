<?php

namespace App\Controller;

use App\Service\Report\ReportService;
use App\Tool\SaveReportTool;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes the multi-tool report-generation agent through three endpoints.
 *
 * This controller is part of the "Report Analitico" feature, which demonstrates
 * a four-tool agentic orchestration pattern:
 *
 *   retrieval → context → computation → output
 *
 * The `generate` action triggers the full pipeline; the `download` action serves
 * the file that `SaveReportTool` persisted to `var/reports/` during that run.
 * The download token is read directly from the `SaveReportTool` shared service
 * instance, which stores it as instance state after the agent call completes.
 */
#[Route('/report')]
class ReportController extends AbstractController
{
    private const MAX_PROMPT_LENGTH = 1000;

    /**
     * @param ReportService $reportService  Orchestrates the four-phase agent pipeline.
     * @param SaveReportTool $saveReportTool Shared tool instance; holds the download token
     *                                       after the agent call so the controller can
     *                                       return it to the browser without parsing the
     *                                       agent's text response.
     * @param string         $projectDir     Kernel project directory, resolved via #[Autowire].
     */
    public function __construct(
        private readonly ReportService $reportService,
        private readonly SaveReportTool $saveReportTool,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Renders the report-generation interface.
     *
     * @return Response HTML page with the prompt form and result area.
     */
    #[Route('', name: 'report', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('report/index.html.twig');
    }

    /**
     * Runs the multi-tool agent pipeline for the given prompt and returns
     * the agent's summary and a download token as JSON.
     *
     * The agent autonomously executes: get_current_date → query_database (×N)
     * → calculate_statistics (×N) → save_report (×1). This controller reads
     * the download token from `SaveReportTool` after the agent call, rather
     * than extracting it from the agent's text response.
     *
     * Expected JSON body: `{ "prompt": "..." }`
     *
     * @param Request $request The incoming POST request with JSON body.
     *
     * @return JsonResponse
     *   Success: `{ summary: string, downloadToken: string|null }`
     *   Error  : `{ error: string }` with an appropriate HTTP status code.
     */
    #[Route('/generate', name: 'report_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);

        if (!is_array($body)) {
            return $this->json(['error' => 'Corpo della richiesta non valido.'], Response::HTTP_BAD_REQUEST);
        }

        $prompt = trim((string) ($body['prompt'] ?? ''));

        if ($prompt === '') {
            return $this->json(
                ['error' => 'La richiesta non può essere vuota.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (mb_strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            return $this->json(
                ['error' => sprintf('La richiesta non può superare %d caratteri.', self::MAX_PROMPT_LENGTH)],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $summary       = $this->reportService->generate($prompt);
            $downloadToken = $this->saveReportTool->getLastSavedToken();

            return $this->json([
                'summary'       => $summary,
                'downloadToken' => $downloadToken,
            ]);
        } catch (RateLimitExceededException) {
            return $this->json(
                ['error' => 'Troppe richieste verso l\'AI. Attendi qualche secondo e riprova.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        } catch (\Throwable $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Streams the generated report file as a browser download.
     *
     * The token is validated against a strict allowlist regex before it is used
     * to resolve the file path, preventing any path traversal attack.
     * Files are served from `var/reports/` (outside `public/`) exclusively
     * through this action, so no report is ever directly accessible via URL.
     *
     * @param string $token The report token returned by the `generate` action.
     *                      Format: "{32-char lowercase hex}_{url-safe slug}".
     *
     * @return BinaryFileResponse Triggers a file download in the browser.
     *
     * @throws NotFoundHttpException If the token format is invalid or the file does not exist.
     */
    #[Route('/download/{token}', name: 'report_download', methods: ['GET'])]
    public function download(string $token): BinaryFileResponse
    {
        // Strict allowlist: 32-char hex + underscore + lowercase alphanumeric slug.
        // Rejects any attempt at path traversal (../, /, null bytes, etc.).
        if (!preg_match('/^[a-f0-9]{32}_[a-z0-9][a-z0-9-]{0,79}$/', $token)) {
            throw new NotFoundHttpException('Report not found.');
        }

        $path = $this->projectDir . '/var/reports/' . $token . '.md';

        if (!is_file($path)) {
            throw new NotFoundHttpException('Report not found.');
        }

        // Extract the human-readable slug part (after the 33-char "hex_" prefix)
        // and use it as the suggested download filename.
        $slug         = substr($token, 33);
        $downloadName = ($slug !== '' ? $slug : 'report') . '.md';

        return $this->file(
            $path,
            $downloadName,
            ResponseHeaderBag::DISPOSITION_ATTACHMENT
        );
    }
}
