<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Endpoint;

use App\Application\UseCase\Endpoint\AddEndpointUseCase;
use App\Domain\Exception\SourceNotFoundException;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sources/{sourceId}/endpoints', name: 'create_endpoint', methods: ['POST'])]
final class CreateEndpointController
{
    public function __construct(
        private readonly AddEndpointUseCase $addEndpointUseCase,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {}

    public function __invoke(Request $request, string $sourceId): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $url = $data['url'] ?? '';

        $violations = $this->validator->validate($url, [
            new Assert\NotBlank(message: 'URL is required.'),
            new Assert\Url(message: 'Invalid URL format.'),
        ]);

        if (\count($violations) > 0) {
            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $id       = Uuid::v7()->toRfc4122();
            $endpoint = $this->addEndpointUseCase->execute($request->attributes->get('request_id'), $id, $sourceId, $url, $user->getId());
        } catch (SourceNotFoundException) {
            return new JsonResponse(['error' => 'Source not found.'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'id'        => $endpoint->getId(),
            'sourceId'  => $endpoint->getSourceId(),
            'url'       => $endpoint->getUrl(),
            'createdAt' => $endpoint->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
