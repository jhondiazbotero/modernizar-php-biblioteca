<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\CopyRepository;
use Library\Application\Port\LoanRepository;
use Library\Application\Port\ReservationRepository;
use Library\Application\Port\TransactionManager;
use Library\Domain\Clock;
use Library\Domain\CopySnapshot;
use Library\Domain\FinePolicy;
use Library\Domain\ReservationQueue;

/**
 * Registers the return of a loan, charges the fine and sets the copy aside for the
 * next reservation. Error codes: loan_not_found, already_returned.
 */
final class ReturnLoan
{
    /** @var TransactionManager */
    private $transactions;

    /** @var LoanRepository */
    private $loans;

    /** @var CopyRepository */
    private $copies;

    /** @var ReservationRepository */
    private $reservations;

    /** @var Clock */
    private $clock;

    /** @var FinePolicy */
    private $finePolicy;

    /** @var ReservationQueue */
    private $queue;

    public function __construct(
        TransactionManager $transactions,
        LoanRepository $loans,
        CopyRepository $copies,
        ReservationRepository $reservations,
        Clock $clock,
        FinePolicy $finePolicy,
        ReservationQueue $queue
    ) {
        $this->transactions = $transactions;
        $this->loans = $loans;
        $this->copies = $copies;
        $this->reservations = $reservations;
        $this->clock = $clock;
        $this->finePolicy = $finePolicy;
        $this->queue = $queue;
    }

    public function handle(int $loanId): ReturnResult
    {
        return $this->transactions->transactional(function () use ($loanId): ReturnResult {
            $loan = $this->loans->findForUpdate($loanId);
            if ($loan === null) {
                return ReturnResult::failure('loan_not_found');
            }
            if ($loan->isReturned()) {
                return ReturnResult::failure('already_returned');
            }

            $today = $this->clock->today();
            $fine = $this->finePolicy->fineFor($loan->getDueDate(), $today);
            $this->loans->markReturned($loanId, $today, $fine);

            $next = $this->queue->next($this->reservations->pendingOfBook($loan->getBookId()));
            if ($next !== null) {
                $this->reservations->assign($next->getId(), $loan->getCopyId());
                $this->copies->updateStatus($loan->getCopyId(), CopySnapshot::RESERVED);
            } else {
                $this->copies->updateStatus($loan->getCopyId(), CopySnapshot::AVAILABLE);
            }

            return ReturnResult::success(
                $loan->getBookTitle(),
                $loan->getMemberName(),
                $fine,
                $next === null ? null : $next->getMemberName()
            );
        });
    }
}
