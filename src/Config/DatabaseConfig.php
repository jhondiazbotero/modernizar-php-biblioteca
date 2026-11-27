<?php

declare(strict_types=1);

namespace Library\Config;

/** Database connection settings read from the environment. */
final class DatabaseConfig
{
    /** @var string */
    private $host;
    /** @var string */
    private $name;
    /** @var string */
    private $user;
    /** @var string */
    private $password;

    private function __construct(string $host, string $name, string $user, string $password)
    {
        $this->host = $host;
        $this->name = $name;
        $this->user = $user;
        $this->password = $password;
    }

    /** @throws MissingConfigurationException when DB_HOST, DB_NAME, DB_USER or DB_PASSWORD is absent or empty */
    public static function fromEnvironment(): self
    {
        return new self(
            self::required('DB_HOST'),
            self::required('DB_NAME'),
            self::required('DB_USER'),
            self::required('DB_PASSWORD')
        );
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    private static function required(string $variable): string
    {
        $value = getenv($variable);
        if ($value === false || $value === '') {
            throw MissingConfigurationException::forVariable($variable);
        }

        return $value;
    }
}
