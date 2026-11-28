<?php

declare(strict_types=1);

namespace Library\Http;

/** One HTTP request: what the controllers know about what the browser sent. Immutable. */
final class Request
{
    /** @var string */
    private $method;

    /** @var string|null */
    private $path;

    /** @var array<string, mixed> */
    private $query;

    /** @var array<string, mixed> */
    private $post;

    /** @var array<string, string> */
    private $headers;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $post
     * @param array<string, string> $headers header names in any case
     */
    public function __construct(string $method, ?string $path, array $query = [], array $post = [], array $headers = [])
    {
        $this->method = $method;
        $this->path = $path;
        $this->query = $query;
        $this->post = $post;
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = $value;
        }
    }

    public static function fromGlobals(): self
    {
        $path = parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && strpos((string) $key, 'HTTP_') === 0) {
                $headers[str_replace('_', '-', substr((string) $key, 5))] = $value;
            }
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return new self(is_string($method) ? $method : 'GET', is_string($path) ? $path : null, $_GET, $_POST, $headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Path of the URL without the query string; null when the URL could not be read. */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /** @return array<string, string> header names in lower case */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Text of a query parameter; the default when it is missing or is not text (for example "q[]=x"). */
    public function queryString(string $name, string $default = ''): string
    {
        return is_string($this->query[$name] ?? null) ? $this->query[$name] : $default;
    }

    /** Integer of a query parameter, 0 when missing. */
    public function queryInt(string $name): int
    {
        return self::toInt($this->query[$name] ?? null);
    }

    public function postString(string $name, string $default = ''): string
    {
        return is_string($this->post[$name] ?? null) ? $this->post[$name] : $default;
    }

    public function postInt(string $name): int
    {
        return self::toInt($this->post[$name] ?? null);
    }

    /** Whether the form sent the field at all (a checked box is sent, an unchecked one is not). */
    public function hasPost(string $name): bool
    {
        return isset($this->post[$name]);
    }

    /** @param mixed $value */
    private static function toInt($value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }
}
