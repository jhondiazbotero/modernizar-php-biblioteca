<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\MemberSnapshot;

interface MemberRepository
{
    public function find(int $id): ?MemberSnapshot;
}
