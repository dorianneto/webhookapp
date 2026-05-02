<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Dashboard;

use App\Application\UseCase\Dashboard\GetDashboardStatsUseCase;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard', name: 'dashboard_stats', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class DashboardController
{
    public function __construct(
        private readonly GetDashboardStatsUseCase $getDashboardStatsUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'dashboard_stats',
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $stats = $this->getDashboardStatsUseCase->execute($requestId, $user->getId());

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => 'dashboard_stats',
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse([
            'totalSources'         => $stats->totalSources,
            'totalEndpoints'       => $stats->totalEndpoints,
            'totalEventsReceived'  => $stats->totalEventsReceived,
            'deliveredEventsCount' => $stats->deliveredEventsCount,
            'pendingEventsCount'   => $stats->pendingEventsCount,
            'failedEventsCount'    => $stats->failedEventsCount,
            'lastEventReceivedAt'  => $stats->lastEventReceivedAt?->format(\DateTimeInterface::ATOM),
            'quotaUsed'            => $stats->quotaUsed,
            'quotaLimit'           => $stats->quotaLimit,
        ]);
    }
}
