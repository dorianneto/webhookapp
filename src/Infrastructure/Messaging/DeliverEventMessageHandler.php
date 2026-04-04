<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Message\DeliverEventMessage;
use App\Application\UseCase\Event\ProcessDeliveryUseCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeliverEventMessageHandler
{
    public function __construct(
        private readonly ProcessDeliveryUseCase $useCase,
    ) {}

    public function __invoke(DeliverEventMessage $message): void
    {
        $this->useCase->execute($message);
    }
}
