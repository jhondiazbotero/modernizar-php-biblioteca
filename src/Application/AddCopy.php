<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\BookRepository;
use Library\Application\Port\CopyRepository;
use Library\Application\Port\TransactionManager;

/** Adds an available copy to a book. Error codes: book_not_found, empty_code, code_taken. */
final class AddCopy
{
    /** @var TransactionManager */
    private $transactions;

    /** @var BookRepository */
    private $books;

    /** @var CopyRepository */
    private $copies;

    public function __construct(TransactionManager $transactions, BookRepository $books, CopyRepository $copies)
    {
        $this->transactions = $transactions;
        $this->books = $books;
        $this->copies = $copies;
    }

    public function handle(int $bookId, string $code): SaveResult
    {
        $code = trim($code);

        return $this->transactions->transactional(function () use ($bookId, $code): SaveResult {
            if (!$this->books->existsForUpdate($bookId)) {
                return SaveResult::failure('book_not_found');
            }
            if ($code === '') {
                return SaveResult::failure('empty_code');
            }
            if ($this->copies->codeTaken($code)) {
                return SaveResult::failure('code_taken');
            }

            return SaveResult::success($this->copies->add($bookId, $code));
        });
    }
}
