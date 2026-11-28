<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the search screen. */
interface SearchQuery
{
    /**
     * Books whose title or author contains the text, as plain text (the characters % _ and \ are not wildcards), by title.
     *
     * @return array<int, array{id: int, title: string, author: string, year: int|null, available: int}>
     */
    public function booksMatching(string $text): array;
}
