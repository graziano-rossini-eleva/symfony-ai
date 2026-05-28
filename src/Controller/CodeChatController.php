<?php

namespace App\Controller;

use App\Service\CodeChat\CodeChatService;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the Code Chat feature, which allows users to ask questions about the
 * project codebase using a RAG pipeline (symfony/ai-store + Ollama embeddings).
 */
#[Route('/code-chat')]
class CodeChatController extends AbstractController
{
    /** Maximum character length accepted for a single question. */
    private const MAX_QUESTION_LENGTH = 2000;

    /**
     * @param CodeChatService     $codeChatService RAG service that retrieves relevant code chunks and calls Claude.
     * @param TranslatorInterface $translator      Translator used for user-facing error strings.
     */
    public function __construct(
        private readonly CodeChatService $codeChatService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Renders the Code Chat interface.
     */
    #[Route('', name: 'code_chat', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('code_chat/index.html.twig');
    }

    /**
     * Processes a user question and returns an AI answer based on retrieved code chunks.
     *
     * Validates the JSON request body, delegates to CodeChatService, and returns
     * a JSON object with the agent reply or an error payload.
     *
     * @param Request $request POST request with a JSON body containing `message` (string).
     *
     * @return JsonResponse JSON object with `reply` (string) on success, or an error payload
     *                      with the appropriate HTTP status code on failure.
     *
     * @throws RateLimitExceededException When the upstream Anthropic API rate limit is exceeded.
     */
    #[Route('/message', name: 'code_chat_message', methods: ['POST'])]
    public function message(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(
                ['error' => $this->translator->trans('error.invalid_request')],
                Response::HTTP_BAD_REQUEST
            );
        }

        $question = trim($data['message'] ?? '');

        if ('' === $question) {
            return $this->json(
                ['error' => $this->translator->trans('error.empty_message')],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($question) > self::MAX_QUESTION_LENGTH) {
            return $this->json(
                ['error' => $this->translator->trans('error.message_too_long')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $result = $this->codeChatService->ask($question);
        } catch (RateLimitExceededException) {
            return $this->json(
                ['error' => $this->translator->trans('error.rate_limit')],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        return $this->json($result);
    }
}
