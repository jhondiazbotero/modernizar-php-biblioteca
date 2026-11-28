<?php

declare(strict_types=1);

namespace Library\Tests\Integration\Support;

use Library\Application\Port\CopyRepository;
use Library\Domain\CopySnapshot;

/** Reads like the real repository and fails when the copy status is updated. */
final class FailingCopyRepository implements CopyRepository
{
    /** @var CopyRepository */
    private $inner;

    public function __construct(CopyRepository $inner)
    {
        $this->inner = $inner;
    }

    public function findForUpdate(int $id): ?CopySnapshot
    {
        return $this->inner->findForUpdate($id);
    }

    public function countAvailableOfBook(int $bookId): int
    {
        return $this->inner->countAvailableOfBook($bookId);
    }

    public function codeTaken(string $code): bool
    {
        return $this->inner->codeTaken($code);
    }

    public function add(int $bookId, string $code): int
    {
        return $this->inner->add($bookId, $code);
    }

    public function updateStatus(int $copyId, string $status): void
    {
        throw new \RuntimeException('Forced failure while updating the copy.');
    }
}
