<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Endpoint;

use App\Application\UseCase\Endpoint\DeleteEndpointUseCase;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/endpoints/{id}', name: 'delete_endpoint', methods: ['DELETE'])]
final class DeleteEndpointController
{
    public function __construct(
        private readonly DeleteEndpointUseCase $deleteEndpointUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->deleteEndpointUseCase->execute($id, $user->getId());
        } catch (EndpointNotFoundException | SourceNotFoundException) {
            return new JsonResponse(['error' => 'Endpoint not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
