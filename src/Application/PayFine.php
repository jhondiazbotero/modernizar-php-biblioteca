<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\LoanRepository;
use Library\Application\Port\TransactionManager;

/** Pays the fine of a loan. Error codes: loan_not_found, no_fine, already_paid. */
final class PayFine
{
    /** @var TransactionManager */
    private $transactions;

    /** @var LoanRepository */
    private $loans;

    public function __construct(TransactionManager $transactions, LoanRepository $loans)
    {
        $this->transactions = $transactions;
        $this->loans = $loans;
    }

    public function handle(int $loanId): PayResult
    {
        return $this->transactions->transactional(function () use ($loanId): PayResult {
            $loan = $this->loans->findForUpdate($loanId);
            if ($loan === null) {
                return PayResult::failure('loan_not_found');
            }
            if ($loan->getFine() <= 0) {
                return PayResult::failure('no_fine');
            }
            if ($loan->isFinePaid()) {
                return PayResult::failure('already_paid');
            }

            $this->loans->markFinePaid($loanId);

            return PayResult::success($loan->getFine());
        });
    }
}
