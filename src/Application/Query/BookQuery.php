<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the books screens. */
interface BookQuery
{
    /**
     * Every book by title, with how many copies it has (not withdrawn) and how many are available.
     *
     * @return array<int, array{id: int, isbn: string, title: string, author: string, year: int|null, total: int, available: int}>
     */
    public function allWithCopyCounts(): array;

    /** @return array{id: int, isbn: string, title: string, author: string, year: int|null}|null */
    public function find(int $id): ?array;
}
