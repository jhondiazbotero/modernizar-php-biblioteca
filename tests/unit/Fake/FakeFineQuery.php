<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\FineQuery;

final class FakeFineQuery implements FineQuery
{
    /** @var array<int, array{id: int, member_id: int, member: string, title: string, due_date: string, returned_on: string|null, fine: float, paid: bool}> */
    public $fines = [];

    public function all(): array
    {
        return $this->fines;
    }
}
