<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\FixedClock;
use Library\Tests\Fake\InMemoryBookRepository;
use Library\Tests\Fake\InMemoryCopyRepository;
use Library\Tests\Fake\InMemoryLoanRepository;
use Library\Tests\Fake\InMemoryMemberRepository;
use Library\Tests\Fake\InMemoryReservationRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

abstract class UseCaseTestCase extends TestCase
{
    /** @var InMemoryStore */
    protected $store;

    /** @var FakeTransactionManager */
    protected $transactions;

    /** @var InMemoryMemberRepository */
    protected $members;

    /** @var InMemoryBookRepository */
    protected $books;

    /** @var InMemoryCopyRepository */
    protected $copies;

    /** @var InMemoryLoanRepository */
    protected $loans;

    /** @var InMemoryReservationRepository */
    protected $reservations;

    /** @var FixedClock */
    protected $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->transactions = new FakeTransactionManager($this->store);
        $this->members = new InMemoryMemberRepository($this->store);
        $this->books = new InMemoryBookRepository($this->store);
        $this->copies = new InMemoryCopyRepository($this->store);
        $this->loans = new InMemoryLoanRepository($this->store);
        $this->reservations = new InMemoryReservationRepository($this->store);
        $this->clock = new FixedClock('2026-11-27 12:00:00');
    }
}
