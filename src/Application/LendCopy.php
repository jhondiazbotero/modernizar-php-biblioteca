<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\CopyRepository;
use Library\Application\Port\LoanRepository;
use Library\Application\Port\MemberRepository;
use Library\Application\Port\ReservationRepository;
use Library\Application\Port\TransactionManager;
use Library\Domain\Clock;
use Library\Domain\CopySnapshot;
use Library\Domain\LendingRules;
use Library\Domain\LoanEligibility;

/**
 * Lends a copy to a member. Error codes: member_not_found, copy_not_found and the
 * ones of LoanEligibility.
 */
final class LendCopy
{
    /** @var TransactionManager */
    private $transactions;

    /** @var MemberRepository */
    private $members;

    /** @var CopyRepository */
    private $copies;

    /** @var LoanRepository */
    private $loans;

    /** @var ReservationRepository */
    private $reservations;

    /** @var Clock */
    private $clock;

    /** @var LoanEligibility */
    private $eligibility;

    public function __construct(
        TransactionManager $transactions,
        MemberRepository $members,
        CopyRepository $copies,
        LoanRepository $loans,
        ReservationRepository $reservations,
        Clock $clock
    ) {
        $this->transactions = $transactions;
        $this->members = $members;
        $this->copies = $copies;
        $this->loans = $loans;
        $this->reservations = $reservations;
        $this->clock = $clock;
        $this->eligibility = new LoanEligibility();
    }

    public function handle(int $memberId, int $copyId): LendResult
    {
        return $this->transactions->transactional(function () use ($memberId, $copyId): LendResult {
            $member = $this->members->find($memberId);
            if ($member === null) {
                return LendResult::failure('member_not_found');
            }
            $copy = $this->copies->findForUpdate($copyId);
            if ($copy === null) {
                return LendResult::failure('copy_not_found');
            }

            $reservationId = $copy->isReserved() ? $this->reservations->findAssignedId($copyId, $memberId) : null;
            $error = $this->eligibility->check(
                $member,
                $this->loans->countActiveOfMember($memberId),
                $this->loans->countUnpaidFinesOfMember($memberId),
                $copy,
                $reservationId !== null
            );
            if ($error !== null) {
                return LendResult::failure($error);
            }

            $today = $this->clock->today();
            $dueDate = $today->add(new \DateInterval('P' . LendingRules::LOAN_DAYS . 'D'));
            $loanId = $this->loans->add($copyId, $memberId, $today, $dueDate);
            $this->copies->updateStatus($copyId, CopySnapshot::ON_LOAN);
            if ($reservationId !== null) {
                $this->reservations->markFulfilled($reservationId);
            }

            return LendResult::success($loanId, $copy->getBookTitle(), $member->getName(), $dueDate);
        });
    }
}
