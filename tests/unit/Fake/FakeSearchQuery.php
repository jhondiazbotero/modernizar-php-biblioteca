<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\SearchQuery;

final class FakeSearchQuery implements SearchQuery
{
    /** @var array<int, array{id: int, title: string, author: string, year: int|null, available: int}> */
    public $books = [];

    /** @var array<int, string> texts searched, in order */
    public $searched = [];

    public function booksMatching(string $text): array
    {
        $this->searched[] = $text;

        return $this->books;
    }
}
