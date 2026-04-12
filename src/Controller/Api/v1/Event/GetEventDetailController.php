<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Event;

use App\Application\UseCase\Event\GetEventDetailUseCase;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events/{id}', name: 'get_event_detail', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class GetEventDetailController
{
    public function __construct(
        private readonly GetEventDetailUseCase $getEventDetailUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'get_event_detail';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $detail = $this->getEventDetailUseCase->execute($requestId, $id, $user->getId());

        if ($detail === null) {
            $this->logger->info('Event not found', [
                'request_id' => $requestId,
                'route'      => $route,
            ]);

            return new JsonResponse(['error' => 'Event not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_OK,
        ]);

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
