<?php

declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/me', name: 'me', methods: ['GET'])]
final class MeController
{
    public function __construct(private readonly Security $security) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id'    => $user->getId(),
            'email' => $user->getUserIdentifier(),
        ]);
    }
}
