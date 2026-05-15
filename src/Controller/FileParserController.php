<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Placeholder for the file parsing feature.
 *
 * Will allow users to upload documents (PDF, CSV, DOCX) and extract
 * structured data using the Claude AI agent.
 */
class FileParserController extends AbstractController
{
    #[Route('/file-parser', name: 'file_parser', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('file_parser/index.html.twig');
    }
}
