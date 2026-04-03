<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\CreateSourceUseCase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sources', name: 'create_source', methods: ['POST'])]
final class CreateSourceController
{
    public function __construct(
        private readonly CreateSourceUseCase $createSourceUseCase,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = $data['name'] ?? '';

        $violations = $this->validator->validate($name, [
            new Assert\NotBlank(message: 'Name is required.'),
        ]);

        if (\count($violations) > 0) {
            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $id     = Uuid::v7()->toRfc4122();
        $source = $this->createSourceUseCase->execute($id, $user->getId(), $name);

        $inboundUrl = $request->getSchemeAndHttpHost() . '/in/' . $source->getInboundUuid();

        return new JsonResponse([
            'id'          => $source->getId(),
            'name'        => $source->getName(),
            'inboundUuid' => $source->getInboundUuid(),
            'inboundUrl'  => $inboundUrl,
            'createdAt'   => $source->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
