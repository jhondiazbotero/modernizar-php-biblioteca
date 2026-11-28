<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\BookQuery;

final class FakeBookQuery implements BookQuery
{
    /** @var array<int, array<string, mixed>> */
    public $books = [];

    /** @param array<string, mixed> $overrides */
    public function addBook(int $id, string $title, string $author, array $overrides = []): void
    {
        $this->books[$id] = $overrides + [
            'id' => $id,
            'isbn' => '',
            'title' => $title,
            'author' => $author,
            'year' => null,
            'total' => 0,
            'available' => 0,
        ];
    }

    public function allWithCopyCounts(): array
    {
        return array_values($this->books);
    }

    public function titleOf(int $id): ?string
    {
        return isset($this->books[$id]) ? (string) $this->books[$id]['title'] : null;
    }

    public function options(): array
    {
        $options = [];
        foreach ($this->books as $book) {
            $options[] = ['id' => $book['id'], 'title' => $book['title'], 'author' => $book['author']];
        }

        return $options;
    }

    public function find(int $id): ?array
    {
        if (!isset($this->books[$id])) {
            return null;
        }
        $book = $this->books[$id];

        return ['id' => $book['id'], 'isbn' => $book['isbn'], 'title' => $book['title'], 'author' => $book['author'], 'year' => $book['year']];
    }
}
