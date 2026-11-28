<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\EditBook;

final class EditBookTest extends UseCaseTestCase
{
    private function useCase(): EditBook
    {
        return new EditBook($this->transactions, $this->books);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addBook(3, 'Rayuela', 'Julio Cortázar');
    }

    public function test_the_data_of_a_book_is_rewritten(): void
    {
        $result = $this->useCase()->handle(3, '978-1', ' Rayuela (edición) ', 'J. Cortázar', 1966);

        self::assertTrue($result->isSuccess());
        self::assertSame(3, $result->getId());
        self::assertSame(
            ['isbn' => '978-1', 'title' => 'Rayuela (edición)', 'author' => 'J. Cortázar', 'year' => 1966],
            $this->store->bookData[3]
        );
        self::assertSame(['books.update'], $this->store->writes);
    }

    public function test_title_and_author_are_required(): void
    {
        self::assertSame('required_fields', $this->useCase()->handle(3, '', '', 'Autor', null)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle(3, '', 'Título', ' ', null)->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertSame('Rayuela', $this->store->bookData[3]['title']);
    }

    public function test_a_failure_while_saving_leaves_the_book_as_it_was(): void
    {
        $this->store->failOn('books.update');

        try {
            $this->useCase()->handle(3, '', 'Otro', 'Otro autor', null);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame('Rayuela', $this->store->bookData[3]['title']);
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
