<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\ReturnLoan;
use Library\Domain\CopySnapshot;
use Library\Domain\FinePolicy;
use Library\Domain\ReservationQueue;
use Library\Domain\ReservationSnapshot;

final class ReturnLoanTest extends UseCaseTestCase
{
    private function useCase(): ReturnLoan
    {
        return new ReturnLoan(
            $this->transactions,
            $this->loans,
            $this->copies,
            $this->reservations,
            $this->clock,
            new FinePolicy(),
            new ReservationQueue()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
    }

    public function test_on_time_there_is_no_fine_and_the_copy_is_available(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-12-01');

        $result = $this->useCase()->handle(1);

        self::assertTrue($result->isSuccess());
        self::assertSame('Rayuela', $result->getBookTitle());
        self::assertSame('Ana Torres', $result->getMemberName());
        self::assertSame(0, $result->getFine());
        self::assertNull($result->getAssignedMemberName());
        self::assertSame('2026-11-27', $this->store->loans[1]['returnedOn']->format('Y-m-d'));
        self::assertSame(0, $this->store->loans[1]['fine']);
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_late_in_the_same_month_charges_500_per_day(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01');

        $result = $this->useCase()->handle(1);

        self::assertSame(13000, $result->getFine());
        self::assertSame(13000, $this->store->loans[1]['fine']);
        self::assertSame(['loans.markReturned', 'copies.updateStatus'], $this->store->writes);
    }

    public function test_crossing_a_month_charges_the_real_days_late(): void
    {
        // due 31 October, returned 27 November: 27 calendar days late
        $this->store->addLoan(1, 10, 1, '2026-10-31');

        $result = $this->useCase()->handle(1);

        self::assertSame(13500, $result->getFine());
        self::assertSame(13500, $this->store->loans[1]['fine']);
    }

    /** Current behavior, known defect F2: the copy goes to the first name, not to the first to arrive. */
    public function test_f2_the_copy_is_given_to_the_first_name_in_alphabetical_order(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-12-01');
        $this->store->addMember(2, 'Zoe Primera');
        $this->store->addMember(3, 'Álvaro Segundo');
        $this->store->addMember(4, 'Andrés Tercero');
        $this->store->addReservation(7, 5, 2, ReservationSnapshot::PENDING, null, '2026-11-01 08:00:00');
        $this->store->addReservation(8, 5, 4, ReservationSnapshot::PENDING, null, '2026-11-02 08:00:00');
        $this->store->addReservation(9, 5, 3, ReservationSnapshot::PENDING, null, '2026-11-03 08:00:00');

        $result = $this->useCase()->handle(1);

        self::assertSame('Álvaro Segundo', $result->getAssignedMemberName());
        self::assertSame(ReservationSnapshot::ASSIGNED, $this->store->reservations[9]['status']);
        self::assertSame(10, $this->store->reservations[9]['copyId']);
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[7]['status']);
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[8]['status']);
        self::assertSame(CopySnapshot::RESERVED, $this->store->copyStatus(10));
        self::assertSame(['loans.markReturned', 'reservations.assign', 'copies.updateStatus'], $this->store->writes);
    }

    public function test_reservations_for_other_books_are_ignored(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-12-01');
        $this->store->addBook(6);
        $this->store->addMember(2, 'Beatriz');
        $this->store->addReservation(7, 6, 2, ReservationSnapshot::PENDING);

        $result = $this->useCase()->handle(1);

        self::assertNull($result->getAssignedMemberName());
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
    }

    public function test_an_unknown_loan_is_reported(): void
    {
        $result = $this->useCase()->handle(99);

        self::assertFalse($result->isSuccess());
        self::assertSame('loan_not_found', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_loan_already_returned_is_reported(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-10', 4500);

        $result = $this->useCase()->handle(1);

        self::assertSame('already_returned', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertSame(4500, $this->store->loans[1]['fine']);
    }

    /** @dataProvider failurePoints */
    public function test_a_failure_in_the_middle_leaves_the_loan_and_the_copy_untouched(string $operation): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01');
        $this->store->addMember(2, 'Beatriz');
        $this->store->addReservation(7, 5, 2, ReservationSnapshot::PENDING);
        $this->store->failOn($operation);

        try {
            $this->useCase()->handle(1);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertNull($this->store->loans[1]['returnedOn']);
        self::assertSame(0, $this->store->loans[1]['fine']);
        self::assertSame(CopySnapshot::ON_LOAN, $this->store->copyStatus(10));
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[7]['status']);
        self::assertNull($this->store->reservations[7]['copyId']);
        self::assertSame([], $this->store->writes);
        self::assertSame(1, $this->transactions->rollbacks);
    }

    public function failurePoints(): array
    {
        return [['reservations.assign'], ['copies.updateStatus']];
    }
}
