<?php

namespace App\Controller;

use App\Service\Advisor\AdvisorService;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides a conversational AI advisor that answers natural-language questions
 * about the platform database using autonomous multi-step tool calling.
 *
 * Unlike the SQL assistant, this controller returns a synthesised natural-language
 * answer rather than raw query results. The agent decides autonomously how many
 * database queries to run and in what order.
 */
class AdvisorController extends AbstractController
{
    private const MAX_QUESTION_LENGTH = 1000;

    /**
     * @param AdvisorService $advisorService Orchestrates multi-step agent tool calls.
     */
    public function __construct(
        private readonly AdvisorService $advisorService,
    ) {
    }

    /**
     * Renders the advisor interface.
     *
     * @return Response HTML page with the question form and answer area.
     */
    #[Route('/advisor', name: 'advisor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('advisor/index.html.twig');
    }

    /**
     * Accepts a natural-language question, runs the multi-step agent and returns
     * its synthesised answer as JSON.
     *
     * Expected JSON body: `{ "question": "..." }`
     *
     * @param Request $request The incoming POST request with JSON body.
     *
     * @return JsonResponse
     *   Success: `{ answer: string }`
     *   Error  : `{ error: string }` with an appropriate HTTP status code.
     */
    #[Route('/advisor/ask', name: 'advisor_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);

        if (!is_array($body)) {
            return $this->json(['error' => 'Invalid request body.'], Response::HTTP_BAD_REQUEST);
        }

        $question = trim((string) ($body['question'] ?? ''));

        if ($question === '') {
            return $this->json(
                ['error' => 'La domanda non può essere vuota.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            return $this->json(
                ['error' => sprintf('La domanda non può superare %d caratteri.', self::MAX_QUESTION_LENGTH)],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $answer = $this->advisorService->ask($question);

            return $this->json(['answer' => $answer]);
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
}
