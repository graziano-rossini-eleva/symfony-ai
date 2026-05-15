<?php

namespace App\Controller;

use App\Service\FileParser\FileParserService;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the file parsing feature.
 *
 * Allows users to upload a PDF file and provide a natural-language prompt
 * describing which data to extract. Returns the extracted data as JSON.
 *
 * All routes require an authenticated user with at least ROLE_USER.
 */
#[Route('/file-parser')]
#[IsGranted('ROLE_USER')]
class FileParserController extends AbstractController
{
    /** Maximum allowed PDF upload size: 10 MB. */
    private const MAX_FILE_BYTES = 10485760;

    /** Maximum character length for the extraction prompt. */
    private const MAX_PROMPT_LENGTH = 1000;

    /**
     * @param FileParserService   $fileParserService Service that extracts data from uploaded documents.
     * @param TranslatorInterface $translator        Translator used for user-facing error strings.
     */
    public function __construct(
        private readonly FileParserService $fileParserService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Renders the file parser upload form.
     *
     * @return Response HTML response with the file parser landing view.
     */
    #[Route('', name: 'file_parser', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('file_parser/index.html.twig');
    }

    /**
     * Accepts a PDF upload and an extraction prompt, then returns structured JSON data.
     *
     * Validates CSRF token, MIME type, file size, and prompt length before
     * delegating to FileParserService.
     *
     * @param Request $request Multipart POST request containing `pdf_file` and `prompt`.
     *
     * @return JsonResponse JSON object with `data` (extracted structure) on success,
     *                      or an `error` string with the appropriate HTTP status code.
     */
    #[Route('/parse', name: 'file_parser_parse', methods: ['POST'])]
    public function parse(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('file_parser', $request->request->get('_csrf_token'))) {
            return $this->json(
                ['error' => $this->translator->trans('file_parser.error.invalid_csrf')],
                Response::HTTP_FORBIDDEN
            );
        }

        $file = $request->files->get('pdf_file');

        if (!$file || $file->getMimeType() !== 'application/pdf') {
            return $this->json(
                ['error' => $this->translator->trans('file_parser.error.invalid_file')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($file->getSize() > self::MAX_FILE_BYTES) {
            return $this->json(
                ['error' => $this->translator->trans('file_parser.error.file_too_large')],
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE
            );
        }

        $prompt = trim($request->request->get('prompt', ''));

        if ($prompt === '') {
            return $this->json(
                ['error' => $this->translator->trans('file_parser.error.empty_prompt')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            return $this->json(
                ['error' => $this->translator->trans('file_parser.error.prompt_too_long')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Move the upload to a private temp path outside the webroot before processing,
        // then delete it unconditionally in the finally block.
        $privateTmpDir = sys_get_temp_dir() . '/file_parser_uploads';
        if (!is_dir($privateTmpDir)) {
            mkdir($privateTmpDir, 0700, true);
        }
        $privatePath = $privateTmpDir . '/' . bin2hex(random_bytes(16)) . '.pdf'; // @phpstan-ignore-line
        $file->move(dirname($privatePath), basename($privatePath));

        try {
            $data = $this->fileParserService->extract($privatePath, $prompt);
            return $this->json(['data' => $data]);
        } catch (RateLimitExceededException) {
            return $this->json(
                ['error' => $this->translator->trans('error.rate_limit')],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        } catch (\RuntimeException) {
            return $this->json(
                ['error' => $this->translator->trans('file_parser.error.extraction_failed')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Always remove the private temp file, regardless of outcome.
            if (file_exists($privatePath)) {
                unlink($privatePath);
            }
        }
    }
}
