<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\MemberQuery;

final class FakeMemberQuery implements MemberQuery
{
    /** @var array<int, array<string, mixed>> */
    public $members = [];

    /** @var array<int, array<int, array<string, mixed>>> loans by member id */
    public $loans = [];

    /** @var array<int, float> */
    public $unpaid = [];

    /** @param array<string, mixed> $overrides */
    public function addMember(int $id, string $name, array $overrides = []): void
    {
        $this->members[$id] = $overrides + [
            'id' => $id,
            'document' => '100' . $id,
            'name' => $name,
            'email' => 'm' . $id . '@example.com',
            'phone' => '300' . $id,
            'registered_on' => '2026-01-15',
            'active' => true,
        ];
    }

    public function all(): array
    {
        return array_values($this->members);
    }

    public function find(int $id): ?array
    {
        return $this->members[$id] ?? null;
    }

    public function loansOf(int $memberId): array
    {
        return $this->loans[$memberId] ?? [];
    }

    public function unpaidFineTotal(int $memberId): float
    {
        return $this->unpaid[$memberId] ?? 0.0;
    }
}
