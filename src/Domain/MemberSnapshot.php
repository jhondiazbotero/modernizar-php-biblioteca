<?php

declare(strict_types=1);

namespace Library\Domain;

/** What the lending rules need to know about a member. */
final class MemberSnapshot
{
    /** @var int */
    private $id;

    /** @var string */
    private $name;

    /** @var bool */
    private $active;

    public function __construct(int $id, string $name, bool $active)
    {
        $this->id = $id;
        $this->name = $name;
        $this->active = $active;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
