<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\ListSourcesUseCase;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources', name: 'list_sources', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListSourcesController
{
    public function __construct(
        private readonly ListSourcesUseCase $listSourcesUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'list_sources',
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $sources = $this->listSourcesUseCase->execute($requestId, $user->getId());
        $baseUrl = $request->getSchemeAndHttpHost();

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => 'list_sources',
            'http_status' => Response::HTTP_OK,
        ]);

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
