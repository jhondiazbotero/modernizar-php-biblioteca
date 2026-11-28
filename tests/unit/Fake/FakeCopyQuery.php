<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\CopyQuery;

final class FakeCopyQuery implements CopyQuery
{
    /** @var array<int, array<int, array{id: int, code: string, status: string}>> by book id */
    public $copies = [];

    public function add(int $bookId, int $id, string $code, string $status): void
    {
        $this->copies[$bookId][] = ['id' => $id, 'code' => $code, 'status' => $status];
    }

    /** @var array<int, array{id: int, code: string, status: string, title: string}> */
    public $lendable = [];

    public function forLending(): array
    {
        return $this->lendable;
    }

    public function ofBook(int $bookId): array
    {
        return $this->copies[$bookId] ?? [];
    }
}
