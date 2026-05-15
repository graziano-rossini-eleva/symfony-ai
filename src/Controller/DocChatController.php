<?php

namespace App\Controller;

use App\Service\DocChat\ChatService;
use App\Service\DocChat\SupportEmailService;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the documentation chat feature.
 *
 * Allows users to upload a Markdown file as a knowledge base and interact
 * with the Claude AI agent via a chat interface. Supports email escalation
 * to a human support team.
 */
#[Route('/doc-chat')]
class DocChatController extends AbstractController
{
    private const MAX_CONTENT_BYTES = 524288; // 512 KB

    /** Maximum character length accepted for a single chat question. */
    private const MAX_QUESTION_LENGTH = 2000;

    /** Maximum raw request-body size accepted for the send-email endpoint (512 KB). */
    private const MAX_EMAIL_BODY_BYTES = 524288;

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
     * Displays the Markdown upload form.
     */
    #[Route('', name: 'doc_chat', methods: ['GET'])]
    public function upload(): Response
    {
        return $this->render('doc_chat/upload.html.twig');
    }

    /**
     * Processes the uploaded Markdown file and redirects to the chat interface.
     *
     * Validates CSRF token, file type (MIME + extension), and file size before
     * storing the document content and name in the session.
     */
    #[Route('/upload', name: 'doc_chat_upload', methods: ['POST'])]
    public function processUpload(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('upload', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->translator->trans('doc_chat.error.invalid_csrf'));
            return $this->redirectToRoute('doc_chat');
        }

        $file = $request->files->get('doc_file');

        if (!$file || strtolower($file->getClientOriginalExtension()) !== 'md') {
            $this->addFlash('error', $this->translator->trans('doc_chat.error.invalid_file'));
            return $this->redirectToRoute('doc_chat');
        }

        $allowedMimeTypes = ['text/plain', 'text/markdown', 'text/x-markdown'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            $this->addFlash('error', $this->translator->trans('doc_chat.error.invalid_file'));
            return $this->redirectToRoute('doc_chat');
        }

        if ($file->getSize() > self::MAX_CONTENT_BYTES) {
            $this->addFlash('error', $this->translator->trans('doc_chat.error.file_too_large'));
            return $this->redirectToRoute('doc_chat');
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false || trim($content) === '') {
            $this->addFlash('error', $this->translator->trans('doc_chat.error.empty_file'));
            return $this->redirectToRoute('doc_chat');
        }

        if (strlen($content) > self::MAX_CONTENT_BYTES) {
            $this->addFlash('error', $this->translator->trans('doc_chat.error.file_too_large'));
            return $this->redirectToRoute('doc_chat');
        }

        $rawName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = substr(preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $rawName), 0, 80);

        $session = $request->getSession();
        $session->set('doc_context', $content);
        $session->set('doc_name', $safeName);

        return $this->redirectToRoute('doc_chat_chat');
    }

    /**
     * Renders the chat interface for an active documentation session.
     *
     * Requires a `doc_name` session key set by the upload flow; redirects to
     * the upload page if no documentation has been loaded yet.
     */
    #[Route('/chat', name: 'doc_chat_chat', methods: ['GET'])]
    public function chat(Request $request): Response
    {
        $docName = $request->getSession()->get('doc_name');

        if ($docName === null) {
            return $this->redirectToRoute('doc_chat');
        }

        return $this->render('doc_chat/index.html.twig', [
            'projectName' => $docName,
        ]);
    }

    /**
     * Processes a user chat message and returns the AI agent reply.
     *
     * @param Request $request POST request with a JSON body containing `message`.
     *
     * @return JsonResponse JSON object with `reply` (string) and `offer_email` (bool),
     *                      or an error payload with the appropriate HTTP status code.
     */
    #[Route('/message', name: 'doc_chat_message', methods: ['POST'])]
    public function message(Request $request): JsonResponse
    {
        $docContext = $request->getSession()->get('doc_context');

        if ($docContext === null) {
            return $this->json(
                ['error' => $this->translator->trans('error.session_expired')],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode($request->getContent(), true);

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

        if (strlen($question) > self::MAX_QUESTION_LENGTH) {
            return $this->json(
                ['error' => $this->translator->trans('error.message_too_long')],
                Response::HTTP_UNPROCESSABLE_ENTITY
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
     * @param Request $request POST request with a JSON body containing
     *                         `name` (string), `email` (string), and `history` (array).
     *
     * @return JsonResponse JSON object with `success: true` on success, or an error payload.
     */
    #[Route('/send-email', name: 'doc_chat_send_email', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        if ($request->headers->get('Content-Length') > self::MAX_EMAIL_BODY_BYTES
            || strlen($request->getContent()) > self::MAX_EMAIL_BODY_BYTES
        ) {
            return $this->json(
                ['error' => $this->translator->trans('error.request_too_large')],
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE
            );
        }

        $projectName = $request->getSession()->get('doc_name') ?? 'N/D';

        $data = json_decode($request->getContent(), true);

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

        $this->supportEmailService->send($name, $userEmail, $projectName, $data['history'] ?? []);

        return $this->json(['success' => true]);
    }
}
