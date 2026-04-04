<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Event;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Event\IngestEventUseCase;
use App\Domain\Endpoint;
use App\Domain\EventStatus;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IngestEventUseCaseTest extends TestCase
{
    private SourceRepositoryPort&MockObject $sourceRepository;
    private EventRepositoryPort&MockObject $eventRepository;
    private EndpointRepositoryPort&MockObject $endpointRepository;
    private EventEndpointDeliveryRepositoryPort&MockObject $deliveryRepository;
    private DeliveryQueuePort&MockObject $queue;
    private IngestEventUseCase $useCase;

    protected function setUp(): void
    {
        $this->sourceRepository   = $this->createMock(SourceRepositoryPort::class);
        $this->eventRepository    = $this->createMock(EventRepositoryPort::class);
        $this->endpointRepository = $this->createMock(EndpointRepositoryPort::class);
        $this->deliveryRepository = $this->createMock(EventEndpointDeliveryRepositoryPort::class);
        $this->queue              = $this->createMock(DeliveryQueuePort::class);

        $this->useCase = new IngestEventUseCase(
            $this->sourceRepository,
            $this->eventRepository,
            $this->endpointRepository,
            $this->deliveryRepository,
            $this->queue,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSourceNotFoundThrowsException(): void
    {
        $this->sourceRepository
            ->method('findByInboundUuid')
            ->willReturn(null);

        $this->eventRepository->expects($this->never())->method('save');
        $this->queue->expects($this->never())->method('enqueue');

        $this->expectException(SourceNotFoundException::class);

        $this->useCase->execute('event-id', 'unknown-uuid', 'POST', [], '{}');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNoActiveEndpointsStillSavesEvent(): void
    {
        $source = new Source('source-id', 'user-id', 'My Source', 'inbound-uuid', new \DateTimeImmutable());

        $this->sourceRepository->method('findByInboundUuid')->willReturn($source);
        $this->endpointRepository->method('findActiveBySource')->willReturn([]);

        $this->eventRepository->expects($this->once())->method('save');
        $this->deliveryRepository->expects($this->never())->method('save');
        $this->queue->expects($this->never())->method('enqueue');

        $this->useCase->execute('event-id', 'inbound-uuid', 'POST', [], '{}');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testMultipleEndpointsEnqueuesAll(): void
    {
        $source = new Source('source-id', 'user-id', 'My Source', 'inbound-uuid', new \DateTimeImmutable());

        $endpoints = [
            new Endpoint('endpoint-1', 'source-id', 'https://example.com/a', new \DateTimeImmutable()),
            new Endpoint('endpoint-2', 'source-id', 'https://example.com/b', new \DateTimeImmutable()),
        ];

        $this->sourceRepository->method('findByInboundUuid')->willReturn($source);
        $this->endpointRepository->method('findActiveBySource')->willReturn($endpoints);

        $this->eventRepository->expects($this->once())->method('save');
        $this->deliveryRepository->expects($this->exactly(2))->method('save');

        $this->queue
            ->expects($this->exactly(2))
            ->method('enqueue')
            ->with($this->callback(function (DeliverEventMessage $msg): bool {
                return $msg->eventId === 'event-id' && $msg->attemptNumber === 1;
            }));

        $this->useCase->execute('event-id', 'inbound-uuid', 'POST', ['Content-Type' => 'application/json'], '{"test":1}');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testEventIsSavedWithCorrectData(): void
    {
        $source = new Source('source-id', 'user-id', 'My Source', 'inbound-uuid', new \DateTimeImmutable());

        $this->sourceRepository->method('findByInboundUuid')->willReturn($source);
        $this->endpointRepository->method('findActiveBySource')->willReturn([]);

        $this->eventRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (\App\Domain\Event $event): bool {
                return $event->getId() === 'event-id'
                    && $event->getSourceId() === 'source-id'
                    && $event->getMethod() === 'POST'
                    && $event->getStatus() === EventStatus::Pending;
            }));

        $this->useCase->execute('event-id', 'inbound-uuid', 'POST', [], '{}');
    }
}
