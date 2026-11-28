<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\CopyRepository;
use Library\Application\Port\TransactionManager;
use Library\Domain\CopySnapshot;

/**
 * Takes a copy of a book out of circulation. Error codes: copy_not_found (no such copy in
 * that book), copy_in_use (on loan or set aside for a reservation).
 */
final class WithdrawCopy
{
    /** @var TransactionManager */
    private $transactions;

    /** @var CopyRepository */
    private $copies;

    public function __construct(TransactionManager $transactions, CopyRepository $copies)
    {
        $this->transactions = $transactions;
        $this->copies = $copies;
    }

    public function handle(int $bookId, int $copyId): SaveResult
    {
        return $this->transactions->transactional(function () use ($bookId, $copyId): SaveResult {
            $copy = $this->copies->findForUpdate($copyId);
            if ($copy === null || $copy->getBookId() !== $bookId) {
                return SaveResult::failure('copy_not_found');
            }
            if ($copy->getStatus() === CopySnapshot::ON_LOAN || $copy->isReserved()) {
                return SaveResult::failure('copy_in_use');
            }

            $this->copies->updateStatus($copyId, CopySnapshot::WITHDRAWN);

            return SaveResult::success($copyId);
        });
    }
}
