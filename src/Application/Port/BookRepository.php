<?php

declare(strict_types=1);

namespace Library\Application\Port;

interface BookRepository
{
    /** Whether the book exists; inside a transaction it locks the row so reservations of one book are serialized. */
    public function existsForUpdate(int $id): bool;

    /** Registers a book and returns its id. */
    public function add(string $isbn, string $title, string $author, ?int $year): int;

    public function update(int $id, string $isbn, string $title, string $author, ?int $year): void;
}
