<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\WithdrawCopy;
use Library\Domain\CopySnapshot;

final class WithdrawCopyTest extends UseCaseTestCase
{
    private function useCase(): WithdrawCopy
    {
        return new WithdrawCopy($this->transactions, $this->copies);
    }

    /** @return array<string, array{string}> */
    public function withdrawableStatuses(): array
    {
        return [
            'available' => [CopySnapshot::AVAILABLE],
            'already withdrawn' => [CopySnapshot::WITHDRAWN],
        ];
    }

    /** @dataProvider withdrawableStatuses */
    public function test_a_copy_that_is_not_in_use_is_withdrawn(string $status): void
    {
        $this->store->addCopy(10, 5, 'Rayuela', $status);

        $result = $this->useCase()->handle(5, 10);

        self::assertTrue($result->isSuccess());
        self::assertSame(CopySnapshot::WITHDRAWN, $this->store->copyStatus(10));
        self::assertSame(['copies.updateStatus'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    /** @return array<string, array{string}> */
    public function statusesInUse(): array
    {
        return [
            'on loan' => [CopySnapshot::ON_LOAN],
            'reserved' => [CopySnapshot::RESERVED],
        ];
    }

    /** @dataProvider statusesInUse */
    public function test_a_copy_on_loan_or_reserved_is_not_withdrawn(string $status): void
    {
        $this->store->addCopy(10, 5, 'Rayuela', $status);

        $result = $this->useCase()->handle(5, 10);

        self::assertSame('copy_in_use', $result->getErrorCode());
        self::assertSame($status, $this->store->copyStatus(10));
        self::assertSame([], $this->store->writes);
    }

    public function test_an_unknown_copy_is_reported(): void
    {
        self::assertSame('copy_not_found', $this->useCase()->handle(5, 99)->getErrorCode());
        self::assertSame('copy_not_found', $this->useCase()->handle(5, 0)->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_copy_of_another_book_is_not_found_in_this_one(): void
    {
        $this->store->addCopy(10, 6, 'Emma');

        self::assertSame('copy_not_found', $this->useCase()->handle(5, 10)->getErrorCode());
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
    }

    public function test_a_failure_while_saving_leaves_the_copy_as_it_was(): void
    {
        $this->store->addCopy(10, 5, 'Rayuela');
        $this->store->failOn('copies.updateStatus');

        try {
            $this->useCase()->handle(5, 10);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
