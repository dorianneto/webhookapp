<?php

declare(strict_types=1);

namespace App\Domain;

enum EventStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
