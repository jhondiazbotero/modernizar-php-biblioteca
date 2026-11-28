<?php

declare(strict_types=1);

namespace Library\Characterization;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RedirectMiddleware;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client for the application under test. Keeps the session cookie and
 * sends the fixed-date header on every request.
 */
final class AppClient
{
    public const DEFAULT_DATE = '2026-04-15 10:00:00';

    private readonly Client $http;
    private readonly CookieJar $cookies;
    private string $date;

    public function __construct(?string $baseUri = null, string $date = self::DEFAULT_DATE)
    {
        $this->date = $date;
        $this->cookies = new CookieJar();
        $this->http = new Client([
            'base_uri' => $baseUri ?? (getenv('APP_URL') ?: 'http://app'),
            'cookies' => $this->cookies,
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => 15,
        ]);
    }

    /** Changes the clock the application sees ("Y-m-d" or "Y-m-d H:i:s"). */
    public function setDate(string $date): self
    {
        $this->date = $date;

        return $this;
    }

    /** @param array<string, scalar> $query */
    public function get(string $path, array $query = [], bool $follow = false): ResponseInterface
    {
        $options = ['headers' => ['X-Fecha-Fija' => $this->date], 'allow_redirects' => $this->redirects($follow)];
        if ($query !== []) {
            $options['query'] = $query;
        }

        return $this->http->get($path, $options);
    }

    /** @param array<string, scalar> $data */
    public function post(string $path, array $data = [], bool $follow = false): ResponseInterface
    {
        return $this->http->post($path, [
            'headers' => ['X-Fecha-Fija' => $this->date],
            'form_params' => $data,
            'allow_redirects' => $this->redirects($follow),
        ]);
    }

    public function login(string $user = 'admin', ?string $password = null, bool $follow = false): ResponseInterface
    {
        return $this->post('login.php', [
            'usuario' => $user,
            'clave' => $password ?? (string) getenv('DEMO_PASSWORD'),
        ], $follow);
    }

    /** Value of a cookie the application has set, or null. */
    public function cookie(string $name): ?string
    {
        $cookie = $this->cookies->getCookieByName($name);

        return $cookie === null ? null : (string) $cookie->getValue();
    }

    /** Path and query of the page a followed redirect ended on (null when none was followed). */
    public static function landedOn(ResponseInterface $response): ?string
    {
        $history = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
        if ($history === []) {
            return null;
        }
        $url = (string) end($history);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return ltrim($path, '/') . ($query !== null ? '?' . $query : '');
    }

    /** @return array<string, mixed>|false */
    private function redirects(bool $follow): array|false
    {
        return $follow ? ['max' => 5, 'strict' => false, 'track_redirects' => true] : false;
    }
}
