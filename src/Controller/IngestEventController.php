<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Event\IngestEventUseCase;
use App\Domain\Exception\SourceNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/in/{uuid}', name: 'ingest_event', methods: ['POST'])]
final class IngestEventController
{
    public function __construct(
        private readonly IngestEventUseCase $ingestEventUseCase,
    ) {}

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $eventId = Uuid::v7()->toRfc4122();
        $body    = $request->getContent();
        $headers = $request->headers->all();
        $method  = $request->getMethod();

        try {
            $this->ingestEventUseCase->execute($request->attributes->get('request_id'), $eventId, $uuid, $method, $headers, $body);
        } catch (SourceNotFoundException) {
            return new JsonResponse(['error' => 'Source not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
