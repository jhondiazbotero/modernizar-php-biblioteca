<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\PayFine;

final class PayFineTest extends UseCaseTestCase
{
    private function useCase(): PayFine
    {
        return new PayFine($this->transactions, $this->loans);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela');
    }

    public function test_an_unpaid_fine_is_paid(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-14', 6500, false);

        $result = $this->useCase()->handle(1);

        self::assertTrue($result->isSuccess());
        self::assertSame(6500, $result->getAmount());
        self::assertTrue($this->store->loans[1]['paid']);
        self::assertSame(['loans.markFinePaid'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_an_unknown_loan_is_reported(): void
    {
        self::assertSame('loan_not_found', $this->useCase()->handle(99)->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_loan_without_fine_is_reported(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-01', 0, false);

        self::assertSame('no_fine', $this->useCase()->handle(1)->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_fine_already_paid_is_reported(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-14', 6500, true);

        self::assertSame('already_paid', $this->useCase()->handle(1)->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_failure_leaves_the_fine_unpaid(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-14', 6500, false);
        $this->store->failOn('loans.markFinePaid');

        try {
            $this->useCase()->handle(1);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertFalse($this->store->loans[1]['paid']);
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
