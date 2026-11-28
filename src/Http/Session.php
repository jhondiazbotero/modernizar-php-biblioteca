<?php

declare(strict_types=1);

namespace Library\Http;

/**
 * The session of the person using the application. The native one wraps $_SESSION;
 * the in-memory one keeps the values in the object (used when there is no browser).
 */
final class Session
{
    /** @var bool */
    private $native;

    /** @var array<string, mixed> */
    private $data;

    /** @var int */
    private $regenerations = 0;

    /** @var bool */
    private $destroyed = false;

    /** @param array<string, mixed> $data */
    private function __construct(bool $native, array $data)
    {
        $this->native = $native;
        $this->data = $data;
    }

    public static function native(): self
    {
        return new self(true, []);
    }

    /** @param array<string, mixed> $data */
    public static function inMemory(array $data = []): self
    {
        return new self(false, $data);
    }

    /** Opens the session (once) so its values can be read. */
    public function start(): void
    {
        if (!$this->native || session_id() !== '') {
            return;
        }
        session_start();
        $this->data = &$_SESSION;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /** @param mixed $value */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /** New session id with the same values; the old id stops working. */
    public function regenerate(): void
    {
        $this->regenerations++;
        if ($this->native) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $this->destroyed = true;
        if ($this->native && session_id() !== '') {
            session_destroy();
        }
    }

    public function regenerationCount(): int
    {
        return $this->regenerations;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }
}
