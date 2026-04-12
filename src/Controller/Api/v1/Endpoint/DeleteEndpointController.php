<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Endpoint;

use App\Application\UseCase\Endpoint\DeleteEndpointUseCase;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/endpoints/{id}', name: 'delete_endpoint', methods: ['DELETE'])]
#[WithMonologChannel('hookyard')]
final class DeleteEndpointController
{
    public function __construct(
        private readonly DeleteEndpointUseCase $deleteEndpointUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'delete_endpoint';

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
            $this->deleteEndpointUseCase->execute($requestId, $id, $user->getId());
        } catch (EndpointNotFoundException | SourceNotFoundException $e) {
            $this->logger->info('Endpoint not found', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(['error' => 'Endpoint not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_NO_CONTENT,
        ]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
