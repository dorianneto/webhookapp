<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class, priority: 65)]
final class LogoutSuccessListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new JsonResponse([], Response::HTTP_OK));
    }
}
