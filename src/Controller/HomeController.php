<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
}
