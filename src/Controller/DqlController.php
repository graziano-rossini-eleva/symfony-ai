<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Placeholder for the natural language DQL/SQL assistant feature.
 *
 * Will allow users to query the database using plain Italian/English questions,
 * which the Claude AI agent translates into safe read-only DQL queries.
 */
class DqlController extends AbstractController
{
    /**
     * Renders the natural language DQL assistant placeholder page.
     *
     * @return Response HTML response with the DQL assistant landing view
     */
    #[Route('/dql', name: 'dql', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dql/index.html.twig');
    }
}
