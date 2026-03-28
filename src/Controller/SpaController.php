<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{reactRouting}', name: 'spa', requirements: ['reactRouting' => '^(?!api|in).*'], priority: -10)]
class SpaController
{
    public function __invoke(string $kernel_project_dir): Response
    {
        $html = file_get_contents($kernel_project_dir . '/public/build/index.html');
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
