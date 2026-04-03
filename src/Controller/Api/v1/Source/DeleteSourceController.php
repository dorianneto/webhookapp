<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\DeleteSourceUseCase;
use App\Domain\Exception\SourceNotFoundException;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources/{id}', name: 'delete_source', methods: ['DELETE'])]
final class DeleteSourceController
{
    public function __construct(
        private readonly DeleteSourceUseCase $deleteSourceUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->deleteSourceUseCase->execute($id, $user->getId());
        } catch (SourceNotFoundException) {
            return new JsonResponse(['error' => 'Source not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
