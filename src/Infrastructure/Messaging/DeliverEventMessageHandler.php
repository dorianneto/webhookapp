<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Message\DeliverEventMessage;
use App\Application\UseCase\Event\ProcessDeliveryUseCase;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
#[WithMonologChannel('hookyard')]
final class DeliverEventMessageHandler
{
    public function __construct(
        private readonly ProcessDeliveryUseCase $useCase,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(DeliverEventMessage $message): void
    {
        $this->logger->info('Delivery message received from queue', [
            'request_id'     => $message->requestId,
            'event_id'       => $message->eventId,
            'endpoint_id'    => $message->endpointId,
            'attempt_number' => $message->attemptNumber,
        ]);

        try {
            $this->useCase->execute($message);
        } catch (\Throwable $e) {
            $this->logger->error('Delivery unhandled exception from use case', [
                'request_id'      => $message->requestId,
                'event_id'        => $message->eventId,
                'endpoint_id'     => $message->endpointId,
                'exception_class' => $e::class,
                'message'         => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        $this->logger->info('Delivery processing complete', [
            'request_id'  => $message->requestId,
            'event_id'    => $message->eventId,
            'endpoint_id' => $message->endpointId,
        ]);
    }
}
