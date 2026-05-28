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

#[Route('/code-chat')]
class CodeChatController extends AbstractController
{
    private const MAX_QUESTION_LENGTH = 2000;

    public function __construct(
        private readonly CodeChatService $codeChatService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'code_chat', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('code_chat/index.html.twig');
    }

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
