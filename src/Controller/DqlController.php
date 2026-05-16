<?php

namespace App\Controller;

use App\Service\Dql\DqlService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides a natural-language SQL assistant backed by the Claude AI agent.
 *
 * The user submits a plain-text question; the DqlService translates it into a
 * safe SQL SELECT, executes it, and returns the result set as JSON.
 * The template handles rendering and client-side pagination entirely in JavaScript.
 */
class DqlController extends AbstractController
{
    private const MAX_PROMPT_LENGTH = 1000;

    /**
     * @param DqlService $dqlService Handles AI query generation and safe SQL execution.
     */
    public function __construct(
        private readonly DqlService $dqlService,
    ) {
    }

    /**
     * Renders the DQL assistant interface.
     *
     * @return Response HTML page with prompt form and results area.
     */
    #[Route('/dql', name: 'dql', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dql/index.html.twig');
    }

    /**
     * Accepts a natural-language prompt, generates a SQL SELECT via AI and executes it.
     *
     * Expected JSON body: `{ "prompt": "..." }`
     *
     * @param Request $request The incoming POST request with JSON body.
     *
     * @return JsonResponse
     *   Success: `{ sql, columns, rows, total }`
     *   Error  : `{ error: "..." }` with an appropriate HTTP status code.
     */
    #[Route('/dql/query', name: 'dql_query', methods: ['POST'])]
    public function query(Request $request): JsonResponse
    {
        // Validate Content-Type and body size.
        $body = json_decode((string) $request->getContent(), true);

        if (!is_array($body)) {
            return $this->json(['error' => 'Invalid request body.'], Response::HTTP_BAD_REQUEST);
        }

        $prompt = trim((string) ($body['prompt'] ?? ''));

        if ($prompt === '') {
            return $this->json(
                ['error' => 'Il prompt non può essere vuoto.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (mb_strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            return $this->json(
                ['error' => sprintf('Il prompt non può superare %d caratteri.', self::MAX_PROMPT_LENGTH)],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $result = $this->dqlService->query($prompt);

            return $this->json($result);
        } catch (\RuntimeException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
