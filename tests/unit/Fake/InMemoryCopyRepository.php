<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\CopyRepository;
use Library\Domain\CopySnapshot;

final class InMemoryCopyRepository implements CopyRepository
{
    /** @var InMemoryStore */
    private $store;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function findForUpdate(int $id): ?CopySnapshot
    {
        return $this->store->copies[$id] ?? null;
    }

    public function countAvailableOfBook(int $bookId): int
    {
        $count = 0;
        foreach ($this->store->copies as $copy) {
            if ($copy->getBookId() === $bookId && $copy->isAvailable()) {
                $count++;
            }
        }

        return $count;
    }

    public function updateStatus(int $copyId, string $status): void
    {
        $this->store->write('copies.updateStatus');
        $copy = $this->store->copies[$copyId];
        $this->store->copies[$copyId] = new CopySnapshot($copyId, $copy->getBookId(), $copy->getBookTitle(), $status);
    }
}
