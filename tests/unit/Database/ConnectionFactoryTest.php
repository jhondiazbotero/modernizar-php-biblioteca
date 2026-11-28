<?php

declare(strict_types=1);

namespace Library\Tests\Database;

use Library\Config\DatabaseConfig;
use Library\Database\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    /** @var array<string, string|false> */
    private $original = [];

    protected function setUp(): void
    {
        foreach (['DB_HOST' => 'db.internal', 'DB_NAME' => 'library_main', 'DB_USER' => 'library_user', 'DB_PASSWORD' => 'x'] as $name => $value) {
            $this->original[$name] = getenv($name);
            putenv($name . '=' . $value);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function test_the_dsn_names_host_database_and_charset(): void
    {
        self::assertSame(
            'mysql:host=db.internal;dbname=library_main;charset=utf8mb4',
            ConnectionFactory::dsn(DatabaseConfig::fromEnvironment())
        );
    }

    public function test_the_dsn_never_carries_the_password(): void
    {
        self::assertStringNotContainsString('password', ConnectionFactory::dsn(DatabaseConfig::fromEnvironment()));
    }

    public function test_errors_are_exceptions_and_statements_are_not_emulated(): void
    {
        $options = ConnectionFactory::options();

        self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        self::assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        self::assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
    }
}
