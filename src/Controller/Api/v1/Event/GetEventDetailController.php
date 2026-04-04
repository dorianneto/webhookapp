<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Event;

use App\Application\UseCase\Event\GetEventDetailUseCase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events/{id}', name: 'get_event_detail', methods: ['GET'])]
final class GetEventDetailController
{
    public function __construct(
        private readonly GetEventDetailUseCase $getEventDetailUseCase,
        private readonly Security $security,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $detail = $this->getEventDetailUseCase->execute($id);

        if ($detail === null) {
            return new JsonResponse(['error' => 'Event not found.'], Response::HTTP_NOT_FOUND);
        }

        $event = $detail->event;

        return new JsonResponse([
            'id'         => $event->getId(),
            'method'     => $event->getMethod(),
            'headers'    => $event->getHeaders(),
            'body'       => $event->getBody(),
            'status'     => $event->getStatus()->value,
            'receivedAt' => $event->getReceivedAt()->format(\DateTimeInterface::ATOM),
            'deliveries' => array_map(static fn($d) => [
                'endpointId'  => $d->delivery->getEndpointId(),
                'endpointUrl' => $d->endpoint->getUrl(),
                'status'      => $d->delivery->getStatus()->value,
                'attempts'    => array_map(static fn($a) => [
                    'attemptNumber' => $a->getAttemptNumber(),
                    'statusCode'    => $a->getStatusCode(),
                    'responseBody'  => $a->getResponseBody(),
                    'durationMs'    => $a->getDurationMs(),
                    'attemptedAt'   => $a->getAttemptedAt()->format(\DateTimeInterface::ATOM),
                ], $d->attempts),
            ], $detail->deliveries),
        ]);
    }
}
