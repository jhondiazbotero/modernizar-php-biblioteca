<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\MemberRepository;
use Library\Domain\MemberSnapshot;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var InMemoryStore */
    private $store;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function findForUpdate(int $id): ?MemberSnapshot
    {
        return $this->store->members[$id] ?? null;
    }
}
