<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Message\DeliverEventMessage;

interface DeliveryQueuePort
{
    public function enqueue(DeliverEventMessage $message): void;
}
