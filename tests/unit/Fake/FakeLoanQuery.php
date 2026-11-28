<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\LoanQuery;

final class FakeLoanQuery implements LoanQuery
{
    /** @var array<int, array<string, mixed>> */
    public $active = [];

    /** @var array<int, array<string, mixed>> */
    public $overdue = [];

    /** @var array<int, array<string, mixed>> */
    public $returned = [];

    /** @var array<int, array{id: int, due_date: string, member: string, title: string, code: string}> */
    public $openForReturn = [];

    /** @var string|null the date the last overdue query was asked for */
    public $overdueAsked;

    /** @var array<int, string> lists asked for, in order */
    public $asked = [];

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function loan(int $id, string $member, string $title, array $overrides = []): array
    {
        return $overrides + [
            'id' => $id,
            'member_id' => 40 + $id,
            'member' => $member,
            'title' => $title,
            'code' => 'BIB-' . $id,
            'loaned_on' => '2026-11-01',
            'due_date' => '2026-11-15',
            'returned_on' => null,
            'fine' => 0.0,
        ];
    }

    public function active(): array
    {
        $this->asked[] = 'active';

        return $this->active;
    }

    public function overdue(string $today): array
    {
        $this->asked[] = 'overdue';
        $this->overdueAsked = $today;

        return $this->overdue;
    }

    public function returned(): array
    {
        $this->asked[] = 'returned';

        return $this->returned;
    }

    public function openForReturn(): array
    {
        return $this->openForReturn;
    }
}
