<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\RegisterBook;

final class RegisterBookTest extends UseCaseTestCase
{
    private function useCase(): RegisterBook
    {
        return new RegisterBook($this->transactions, $this->books);
    }

    public function test_a_book_is_registered(): void
    {
        $result = $this->useCase()->handle('978-0', 'Rayuela', 'Julio Cortázar', 1963);

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->getId());
        self::assertSame(
            ['isbn' => '978-0', 'title' => 'Rayuela', 'author' => 'Julio Cortázar', 'year' => 1963],
            $this->store->bookData[1]
        );
        self::assertSame(['books.add'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_the_text_fields_are_trimmed_and_the_year_may_be_missing(): void
    {
        $this->useCase()->handle(' 978-0 ', '  Rayuela ', ' Julio Cortázar  ', null);

        self::assertSame(
            ['isbn' => '978-0', 'title' => 'Rayuela', 'author' => 'Julio Cortázar', 'year' => null],
            $this->store->bookData[1]
        );
    }

    public function test_title_and_author_are_required(): void
    {
        self::assertSame('required_fields', $this->useCase()->handle('', '', 'Julio Cortázar', 1963)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle('', 'Rayuela', '', 1963)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle('', '   ', 'Julio Cortázar', null)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle('978-0', 'Rayuela', "\t ", null)->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertSame([], $this->store->bookData);
    }

    public function test_the_isbn_is_optional(): void
    {
        self::assertTrue($this->useCase()->handle('', 'Rayuela', 'Julio Cortázar', null)->isSuccess());
    }

    public function test_a_failure_while_saving_leaves_nothing_behind(): void
    {
        $this->store->failOn('books.add');

        try {
            $this->useCase()->handle('', 'Rayuela', 'Julio Cortázar', null);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame([], $this->store->bookData);
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
