<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\PlaceReservation;
use Library\Domain\CopySnapshot;
use Library\Domain\ReservationSnapshot;

final class PlaceReservationTest extends UseCaseTestCase
{
    private function useCase(): PlaceReservation
    {
        return new PlaceReservation($this->transactions, $this->members, $this->books, $this->copies, $this->reservations, $this->clock);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
    }

    public function test_a_book_without_available_copies_can_be_reserved(): void
    {
        $result = $this->useCase()->handle(1, 5);

        self::assertTrue($result->isSuccess());
        self::assertSame('Ana Torres', $result->getMemberName());
        self::assertSame(5, $result->getBookId());
        self::assertSame(1, $result->getReservationId());
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[1]['status']);
        self::assertSame('2026-11-27 12:00:00', $this->store->reservations[1]['reservedAt']->format('Y-m-d H:i:s'));
        self::assertSame(['reservations.add'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_a_reserved_or_withdrawn_copy_does_not_count_as_available(): void
    {
        $this->store->addCopy(11, 5, 'Rayuela', CopySnapshot::RESERVED);
        $this->store->addCopy(12, 5, 'Rayuela', CopySnapshot::WITHDRAWN);

        self::assertTrue($this->useCase()->handle(1, 5)->isSuccess());
    }

    public function test_a_book_with_an_available_copy_cannot_be_reserved(): void
    {
        $this->store->addCopy(11, 5, 'Rayuela', CopySnapshot::AVAILABLE);

        $result = $this->useCase()->handle(1, 5);

        self::assertSame('copies_available', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_the_same_member_cannot_have_two_pending_reservations_of_a_book(): void
    {
        $this->store->addReservation(1, 5, 1, ReservationSnapshot::PENDING);

        $result = $this->useCase()->handle(1, 5);

        self::assertSame('already_pending', $result->getErrorCode());
        self::assertCount(1, $this->store->reservations);
    }

    public function test_a_cancelled_or_assigned_reservation_does_not_block_a_new_one(): void
    {
        $this->store->addReservation(1, 5, 1, ReservationSnapshot::CANCELLED);
        $this->store->addReservation(2, 5, 1, ReservationSnapshot::ASSIGNED, 10);

        self::assertTrue($this->useCase()->handle(1, 5)->isSuccess());
    }

    public function test_another_member_can_reserve_the_same_book(): void
    {
        $this->store->addMember(2, 'Beatriz');
        $this->store->addReservation(1, 5, 2, ReservationSnapshot::PENDING);

        self::assertTrue($this->useCase()->handle(1, 5)->isSuccess());
        self::assertCount(2, $this->store->reservations);
    }

    public function test_unknown_member_and_unknown_book_are_reported_in_that_order(): void
    {
        self::assertSame('member_not_found', $this->useCase()->handle(99, 5)->getErrorCode());
        self::assertSame('book_not_found', $this->useCase()->handle(1, 99)->getErrorCode());
        self::assertSame('member_not_found', $this->useCase()->handle(99, 99)->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_an_inactive_member_can_still_reserve(): void
    {
        $this->store->addMember(2, 'Inactivo', false);

        self::assertTrue($this->useCase()->handle(2, 5)->isSuccess());
    }

    public function test_a_failure_leaves_no_reservation(): void
    {
        $this->store->failOn('reservations.add');

        try {
            $this->useCase()->handle(1, 5);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame([], $this->store->reservations);
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
