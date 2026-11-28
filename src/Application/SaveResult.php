<?php

declare(strict_types=1);

namespace Library\Application;

/** Outcome of creating or changing a record: the id of the record, or an error code. */
final class SaveResult extends OperationResult
{
    /** @var int */
    private $id = 0;

    public static function success(int $id): self
    {
        $result = new self(null);
        $result->id = $id;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    public function getId(): int
    {
        return $this->id;
    }
}
