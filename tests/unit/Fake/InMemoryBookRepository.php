<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\BookRepository;

final class InMemoryBookRepository implements BookRepository
{
    /** @var InMemoryStore */
    private $store;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function existsForUpdate(int $id): bool
    {
        return isset($this->store->books[$id]);
    }

    public function add(string $isbn, string $title, string $author, ?int $year): int
    {
        $this->store->write('books.add');
        $id = $this->store->nextBookId++;
        $this->store->books[$id] = true;
        $this->store->bookData[$id] = ['isbn' => $isbn, 'title' => $title, 'author' => $author, 'year' => $year];

        return $id;
    }

    public function update(int $id, string $isbn, string $title, string $author, ?int $year): void
    {
        $this->store->write('books.update');
        if (!isset($this->store->books[$id])) {
            return;
        }
        $this->store->bookData[$id] = ['isbn' => $isbn, 'title' => $title, 'author' => $author, 'year' => $year];
    }
}
