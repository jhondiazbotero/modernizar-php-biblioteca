<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\LendCopy;
use Library\Domain\CopySnapshot;
use Library\Domain\ReservationSnapshot;

final class LendCopyTest extends UseCaseTestCase
{
    private function useCase(): LendCopy
    {
        return new LendCopy($this->transactions, $this->members, $this->copies, $this->loans, $this->reservations, $this->clock);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela');
    }

    public function test_a_loan_is_registered_for_14_days_and_the_copy_goes_on_loan(): void
    {
        $result = $this->useCase()->handle(1, 10);

        self::assertTrue($result->isSuccess());
        self::assertNull($result->getErrorCode());
        self::assertSame('Rayuela', $result->getBookTitle());
        self::assertSame('Ana Torres', $result->getMemberName());
        self::assertSame('2026-12-11', $result->getDueDate()->format('Y-m-d'));
        self::assertSame(1, $result->getLoanId());
        self::assertSame('2026-11-27', $this->store->loans[1]['loanedOn']->format('Y-m-d'));
        self::assertSame('2026-12-11', $this->store->loans[1]['dueDate']->format('Y-m-d'));
        self::assertSame(CopySnapshot::ON_LOAN, $this->store->copyStatus(10));
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_a_copy_reserved_for_this_member_is_lent_and_the_reservation_is_fulfilled(): void
    {
        $this->store->addCopy(11, 5, 'Rayuela', CopySnapshot::RESERVED);
        $this->store->addReservation(3, 5, 1, ReservationSnapshot::ASSIGNED, 11);

        $result = $this->useCase()->handle(1, 11);

        self::assertTrue($result->isSuccess());
        self::assertSame(ReservationSnapshot::FULFILLED, $this->store->reservations[3]['status']);
        self::assertSame(CopySnapshot::ON_LOAN, $this->store->copyStatus(11));
        self::assertSame(['loans.add', 'copies.updateStatus', 'reservations.markFulfilled'], $this->store->writes);
    }

    public function test_the_third_loan_is_allowed(): void
    {
        $this->store->addCopy(20, 6, 'Otro libro', CopySnapshot::ON_LOAN);
        $this->store->addCopy(21, 7, 'Otro más', CopySnapshot::ON_LOAN);
        $this->store->addLoan(1, 20, 1, '2026-12-01');
        $this->store->addLoan(2, 21, 1, '2026-12-01');

        self::assertTrue($this->useCase()->handle(1, 10)->isSuccess());
    }

    public function test_a_paid_fine_does_not_block(): void
    {
        $this->store->addCopy(20, 6, 'Otro libro');
        $this->store->addLoan(1, 20, 1, '2026-10-01', '2026-10-20', 1500, true);

        self::assertTrue($this->useCase()->handle(1, 10)->isSuccess());
    }

    /** @dataProvider failures */
    public function test_each_error_is_reported_and_nothing_is_written(string $code, callable $arrange, int $memberId, int $copyId): void
    {
        $arrange($this->store);
        $loansBefore = $this->store->loans;

        $result = $this->useCase()->handle($memberId, $copyId);

        self::assertFalse($result->isSuccess());
        self::assertSame($code, $result->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertEquals($loansBefore, $this->store->loans);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function failures(): array
    {
        return [
            'unknown member' => ['member_not_found', function ($s) {
            }, 99, 10],
            'unknown copy' => ['copy_not_found', function ($s) {
            }, 1, 99],
            'unknown member and copy: the member is checked first' => ['member_not_found', function ($s) {
            }, 98, 99],
            'inactive member' => ['member_inactive', function ($s) {
                $s->addMember(2, 'Inactivo', false);
            }, 2, 10],
            'three active loans' => ['max_active_loans', function ($s) {
                foreach ([20, 21, 22] as $i => $copy) {
                    $s->addCopy($copy, 6 + $i, 'Libro ' . $copy, CopySnapshot::ON_LOAN);
                    $s->addLoan($i + 1, $copy, 1, '2026-12-01');
                }
            }, 1, 10],
            'unpaid fine' => ['unpaid_fines', function ($s) {
                $s->addCopy(20, 6, 'Otro libro');
                $s->addLoan(1, 20, 1, '2026-10-01', '2026-10-20', 1500, false);
            }, 1, 10],
            'copy on loan' => ['copy_on_loan', function ($s) {
                $s->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
            }, 1, 10],
            'copy withdrawn' => ['copy_withdrawn', function ($s) {
                $s->addCopy(10, 5, 'Rayuela', CopySnapshot::WITHDRAWN);
            }, 1, 10],
            'copy reserved for another member' => ['copy_reserved_for_other', function ($s) {
                $s->addMember(2, 'Otro Socio');
                $s->addCopy(10, 5, 'Rayuela', CopySnapshot::RESERVED);
                $s->addReservation(3, 5, 2, ReservationSnapshot::ASSIGNED, 10);
            }, 1, 10],
            'copy reserved but without assigned reservation' => ['copy_reserved_for_other', function ($s) {
                $s->addCopy(10, 5, 'Rayuela', CopySnapshot::RESERVED);
            }, 1, 10],
        ];
    }

    public function test_the_validation_order_is_inactive_then_limit_then_fines_then_copy(): void
    {
        $this->store->addMember(2, 'Inactivo', false);
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::WITHDRAWN);
        foreach ([20, 21, 22] as $i => $copy) {
            $this->store->addCopy($copy, 6 + $i, 'Libro ' . $copy, CopySnapshot::ON_LOAN);
            $this->store->addLoan($i + 1, $copy, 2, '2026-10-01', null, 500);
        }

        self::assertSame('member_inactive', $this->useCase()->handle(2, 10)->getErrorCode());

        $this->store->addMember(2, 'Activo', true);
        self::assertSame('max_active_loans', $this->useCase()->handle(2, 10)->getErrorCode());

        $this->store->loans[3]['returnedOn'] = new \DateTimeImmutable('2026-10-10');
        self::assertSame('unpaid_fines', $this->useCase()->handle(2, 10)->getErrorCode());
    }

    public function test_a_failure_in_the_middle_leaves_nothing_written(): void
    {
        $this->store->failOn('copies.updateStatus');

        try {
            $this->useCase()->handle(1, 10);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            self::assertSame('Forced failure in copies.updateStatus', $e->getMessage());
        }

        self::assertSame([], $this->store->loans);
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
        self::assertSame([], $this->store->writes);
        self::assertSame(1, $this->transactions->rollbacks);
    }

    public function test_a_failure_after_the_reservation_step_discards_the_whole_lending(): void
    {
        $this->store->addCopy(11, 5, 'Rayuela', CopySnapshot::RESERVED);
        $this->store->addReservation(3, 5, 1, ReservationSnapshot::ASSIGNED, 11);
        $this->store->failOn('reservations.markFulfilled');

        try {
            $this->useCase()->handle(1, 11);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame([], $this->store->loans);
        self::assertSame(CopySnapshot::RESERVED, $this->store->copyStatus(11));
        self::assertSame(ReservationSnapshot::ASSIGNED, $this->store->reservations[3]['status']);
    }
}
