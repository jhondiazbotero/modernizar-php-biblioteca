<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\CancelReservation;
use Library\Domain\CopySnapshot;
use Library\Domain\ReservationQueue;
use Library\Domain\ReservationSnapshot;

final class CancelReservationTest extends UseCaseTestCase
{
    private function useCase(): CancelReservation
    {
        return new CancelReservation($this->transactions, $this->reservations, $this->copies, new ReservationQueue());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addMember(2, 'Zoe Primera');
        $this->store->addMember(3, 'Álvaro Segundo');
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::RESERVED);
    }

    public function test_cancelling_a_pending_reservation_touches_nothing_else(): void
    {
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::PENDING);

        $result = $this->useCase()->handle(7);

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->hadCopy());
        self::assertNull($result->getAssignedMemberName());
        self::assertSame(ReservationSnapshot::CANCELLED, $this->store->reservations[7]['status']);
        self::assertSame(CopySnapshot::RESERVED, $this->store->copyStatus(10));
        self::assertSame(['reservations.cancel'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_cancelling_an_assigned_reservation_without_queue_frees_the_copy(): void
    {
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::ASSIGNED, 10);

        $result = $this->useCase()->handle(7);

        self::assertTrue($result->hadCopy());
        self::assertNull($result->getAssignedMemberName());
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
        self::assertSame(['reservations.cancel', 'copies.updateStatus'], $this->store->writes);
    }

    public function test_the_copy_goes_to_the_reservation_that_arrived_first(): void
    {
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::ASSIGNED, 10);
        $this->store->addReservation(8, 5, 2, ReservationSnapshot::PENDING, null, '2026-11-01 08:00:00');
        $this->store->addReservation(9, 5, 3, ReservationSnapshot::PENDING, null, '2026-11-09 08:00:00');

        $result = $this->useCase()->handle(7);

        self::assertSame('Zoe Primera', $result->getAssignedMemberName());
        self::assertSame(ReservationSnapshot::ASSIGNED, $this->store->reservations[8]['status']);
        self::assertSame(10, $this->store->reservations[8]['copyId']);
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[9]['status']);
        self::assertSame(CopySnapshot::RESERVED, $this->store->copyStatus(10));
        self::assertSame(['reservations.cancel', 'reservations.assign', 'copies.updateStatus'], $this->store->writes);
    }

    public function test_the_cancelled_reservation_is_not_offered_the_copy_again(): void
    {
        $this->store->addReservation(7, 5, 3, ReservationSnapshot::ASSIGNED, 10);

        $result = $this->useCase()->handle(7);

        self::assertNull($result->getAssignedMemberName());
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
    }

    public function test_an_unknown_reservation_is_reported(): void
    {
        $result = $this->useCase()->handle(99);

        self::assertSame('reservation_not_found', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    /** @dataProvider closedStatuses */
    public function test_a_fulfilled_or_cancelled_reservation_cannot_be_cancelled(string $status): void
    {
        $this->store->addReservation(7, 5, 1, $status, 10);

        $result = $this->useCase()->handle(7);

        self::assertSame('not_cancellable', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertSame($status, $this->store->reservations[7]['status']);
    }

    public function closedStatuses(): array
    {
        return [[ReservationSnapshot::FULFILLED], [ReservationSnapshot::CANCELLED]];
    }

    /** @dataProvider failurePoints */
    public function test_a_failure_in_the_middle_leaves_everything_as_it_was(string $operation): void
    {
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::ASSIGNED, 10);
        $this->store->addReservation(8, 5, 2, ReservationSnapshot::PENDING);
        $this->store->failOn($operation);

        try {
            $this->useCase()->handle(7);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame(ReservationSnapshot::ASSIGNED, $this->store->reservations[7]['status']);
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[8]['status']);
        self::assertSame(CopySnapshot::RESERVED, $this->store->copyStatus(10));
        self::assertSame([], $this->store->writes);
        self::assertSame(1, $this->transactions->rollbacks);
    }

    public function failurePoints(): array
    {
        return [['reservations.assign'], ['copies.updateStatus']];
    }
}
