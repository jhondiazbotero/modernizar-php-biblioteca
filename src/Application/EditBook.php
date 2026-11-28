<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\BookRepository;
use Library\Application\Port\TransactionManager;

/** Rewrites the data of a book. Error code: required_fields (title and author). */
final class EditBook
{
    /** @var TransactionManager */
    private $transactions;

    /** @var BookRepository */
    private $books;

    public function __construct(TransactionManager $transactions, BookRepository $books)
    {
        $this->transactions = $transactions;
        $this->books = $books;
    }

    public function handle(int $id, string $isbn, string $title, string $author, ?int $year): SaveResult
    {
        $isbn = trim($isbn);
        $title = trim($title);
        $author = trim($author);

        if ($title === '' || $author === '') {
            return SaveResult::failure('required_fields');
        }

        return $this->transactions->transactional(function () use ($id, $isbn, $title, $author, $year): SaveResult {
            $this->books->update($id, $isbn, $title, $author, $year);

            return SaveResult::success($id);
        });
    }
}
