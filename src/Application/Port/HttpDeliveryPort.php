<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Value\DeliveryResult;

interface HttpDeliveryPort
{
    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): DeliveryResult;
}
