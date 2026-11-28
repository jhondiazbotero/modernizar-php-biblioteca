<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\TransactionManager;

/** Runs the work, remembers it was used and discards everything written when the work throws. */
final class FakeTransactionManager implements TransactionManager
{
    /** @var InMemoryStore */
    private $store;

    /** @var int */
    public $transactions = 0;

    /** @var int */
    public $rollbacks = 0;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function transactional(callable $work)
    {
        $this->transactions++;
        $before = $this->store->state();
        $this->store->inTransaction = true;
        try {
            return $work();
        } catch (\Throwable $e) {
            $this->store->restore($before);
            $this->rollbacks++;
            throw $e;
        } finally {
            $this->store->inTransaction = false;
        }
    }
}
