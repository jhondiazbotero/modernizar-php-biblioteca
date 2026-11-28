<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\AddCopy;
use Library\Domain\CopySnapshot;

final class AddCopyTest extends UseCaseTestCase
{
    private function useCase(): AddCopy
    {
        return new AddCopy($this->transactions, $this->books, $this->copies);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addBook(5, 'Rayuela', 'Julio Cortázar');
    }

    public function test_a_copy_is_added_available(): void
    {
        $result = $this->useCase()->handle(5, 'BIB-0100');

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->getId());
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copies[1]->getStatus());
        self::assertSame(5, $this->store->copies[1]->getBookId());
        self::assertSame('BIB-0100', $this->store->copyCodes[1]);
        self::assertSame(['copies.add'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_the_code_is_trimmed(): void
    {
        $this->useCase()->handle(5, '  BIB-0100 ');

        self::assertSame('BIB-0100', $this->store->copyCodes[1]);
    }

    public function test_the_code_is_required(): void
    {
        self::assertSame('empty_code', $this->useCase()->handle(5, '')->getErrorCode());
        self::assertSame('empty_code', $this->useCase()->handle(5, "  \t")->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_code_that_exists_in_any_book_is_rejected(): void
    {
        $this->store->addCopy(20, 9, 'Emma');
        $this->store->copyCodes[20] = 'BIB-0100';

        $result = $this->useCase()->handle(5, 'BIB-0100');

        self::assertSame('code_taken', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertCount(1, $this->store->copies);
    }

    public function test_an_unknown_book_is_reported_before_the_code_is_looked_at(): void
    {
        self::assertSame('book_not_found', $this->useCase()->handle(99, '')->getErrorCode());
        self::assertSame('book_not_found', $this->useCase()->handle(99, 'BIB-0100')->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_failure_while_saving_leaves_nothing_behind(): void
    {
        $this->store->failOn('copies.add');

        try {
            $this->useCase()->handle(5, 'BIB-0100');
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame([], $this->store->copies);
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
