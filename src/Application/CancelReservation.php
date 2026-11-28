<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\CopyRepository;
use Library\Application\Port\ReservationRepository;
use Library\Application\Port\TransactionManager;
use Library\Domain\CopySnapshot;
use Library\Domain\ReservationQueue;

/**
 * Cancels a pending or assigned reservation; when it held a copy, the copy goes to
 * the next in the queue or back to the shelf. Error codes: reservation_not_found,
 * not_cancellable.
 */
final class CancelReservation
{
    /** @var TransactionManager */
    private $transactions;

    /** @var ReservationRepository */
    private $reservations;

    /** @var CopyRepository */
    private $copies;

    /** @var ReservationQueue */
    private $queue;

    public function __construct(
        TransactionManager $transactions,
        ReservationRepository $reservations,
        CopyRepository $copies,
        ReservationQueue $queue
    ) {
        $this->transactions = $transactions;
        $this->reservations = $reservations;
        $this->copies = $copies;
        $this->queue = $queue;
    }

    public function handle(int $reservationId): CancelResult
    {
        return $this->transactions->transactional(function () use ($reservationId): CancelResult {
            $reservation = $this->reservations->findForUpdate($reservationId);
            if ($reservation === null) {
                return CancelResult::failure('reservation_not_found');
            }
            if (!$reservation->canBeCancelled()) {
                return CancelResult::failure('not_cancellable');
            }

            $this->reservations->cancel($reservationId);
            if (!$reservation->isAssigned()) {
                return CancelResult::success(false, null);
            }

            $copyId = (int) $reservation->getCopyId();
            $next = $this->queue->next($this->reservations->pendingOfBook($reservation->getBookId()));
            if ($next !== null) {
                $this->reservations->assign($next->getId(), $copyId);
                $this->copies->updateStatus($copyId, CopySnapshot::RESERVED);

                return CancelResult::success(true, $next->getMemberName());
            }

            $this->copies->updateStatus($copyId, CopySnapshot::AVAILABLE);

            return CancelResult::success(true, null);
        });
    }
}
