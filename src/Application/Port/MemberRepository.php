<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\MemberSnapshot;

interface MemberRepository
{
    /** Reads the member and locks the row until the end of the transaction, so the limits of one member are checked one at a time. */
    public function findForUpdate(int $id): ?MemberSnapshot;

    /** Whether another member (any but $exceptId; use 0 for a new one) already has this document. */
    public function documentTaken(string $document, int $exceptId): bool;

    /** Registers a member and returns its id. */
    public function add(string $document, string $name, string $email, string $phone, \DateTimeImmutable $registeredOn, bool $active): int;

    /** Rewrites the data of a member; the registration date stays. */
    public function update(int $id, string $document, string $name, string $email, string $phone, bool $active): void;
}
