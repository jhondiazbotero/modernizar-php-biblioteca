<?php

declare(strict_types=1);

namespace Library\Application\Port;

interface TransactionManager
{
    /**
     * Runs $work inside a transaction: commits when it returns and rolls back
     * everything it wrote when it throws (the exception is rethrown).
     *
     * @return mixed whatever $work returns
     */
    public function transactional(callable $work);
}
