<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Landing page that lists all available AI feature demos.
 */
class HomeController extends AbstractController
{
    /**
     * Renders the landing page listing all available AI feature demos.
     *
     * @return Response HTML response with the home page
     */
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    /**
     * Serves a pre-filled claude_desktop_config.json for the MCP server integration.
     *
     * The console path is resolved dynamically from the kernel project directory
     * so the downloaded file works immediately without manual editing.
     *
     * @return JsonResponse Downloadable JSON file with Content-Disposition: attachment.
     */
    #[Route('/mcp-config', name: 'mcp_config_download', methods: ['GET'])]
    public function mcpConfigDownload(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): JsonResponse {
        $config = [
            'mcpServers' => [
                'symfony-ai' => [
                    'command' => 'php',
                    'args' => [$projectDir . '/bin/console', 'mcp:server'],
                ],
            ],
        ];

        $response = new JsonResponse($config, Response::HTTP_OK, [], false);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'claude_desktop_config.json')
        );

        return $response;
    }
}
