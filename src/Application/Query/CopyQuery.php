<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the copies screens. */
interface CopyQuery
{
    /**
     * Every copy of a book by code, with the status as the library writes it (disponible, prestado, reservado, baja).
     *
     * @return array<int, array{id: int, code: string, status: string}>
     */
    public function ofBook(int $bookId): array;

    /**
     * Every copy that is not withdrawn, by book title and code, for the select of the lending form.
     *
     * @return array<int, array{id: int, code: string, status: string, title: string}>
     */
    public function forLending(): array;
}
