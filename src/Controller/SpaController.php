<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{reactRouting}', name: 'spa', requirements: ['reactRouting' => '^(?!api|in|health).*'], priority: -10)]
class SpaController
{
    public function __invoke(#[Autowire('%kernel.project_dir%')] string $projectDir): Response
    {
        $html = file_get_contents($projectDir . '/public/build/index.html');
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
