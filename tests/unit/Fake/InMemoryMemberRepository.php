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

    public function documentTaken(string $document, int $exceptId): bool
    {
        foreach ($this->store->memberData as $id => $data) {
            if ($id !== $exceptId && $data['document'] === $document) {
                return true;
            }
        }

        return false;
    }

    public function add(string $document, string $name, string $email, string $phone, \DateTimeImmutable $registeredOn, bool $active): int
    {
        $this->store->write('members.add');
        $id = $this->store->nextMemberId++;
        $this->store->members[$id] = new MemberSnapshot($id, $name, $active);
        $this->store->memberData[$id] = ['document' => $document, 'email' => $email, 'phone' => $phone, 'registeredOn' => $registeredOn];

        return $id;
    }

    public function update(int $id, string $document, string $name, string $email, string $phone, bool $active): void
    {
        $this->store->write('members.update');
        if (!isset($this->store->memberData[$id])) {
            return;
        }
        $this->store->members[$id] = new MemberSnapshot($id, $name, $active);
        $registeredOn = $this->store->memberData[$id]['registeredOn'];
        $this->store->memberData[$id] = ['document' => $document, 'email' => $email, 'phone' => $phone, 'registeredOn' => $registeredOn];
    }
}
