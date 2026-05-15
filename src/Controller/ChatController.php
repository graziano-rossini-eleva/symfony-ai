<?php

namespace App\Controller;

use App\Service\AI\ChatService;
use App\Service\Mail\SupportEmailService;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the AI-powered chat interface for software support sessions.
 *
 * Manages user conversations with the Claude AI agent, using a Markdown
 * documentation file uploaded via HomeController as the knowledge base.
 * When the agent determines that human intervention is needed, it triggers
 * an email escalation to the support team.
 */
class ChatController extends AbstractController
{
    /**
     * @param ChatService         $chatService         AI service that processes user questions.
     * @param SupportEmailService $supportEmailService Mail service that dispatches support-request emails.
     * @param TranslatorInterface $translator          Translator used for user-facing error strings.
     */
    public function __construct(
        private readonly ChatService $chatService,
        private readonly SupportEmailService $supportEmailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Renders the chat interface for an active documentation session.
     *
     * Requires a `doc_name` session key set by the upload flow; redirects to
     * the home page if no documentation has been loaded yet.
     *
     * @param Request $request Current HTTP request carrying the session.
     *
     * @return Response Rendered chat page or redirect to the home route.
     */
    #[Route('/chat', name: 'chat_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $docName = $session->get('doc_name');

        if ($docName === null) {
            return $this->redirectToRoute('home');
        }

        return $this->render('chat/index.html.twig', [
            'projectName' => $docName,
        ]);
    }

    /**
     * Processes a user chat message and returns the AI agent reply.
     *
     * Validates that a documentation context exists in the session, delegates to
     * ChatService, and returns the response together with a flag indicating whether
     * an email escalation was offered by the agent.
     *
     * @param Request $request POST request with a JSON body containing `message`.
     *
     * @return JsonResponse JSON object with `reply` (string) and `offer_email` (bool),
     *                      or an error payload with the appropriate HTTP status code.
     */
    #[Route('/chat/message', name: 'chat_message', methods: ['POST'])]
    public function message(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $docContext = $session->get('doc_context');

        if ($docContext === null) {
            return $this->json(
                ['error' => $this->translator->trans('error.session_expired')],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode($request->getContent(), true);

        // Guard: json_decode returns null for invalid JSON.
        if (!is_array($data)) {
            return $this->json(
                ['error' => $this->translator->trans('error.invalid_request')],
                Response::HTTP_BAD_REQUEST
            );
        }

        $question = trim($data['message'] ?? '');

        if ($question === '') {
            return $this->json(
                ['error' => $this->translator->trans('error.empty_message')],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->chatService->ask($docContext, $question);
        } catch (RateLimitExceededException) {
            return $this->json(
                ['error' => $this->translator->trans('error.rate_limit')],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        return $this->json($result);
    }

    /**
     * Sends a support request email with the full chat transcript attached.
     *
     * Accepts the user's name, email address, and conversation history from the
     * JSON request body. Validates and sanitises input, then delegates assembly
     * and dispatch to SupportEmailService.
     *
     * @param Request $request Current HTTP request with a JSON body containing
     *                         `name` (string), `email` (string), and `history` (array).
     *
     * @return JsonResponse JSON object with `success: true` on success, or an error payload
     *                      with HTTP 400 if the contact data is invalid.
     */
    #[Route('/chat/send-email', name: 'chat_send_email', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $projectName = $session->get('doc_name') ?? 'N/D';

        $data = json_decode($request->getContent(), true);

        // Guard: json_decode returns null for invalid JSON.
        if (!is_array($data)) {
            return $this->json(
                ['error' => $this->translator->trans('error.invalid_request')],
                Response::HTTP_BAD_REQUEST
            );
        }

        $name = trim($data['name'] ?? '');
        $userEmail = trim($data['email'] ?? '');

        if ($name === '' || $userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['error' => $this->translator->trans('error.invalid_contact_data')],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Sanitise history: must be an array, capped at 100 entries.
        // Each entry must have a valid role and a string text capped at 2000 characters.
        $rawHistory = is_array($data['history'] ?? null) ? $data['history'] : [];
        $rawHistory = array_slice($rawHistory, 0, 100);
        $history = [];
        foreach ($rawHistory as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $role = $entry['role'] ?? '';
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $text = is_string($entry['text'] ?? null) ? $entry['text'] : '';
            $text = substr($text, 0, 2000);
            $history[] = ['role' => $role, 'text' => $text];
        }

        $this->supportEmailService->send($name, $userEmail, $projectName, $history);

        return $this->json(['success' => true]);
    }
}
