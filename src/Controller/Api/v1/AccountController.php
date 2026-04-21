<?php

declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\Application\UseCase\UpdateAccountUseCase;
use App\Domain\Exception\InvalidPasswordException;
use App\Entity\User as UserEntity;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/account', name: 'account_update', methods: ['PUT'])]
#[WithMonologChannel('hookyard')]
final class AccountController
{
    public function __construct(
        private readonly UpdateAccountUseCase $updateAccountUseCase,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'account_update';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof UserEntity) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name            = $data['name']            ?? '';
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword     = isset($data['newPassword']) && '' !== $data['newPassword']
            ? $data['newPassword']
            : null;

        $violations = $this->validator->validate($name, [
            new Assert\NotBlank(message: 'Name is required.'),
        ]);

        if (\count($violations) > 0) {
            $this->logger->warning('Validation failure', [
                'request_id' => $requestId,
                'route'      => $route,
                'violations' => [(string) $violations[0]->getMessage()],
            ]);

            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (null !== $newPassword && (null === $currentPassword || '' === $currentPassword)) {
            return new JsonResponse(
                ['error' => 'Current password is required when setting a new password.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $newPasswordHash = null !== $newPassword
            ? $this->passwordHasher->hashPassword($user, $newPassword)
            : null;

        $passwordVerifier = function (string $plain, string $hash) use ($user): bool {
            $tempUser = new UserEntity($user->getId(), $user->getUserIdentifier(), $hash, new \DateTimeImmutable());

            return $this->passwordHasher->isPasswordValid($tempUser, $plain);
        };

        try {
            $updated = $this->updateAccountUseCase->execute(
                requestId:            $requestId,
                userId:               $user->getId(),
                name:                 $name,
                newPasswordHash:      $newPasswordHash,
                currentPasswordPlain: $currentPassword,
                passwordVerifier:     $passwordVerifier,
            );
        } catch (InvalidPasswordException) {
            return new JsonResponse(
                ['error' => 'Current password is incorrect.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse([
            'id'    => $updated->getId(),
            'email' => $updated->getEmail(),
            'name'  => $updated->getName(),
        ]);
    }
}
