<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\EventEndpointDelivery;

interface EventEndpointDeliveryRepositoryPort
{
    public function save(EventEndpointDelivery $delivery): void;
}
