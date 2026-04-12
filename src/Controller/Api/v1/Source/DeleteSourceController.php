<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\DeleteSourceUseCase;
use App\Domain\Exception\SourceNotFoundException;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources/{id}', name: 'delete_source', methods: ['DELETE'])]
#[WithMonologChannel('hookyard')]
final class DeleteSourceController
{
    public function __construct(
        private readonly DeleteSourceUseCase $deleteSourceUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'delete_source';

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
            $this->deleteSourceUseCase->execute($requestId, $id, $user->getId());
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
            'http_status' => Response::HTTP_NO_CONTENT,
        ]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
