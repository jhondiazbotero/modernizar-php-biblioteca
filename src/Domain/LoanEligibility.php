<?php

declare(strict_types=1);

namespace Library\Domain;

final class LoanEligibility
{
    public const MEMBER_INACTIVE = 'member_inactive';
    public const MAX_ACTIVE_LOANS = 'max_active_loans';
    public const UNPAID_FINES = 'unpaid_fines';
    public const COPY_ON_LOAN = 'copy_on_loan';
    public const COPY_WITHDRAWN = 'copy_withdrawn';
    public const COPY_RESERVED_FOR_OTHER = 'copy_reserved_for_other';

    /**
     * Null when the member can borrow the copy; otherwise the first failing rule:
     * inactive member, loan limit, unpaid fines, state of the copy.
     */
    public function check(
        MemberSnapshot $member,
        int $activeLoans,
        int $unpaidFines,
        CopySnapshot $copy,
        bool $copyReservedForThisMember
    ): ?string {
        if (!$member->isActive()) {
            return self::MEMBER_INACTIVE;
        }
        if ($activeLoans >= LendingRules::MAX_ACTIVE_LOANS) {
            return self::MAX_ACTIVE_LOANS;
        }
        if ($unpaidFines > 0) {
            return self::UNPAID_FINES;
        }

        switch ($copy->getStatus()) {
            case CopySnapshot::ON_LOAN:
                return self::COPY_ON_LOAN;
            case CopySnapshot::WITHDRAWN:
                return self::COPY_WITHDRAWN;
            case CopySnapshot::RESERVED:
                return $copyReservedForThisMember ? null : self::COPY_RESERVED_FOR_OTHER;
        }

        return null;
    }
}
