<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\BookRepository;
use Library\Application\Port\CopyRepository;
use Library\Application\Port\MemberRepository;
use Library\Application\Port\ReservationRepository;
use Library\Application\Port\TransactionManager;
use Library\Domain\Clock;

/**
 * Reserves a book that has no available copy. Error codes: member_not_found,
 * book_not_found, copies_available, already_pending.
 */
final class PlaceReservation
{
    /** @var TransactionManager */
    private $transactions;

    /** @var MemberRepository */
    private $members;

    /** @var BookRepository */
    private $books;

    /** @var CopyRepository */
    private $copies;

    /** @var ReservationRepository */
    private $reservations;

    /** @var Clock */
    private $clock;

    public function __construct(
        TransactionManager $transactions,
        MemberRepository $members,
        BookRepository $books,
        CopyRepository $copies,
        ReservationRepository $reservations,
        Clock $clock
    ) {
        $this->transactions = $transactions;
        $this->members = $members;
        $this->books = $books;
        $this->copies = $copies;
        $this->reservations = $reservations;
        $this->clock = $clock;
    }

    public function handle(int $memberId, int $bookId): ReservationResult
    {
        return $this->transactions->transactional(function () use ($memberId, $bookId): ReservationResult {
            $member = $this->members->findForUpdate($memberId);
            if ($member === null) {
                return ReservationResult::failure('member_not_found');
            }
            if (!$this->books->existsForUpdate($bookId)) {
                return ReservationResult::failure('book_not_found');
            }
            if ($this->copies->countAvailableOfBook($bookId) > 0) {
                return ReservationResult::failure('copies_available');
            }
            if ($this->reservations->hasPending($bookId, $memberId)) {
                return ReservationResult::failure('already_pending');
            }

            $id = $this->reservations->add($bookId, $memberId, $this->clock->now());

            return ReservationResult::success($id, $bookId, $member->getName());
        });
    }
}
