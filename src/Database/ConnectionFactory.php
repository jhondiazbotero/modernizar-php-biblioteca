<?php

declare(strict_types=1);

namespace Library\Database;

use Library\Config\DatabaseConfig;
use PDO;

/** Opens the PDO connection used by the application. */
final class ConnectionFactory
{
    /**
     * @throws \PDOException when the server cannot be reached or rejects the credentials
     */
    public static function create(DatabaseConfig $config): PDO
    {
        return new PDO(self::dsn($config), $config->getUser(), $config->getPassword(), self::options());
    }

    public static function dsn(DatabaseConfig $config): string
    {
        return 'mysql:host=' . $config->getHost() . ';dbname=' . $config->getName() . ';charset=utf8mb4';
    }

    /**
     * Errors are exceptions, rows come back as associative arrays and every
     * statement is prepared by the server (no emulation in PHP).
     *
     * @return array<int, int|bool>
     */
    public static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
}
