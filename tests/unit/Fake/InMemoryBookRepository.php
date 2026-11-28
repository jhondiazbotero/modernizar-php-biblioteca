<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\BookRepository;

final class InMemoryBookRepository implements BookRepository
{
    /** @var InMemoryStore */
    private $store;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function existsForUpdate(int $id): bool
    {
        return isset($this->store->books[$id]);
    }
}
