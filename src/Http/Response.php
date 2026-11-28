<?php

declare(strict_types=1);

namespace Library\Http;

/** What goes back to the browser: status, headers and body. */
final class Response
{
    /** @var int */
    private $status;

    /** @var array<string, string> */
    private $headers;

    /** @var string */
    private $body;

    /** @param array<string, string> $headers */
    private function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, [], $body);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], '');
    }

    /** Goes back to a screen with a message in the URL: ?msg=...&tipo=ok|error. */
    public static function redirectWithMessage(string $url, string $message, string $type = 'ok'): self
    {
        $separator = strpos($url, '?') === false ? '?' : '&';

        return self::redirect($url . $separator . 'msg=' . urlencode($message) . '&tipo=' . $type);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
