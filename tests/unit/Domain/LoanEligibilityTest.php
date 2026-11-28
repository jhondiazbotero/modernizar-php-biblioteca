<?php

declare(strict_types=1);

namespace Library\Tests\Domain;

use Library\Domain\CopySnapshot;
use Library\Domain\LoanEligibility;
use Library\Domain\MemberSnapshot;
use PHPUnit\Framework\TestCase;

final class LoanEligibilityTest extends TestCase
{
    private function member(bool $active = true): MemberSnapshot
    {
        return new MemberSnapshot(1, 'Ana Torres', $active);
    }

    private function copy(string $status = CopySnapshot::AVAILABLE): CopySnapshot
    {
        return new CopySnapshot(5, 2, 'Cien años de soledad', $status);
    }

    private function check(
        ?MemberSnapshot $member = null,
        int $activeLoans = 0,
        int $unpaidFines = 0,
        ?CopySnapshot $copy = null,
        bool $reservedForThisMember = false
    ): ?string {
        return (new LoanEligibility())->check(
            $member ?? $this->member(),
            $activeLoans,
            $unpaidFines,
            $copy ?? $this->copy(),
            $reservedForThisMember
        );
    }

    public function test_an_active_member_with_an_available_copy_can_borrow(): void
    {
        self::assertNull($this->check());
    }

    public function test_an_inactive_member_cannot_borrow(): void
    {
        self::assertSame('member_inactive', $this->check($this->member(false)));
    }

    public function test_three_active_loans_is_the_limit(): void
    {
        self::assertNull($this->check(null, 2));
        self::assertSame('max_active_loans', $this->check(null, 3));
        self::assertSame('max_active_loans', $this->check(null, 4));
    }

    public function test_any_unpaid_fine_blocks_the_loan(): void
    {
        self::assertSame('unpaid_fines', $this->check(null, 0, 1));
        self::assertSame('unpaid_fines', $this->check(null, 0, 5));
    }

    public function test_a_copy_on_loan_is_refused(): void
    {
        self::assertSame('copy_on_loan', $this->check(null, 0, 0, $this->copy(CopySnapshot::ON_LOAN)));
    }

    public function test_a_withdrawn_copy_is_refused(): void
    {
        self::assertSame('copy_withdrawn', $this->check(null, 0, 0, $this->copy(CopySnapshot::WITHDRAWN)));
    }

    public function test_a_copy_reserved_for_someone_else_is_refused(): void
    {
        self::assertSame('copy_reserved_for_other', $this->check(null, 0, 0, $this->copy(CopySnapshot::RESERVED), false));
    }

    public function test_a_copy_reserved_for_this_member_can_be_borrowed(): void
    {
        self::assertNull($this->check(null, 0, 0, $this->copy(CopySnapshot::RESERVED), true));
    }

    public function test_the_reservation_flag_does_not_help_with_other_states(): void
    {
        self::assertSame('copy_on_loan', $this->check(null, 0, 0, $this->copy(CopySnapshot::ON_LOAN), true));
        self::assertSame('copy_withdrawn', $this->check(null, 0, 0, $this->copy(CopySnapshot::WITHDRAWN), true));
    }

    public function test_the_order_is_inactive_then_loans_then_fines_then_copy(): void
    {
        $withdrawn = $this->copy(CopySnapshot::WITHDRAWN);

        // everything fails at once: the member status comes first
        self::assertSame('member_inactive', $this->check($this->member(false), 3, 2, $withdrawn));
        // active member: the loan limit comes before fines and copy
        self::assertSame('max_active_loans', $this->check(null, 3, 2, $withdrawn));
        // below the limit: fines come before the copy state
        self::assertSame('unpaid_fines', $this->check(null, 1, 2, $withdrawn));
        // only the copy fails
        self::assertSame('copy_withdrawn', $this->check(null, 1, 0, $withdrawn));
    }

    public function test_the_snapshots_expose_their_data(): void
    {
        $member = new MemberSnapshot(7, 'Luis', false);
        $copy = new CopySnapshot(9, 3, 'Rayuela', CopySnapshot::RESERVED);

        self::assertSame(7, $member->getId());
        self::assertSame('Luis', $member->getName());
        self::assertFalse($member->isActive());
        self::assertSame(9, $copy->getId());
        self::assertSame(3, $copy->getBookId());
        self::assertSame('Rayuela', $copy->getBookTitle());
        self::assertSame(CopySnapshot::RESERVED, $copy->getStatus());
        self::assertTrue($copy->isReserved());
        self::assertFalse($copy->isAvailable());
    }

    public function test_an_unknown_copy_status_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CopySnapshot(1, 1, 'Libro', 'perdido');
    }
}
