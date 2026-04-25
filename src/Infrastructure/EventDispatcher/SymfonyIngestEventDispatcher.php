<?php

declare(strict_types=1);

namespace App\Infrastructure\EventDispatcher;

use App\Application\Event\IngestCompletedEvent;
use App\Application\Port\IngestEventDispatcherPort;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SymfonyIngestEventDispatcher implements IngestEventDispatcherPort
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function dispatch(IngestCompletedEvent $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
