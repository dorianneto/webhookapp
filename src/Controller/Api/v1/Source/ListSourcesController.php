<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\ListSourcesUseCase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources', name: 'list_sources', methods: ['GET'])]
final class ListSourcesController
{
    public function __construct(
        private readonly ListSourcesUseCase $listSourcesUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $sources = $this->listSourcesUseCase->execute($user->getId());

        return new JsonResponse(array_map(
            static fn($source) => [
                'id'          => $source->getId(),
                'name'        => $source->getName(),
                'inboundUuid' => $source->getInboundUuid(),
                'createdAt'   => $source->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $sources
        ));
    }
}
