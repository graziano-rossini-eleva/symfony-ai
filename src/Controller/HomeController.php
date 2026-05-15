<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the landing page and Markdown documentation upload flow.
 *
 * Provides the entry point of the application where users upload a Markdown
 * file that acts as the knowledge base for the AI chat session. On successful
 * upload the document content and name are stored in the session before
 * redirecting to the chat interface.
 */
class HomeController extends AbstractController
{
    /**
     * @param TranslatorInterface $translator Translator used for user-facing flash error messages.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Renders the home page with the documentation upload form.
     *
     * @return Response Rendered home page.
     */
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    /** Maximum allowed file / session content size: 512 KB. */
    private const MAX_CONTENT_BYTES = 524288;

    /** Allowed MIME types for the uploaded documentation file. */
    private const ALLOWED_MIME_TYPES = ['text/plain', 'text/markdown', 'text/x-markdown'];

    /**
     * Processes the uploaded Markdown file and initialises the chat session.
     *
     * Validates the CSRF token, MIME type, file size, and extension before
     * reading the file content. Sanitises the document name by stripping any
     * character that is not alphanumeric, a hyphen, an underscore, or a space,
     * and caps the name at 80 characters. Rejects content that exceeds 512 KB.
     * On success, stores the raw Markdown content under the `doc_context` session
     * key and the sanitised filename (without extension) under `doc_name`, then
     * redirects to the chat interface. On failure, adds a flash error and
     * redirects back to the home page.
     *
     * @param Request $request POST request containing the `doc_file` uploaded file.
     *
     * @return Response Redirect to the chat page on success, or back to home on failure.
     */
    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        // CSRF validation.
        if (!$this->isCsrfTokenValid('upload', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->translator->trans('upload.error.invalid_csrf'));
            return $this->redirectToRoute('home');
        }

        $file = $request->files->get('doc_file');

        if (!$file) {
            $this->addFlash('error', $this->translator->trans('upload.error.invalid_file'));
            return $this->redirectToRoute('home');
        }

        // Secondary defence: extension check.
        if (strtolower($file->getClientOriginalExtension()) !== 'md') {
            $this->addFlash('error', $this->translator->trans('upload.error.invalid_file'));
            return $this->redirectToRoute('home');
        }

        // Primary defence: server-side MIME type check.
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $this->addFlash('error', $this->translator->trans('upload.error.invalid_file'));
            return $this->redirectToRoute('home');
        }

        // File size check before reading into memory.
        if ($file->getSize() > self::MAX_CONTENT_BYTES) {
            $this->addFlash('error', $this->translator->trans('upload.error.file_too_large'));
            return $this->redirectToRoute('home');
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false || trim($content) === '') {
            $this->addFlash('error', $this->translator->trans('upload.error.empty_file'));
            return $this->redirectToRoute('home');
        }

        // Cap session payload size.
        if (strlen($content) > self::MAX_CONTENT_BYTES) {
            $this->addFlash('error', $this->translator->trans('upload.error.file_too_large'));
            return $this->redirectToRoute('home');
        }

        // Sanitise the document name: keep only safe characters, cap at 80.
        $rawName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $rawName);
        $safeName = substr($safeName, 0, 80);

        $session = $request->getSession();
        $session->set('doc_context', $content);
        $session->set('doc_name', $safeName);

        return $this->redirectToRoute('chat_index');
    }
}