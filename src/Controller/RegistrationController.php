<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Entity\User as UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/register', name: 'register', methods: ['POST'])]
final class RegistrationController
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(message: 'Email is required.'),
            new Assert\Email(message: 'Invalid email format.'),
        ]);

        if (\count($violations) > 0) {
            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $violations = $this->validator->validate($password, [
            new Assert\NotBlank(message: 'Password is required.'),
        ]);

        if (\count($violations) > 0) {
            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $id       = Uuid::v7()->toRfc4122();
        $tempUser = new UserEntity($id, $email, '', new \DateTimeImmutable());
        $passwordHash = $this->passwordHasher->hashPassword($tempUser, $password);

        try {
            $this->registerUserUseCase->execute($id, $email, $passwordHash);
        } catch (EmailAlreadyTakenException) {
            return new JsonResponse(
                ['error' => 'Email is already registered.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new JsonResponse(null, Response::HTTP_CREATED);
    }
}
