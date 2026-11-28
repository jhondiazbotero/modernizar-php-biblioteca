<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\CopySnapshot;

interface CopyRepository
{
    /** Reads the copy and locks it until the end of the transaction. */
    public function findForUpdate(int $id): ?CopySnapshot;

    public function countAvailableOfBook(int $bookId): int;

    /** @param string $status one of the CopySnapshot status constants */
    public function updateStatus(int $copyId, string $status): void;
}
