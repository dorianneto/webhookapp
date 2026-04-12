<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\ListSourcesUseCase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources', name: 'list_sources', methods: ['GET'])]
final class ListSourcesController
{
    public function __construct(
        private readonly ListSourcesUseCase $listSourcesUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $sources = $this->listSourcesUseCase->execute($request->attributes->get('request_id'), $user->getId());
        $baseUrl = $request->getSchemeAndHttpHost();

        return new JsonResponse(array_map(
            static fn($source) => [
                'id'          => $source->getId(),
                'name'        => $source->getName(),
                'inboundUuid' => $source->getInboundUuid(),
                'inboundUrl'  => $baseUrl . '/in/' . $source->getInboundUuid(),
                'createdAt'   => $source->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $sources
        ));
    }
}
