<?php

declare(strict_types=1);

namespace Library\Tests\Config;

use Library\Config\DatabaseConfig;
use Library\Config\MissingConfigurationException;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    private const VARIABLES = [
        'DB_HOST' => 'db.internal',
        'DB_NAME' => 'library_main',
        'DB_USER' => 'library_user',
        'DB_PASSWORD' => 'S3cr3t-Distinct-Value-91',
    ];

    /** @var array<string, string|false> */
    private $original = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name => $value) {
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

    public function test_it_reads_the_four_variables(): void
    {
        $config = DatabaseConfig::fromEnvironment();

        self::assertSame('db.internal', $config->getHost());
        self::assertSame('library_main', $config->getName());
        self::assertSame('library_user', $config->getUser());
        self::assertSame('S3cr3t-Distinct-Value-91', $config->getPassword());
    }

    /** @return array<string, array{string}> */
    public function variable_names(): array
    {
        $cases = [];
        foreach (array_keys(self::VARIABLES) as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * @dataProvider variable_names
     */
    public function test_a_missing_variable_is_reported_by_name(string $name): void
    {
        putenv($name);

        try {
            DatabaseConfig::fromEnvironment();
            self::fail('A missing variable must raise an exception.');
        } catch (MissingConfigurationException $e) {
            self::assertStringContainsString($name, $e->getMessage());
        }
    }

    /**
     * @dataProvider variable_names
     */
    public function test_an_empty_variable_counts_as_missing(string $name): void
    {
        putenv($name . '=');

        try {
            DatabaseConfig::fromEnvironment();
            self::fail('An empty variable must raise an exception.');
        } catch (MissingConfigurationException $e) {
            self::assertStringContainsString($name, $e->getMessage());
        }
    }

    /**
     * @dataProvider variable_names
     */
    public function test_the_message_never_contains_the_value_of_another_variable(string $name): void
    {
        putenv($name);

        try {
            DatabaseConfig::fromEnvironment();
            self::fail('A missing variable must raise an exception.');
        } catch (MissingConfigurationException $e) {
            foreach (self::VARIABLES as $other => $value) {
                self::assertStringNotContainsString($value, $e->getMessage(), "The message leaks the value of {$other}");
            }
        }
    }

    public function test_the_first_missing_variable_is_the_one_reported(): void
    {
        putenv('DB_USER');
        putenv('DB_PASSWORD');

        $this->expectException(MissingConfigurationException::class);
        $this->expectExceptionMessage('DB_USER');

        DatabaseConfig::fromEnvironment();
    }
}
