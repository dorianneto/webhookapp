<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Endpoint;

use App\Application\UseCase\Endpoint\ListEndpointsUseCase;
use App\Domain\Exception\SourceNotFoundException;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources/{sourceId}/endpoints', name: 'list_endpoints', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListEndpointsController
{
    public function __construct(
        private readonly ListEndpointsUseCase $listEndpointsUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $sourceId): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'list_endpoints';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $endpoints = $this->listEndpointsUseCase->execute($requestId, $sourceId, $user->getId());
        } catch (SourceNotFoundException $e) {
            $this->logger->info('Source not found', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(['error' => 'Source not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse(array_map(static fn($endpoint) => [
            'id'        => $endpoint->getId(),
            'sourceId'  => $endpoint->getSourceId(),
            'url'       => $endpoint->getUrl(),
            'createdAt' => $endpoint->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $endpoints));
    }
}
