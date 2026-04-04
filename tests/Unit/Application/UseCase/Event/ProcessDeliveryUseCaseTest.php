<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Event;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\HttpDeliveryPort;
use App\Application\Port\TransactionPort;
use App\Application\UseCase\Event\ProcessDeliveryUseCase;
use App\Application\Value\DeliveryResult;
use App\Domain\Endpoint;
use App\Domain\Event;
use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProcessDeliveryUseCaseTest extends TestCase
{
    private EventRepositoryPort&MockObject $eventRepository;
    private EventEndpointDeliveryRepositoryPort&MockObject $deliveryRepository;
    private EndpointRepositoryPort&MockObject $endpointRepository;
    private DeliveryAttemptRepositoryPort&MockObject $attemptRepository;
    private HttpDeliveryPort&MockObject $httpDelivery;
    private DeliveryQueuePort&MockObject $queue;
    private TransactionPort&MockObject $transaction;
    private ProcessDeliveryUseCase $useCase;

    protected function setUp(): void
    {
        $this->eventRepository    = $this->createMock(EventRepositoryPort::class);
        $this->deliveryRepository = $this->createMock(EventEndpointDeliveryRepositoryPort::class);
        $this->endpointRepository = $this->createMock(EndpointRepositoryPort::class);
        $this->attemptRepository  = $this->createMock(DeliveryAttemptRepositoryPort::class);
        $this->httpDelivery       = $this->createMock(HttpDeliveryPort::class);
        $this->queue              = $this->createMock(DeliveryQueuePort::class);
        $this->transaction        = $this->createMock(TransactionPort::class);

        // Transaction stub executes the callable immediately
        $this->transaction
            ->method('execute')
            ->willReturnCallback(static fn(callable $op) => $op());

        $this->useCase = new ProcessDeliveryUseCase(
            $this->eventRepository,
            $this->deliveryRepository,
            $this->endpointRepository,
            $this->attemptRepository,
            $this->httpDelivery,
            $this->queue,
            $this->transaction,
        );
    }

    private function makeEndpoint(): Endpoint
    {
        return new Endpoint('endpoint-1', 'source-1', 'https://example.com/hook', new \DateTimeImmutable());
    }

    private function makeEvent(): Event
    {
        return new Event('event-1', 'source-1', 'POST', ['Content-Type' => ['application/json']], '{"test":1}', EventStatus::Pending, new \DateTimeImmutable());
    }

    private function makeDelivery(EventStatus $status = EventStatus::Pending): EventEndpointDelivery
    {
        return new EventEndpointDelivery('delivery-1', 'event-1', 'endpoint-1', $status, new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    private function successResult(): DeliveryResult
    {
        return new DeliveryResult(200, 'OK', 120, true);
    }

    private function failureResult(): DeliveryResult
    {
        return new DeliveryResult(500, 'Error', 80, false);
    }

    private function setupCommonStubs(DeliveryResult $result, EventStatus $deliveryStatus = EventStatus::Pending): void
    {
        $this->endpointRepository->method('findById')->willReturn($this->makeEndpoint());
        $this->eventRepository->method('findById')->willReturn($this->makeEvent());
        $this->deliveryRepository->method('findByEventAndEndpoint')->willReturn($this->makeDelivery($deliveryStatus));
        $this->httpDelivery->method('deliver')->willReturn($result);
        $this->deliveryRepository->method('findAllByEvent')->willReturn([$this->makeDelivery($deliveryStatus)]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSuccessOnFirstAttempt(): void
    {
        $this->setupCommonStubs($this->successResult(), EventStatus::Delivered);

        $this->deliveryRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('delivery-1', EventStatus::Delivered);

        $this->eventRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('event-1', EventStatus::Delivered);

        $this->queue->expects($this->never())->method('enqueue');

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailureOnAttempt1ReEnqueuesWithDelay(): void
    {
        $this->setupCommonStubs($this->failureResult());

        $this->deliveryRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('delivery-1', EventStatus::Pending);

        $this->queue
            ->expects($this->once())
            ->method('enqueue')
            ->with(
                $this->callback(fn(DeliverEventMessage $m) => $m->attemptNumber === 2),
                30_000,
            );

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailureOnAttempt2ReEnqueuesWithLongerDelay(): void
    {
        $this->setupCommonStubs($this->failureResult());

        $this->queue
            ->expects($this->once())
            ->method('enqueue')
            ->with(
                $this->callback(fn(DeliverEventMessage $m) => $m->attemptNumber === 3),
                300_000,
            );

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 2));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailureOnAttempt5MarksDeliveryFailed(): void
    {
        $this->setupCommonStubs($this->failureResult(), EventStatus::Failed);

        $this->deliveryRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('delivery-1', EventStatus::Failed);

        $this->eventRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('event-1', EventStatus::Failed);

        $this->queue->expects($this->never())->method('enqueue');

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 5));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testMixedDeliveriesEventStatusPending(): void
    {
        $this->endpointRepository->method('findById')->willReturn($this->makeEndpoint());
        $this->eventRepository->method('findById')->willReturn($this->makeEvent());
        $this->deliveryRepository->method('findByEventAndEndpoint')->willReturn($this->makeDelivery());
        $this->httpDelivery->method('deliver')->willReturn($this->successResult());

        $allDeliveries = [
            new EventEndpointDelivery('d1', 'event-1', 'endpoint-1', EventStatus::Delivered, new \DateTimeImmutable(), new \DateTimeImmutable()),
            new EventEndpointDelivery('d2', 'event-1', 'endpoint-2', EventStatus::Pending, new \DateTimeImmutable(), new \DateTimeImmutable()),
        ];
        $this->deliveryRepository->method('findAllByEvent')->willReturn($allDeliveries);

        $this->eventRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('event-1', EventStatus::Pending);

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAllDeliveredEventStatusDelivered(): void
    {
        $this->endpointRepository->method('findById')->willReturn($this->makeEndpoint());
        $this->eventRepository->method('findById')->willReturn($this->makeEvent());
        $this->deliveryRepository->method('findByEventAndEndpoint')->willReturn($this->makeDelivery());
        $this->httpDelivery->method('deliver')->willReturn($this->successResult());

        $allDeliveries = [
            new EventEndpointDelivery('d1', 'event-1', 'endpoint-1', EventStatus::Delivered, new \DateTimeImmutable(), new \DateTimeImmutable()),
            new EventEndpointDelivery('d2', 'event-1', 'endpoint-2', EventStatus::Delivered, new \DateTimeImmutable(), new \DateTimeImmutable()),
        ];
        $this->deliveryRepository->method('findAllByEvent')->willReturn($allDeliveries);

        $this->eventRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('event-1', EventStatus::Delivered);

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnyFailedEventStatusFailed(): void
    {
        $this->endpointRepository->method('findById')->willReturn($this->makeEndpoint());
        $this->eventRepository->method('findById')->willReturn($this->makeEvent());
        $this->deliveryRepository->method('findByEventAndEndpoint')->willReturn($this->makeDelivery());
        $this->httpDelivery->method('deliver')->willReturn($this->successResult());

        $allDeliveries = [
            new EventEndpointDelivery('d1', 'event-1', 'endpoint-1', EventStatus::Delivered, new \DateTimeImmutable(), new \DateTimeImmutable()),
            new EventEndpointDelivery('d2', 'event-1', 'endpoint-2', EventStatus::Failed, new \DateTimeImmutable(), new \DateTimeImmutable()),
        ];
        $this->deliveryRepository->method('findAllByEvent')->willReturn($allDeliveries);

        $this->eventRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with('event-1', EventStatus::Failed);

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1));
    }
}
