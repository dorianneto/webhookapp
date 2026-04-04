<?php

declare(strict_types=1);

namespace App\Application\Value;

use App\Domain\DeliveryAttempt;
use App\Domain\Endpoint;
use App\Domain\EventEndpointDelivery;

final readonly class EndpointDeliveryDetail
{
    /**
     * @param DeliveryAttempt[] $attempts
     */
    public function __construct(
        public EventEndpointDelivery $delivery,
        public Endpoint $endpoint,
        public array $attempts,
    ) {}
}
