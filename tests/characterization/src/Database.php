<?php

declare(strict_types=1);

namespace Library\Characterization;

use PDO;
use RuntimeException;

/**
 * Direct access to the application database as root. The data of every test
 * starts from a template schema created once per run.
 */
final class Database
{
    public const TEMPLATE = 'biblioteca_plantilla';

    private static ?self $instance = null;

    /** @var list<string>|null */
    private ?array $tables = null;

    private function __construct(private readonly PDO $pdo, private readonly string $database)
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(self::connect(self::env('DB_NAME')), self::env('DB_NAME'));
    }

    /**
     * Builds the template database from the project scripts: schema, seed and
     * the initial administrator (same logic as db/03-admin.sh).
     */
    public static function createTemplate(): void
    {
        $dir = getenv('DB_SCRIPTS_DIR') ?: '/db';
        $pdo = self::connect(null);
        $pdo->exec('DROP DATABASE IF EXISTS ' . self::TEMPLATE);
        $pdo->exec('CREATE DATABASE ' . self::TEMPLATE . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE ' . self::TEMPLATE);

        foreach (['01-esquema.sql', '02-semilla.sql'] as $file) {
            $sql = file_get_contents($dir . '/' . $file);
            if ($sql === false) {
                throw new RuntimeException("Cannot read {$dir}/{$file}");
            }
            foreach (preg_split('/;[ \t]*\r?\n/', $sql) ?: [] as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }
        }

        $pdo->prepare('INSERT INTO bibliotecarios (usuario, clave, nombre) VALUES (?, ?, ?)')
            ->execute(['admin', md5(self::env('DEMO_PASSWORD')), 'Administrador']);
    }

    /** Puts every application table back to the template contents. */
    public function reset(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->tables() as $table) {
            $this->pdo->exec("TRUNCATE TABLE {$this->database}.{$table}");
            $this->pdo->exec("INSERT INTO {$this->database}.{$table} SELECT * FROM " . self::TEMPLATE . ".{$table}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @param list<scalar|null> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param list<scalar|null> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<scalar|null> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);

        return $row === null ? null : reset($row);
    }

    /** @param list<scalar|null> $params */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /** @return list<string> */
    private function tables(): array
    {
        if ($this->tables === null) {
            $this->tables = $this->pdo
                ->query('SELECT table_name FROM information_schema.tables WHERE table_schema = \'' . self::TEMPLATE . '\' ORDER BY table_name')
                ->fetchAll(PDO::FETCH_COLUMN);
        }

        return $this->tables;
    }

    private static function connect(?string $database): PDO
    {
        $dsn = 'mysql:host=' . self::env('DB_HOST') . ';charset=utf8mb4' . ($database !== null ? ';dbname=' . $database : '');

        return new PDO($dsn, 'root', self::env('MYSQL_ROOT_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private static function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new RuntimeException("Missing environment variable {$name}");
        }

        return $value;
    }
}
