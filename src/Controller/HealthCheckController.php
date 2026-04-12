<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health', name: 'health_check', methods: ['GET'])]
final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_OK);
    }
}
