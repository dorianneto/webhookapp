<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Endpoint;

use App\Application\UseCase\Endpoint\ListEndpointsUseCase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources/{sourceId}/endpoints', name: 'list_endpoints', methods: ['GET'])]
final class ListEndpointsController
{
    public function __construct(
        private readonly ListEndpointsUseCase $listEndpointsUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(string $sourceId): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $endpoints = $this->listEndpointsUseCase->execute($sourceId);

        return new JsonResponse(array_map(static fn($endpoint) => [
            'id'        => $endpoint->getId(),
            'sourceId'  => $endpoint->getSourceId(),
            'url'       => $endpoint->getUrl(),
            'createdAt' => $endpoint->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $endpoints));
    }
}
