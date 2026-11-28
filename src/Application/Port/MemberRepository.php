<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\MemberSnapshot;

interface MemberRepository
{
    /** Reads the member and locks the row until the end of the transaction, so the limits of one member are checked one at a time. */
    public function findForUpdate(int $id): ?MemberSnapshot;
}
