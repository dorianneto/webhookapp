<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Event;

use App\Application\UseCase\Event\ListEventsUseCase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources/{sourceId}/events', name: 'list_events', methods: ['GET'])]
final class ListEventsController
{
    public function __construct(
        private readonly ListEventsUseCase $listEventsUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(string $sourceId): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $events = $this->listEventsUseCase->execute($sourceId);

        return new JsonResponse(array_map(static fn($event) => [
            'id'         => $event->getId(),
            'method'     => $event->getMethod(),
            'status'     => $event->getStatus()->value,
            'receivedAt' => $event->getReceivedAt()->format(\DateTimeInterface::ATOM),
        ], $events));
    }
}
