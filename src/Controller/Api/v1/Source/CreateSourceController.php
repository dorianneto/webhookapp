<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Source;

use App\Application\UseCase\Source\CreateSourceUseCase;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sources', name: 'create_source', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class CreateSourceController
{
    public function __construct(
        private readonly CreateSourceUseCase $createSourceUseCase,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'create_source';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
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

        $id     = Uuid::v7()->toRfc4122();
        $source = $this->createSourceUseCase->execute($requestId, $id, $user->getId(), $name);

        $inboundUrl = $request->getSchemeAndHttpHost() . '/in/' . $source->getInboundUuid();

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_CREATED,
        ]);

        return new JsonResponse([
            'id'          => $source->getId(),
            'name'        => $source->getName(),
            'inboundUuid' => $source->getInboundUuid(),
            'inboundUrl'  => $inboundUrl,
            'createdAt'   => $source->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
