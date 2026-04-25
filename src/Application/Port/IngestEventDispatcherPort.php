<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Event\IngestCompletedEvent;

interface IngestEventDispatcherPort
{
    public function dispatch(IngestCompletedEvent $event): void;
}
