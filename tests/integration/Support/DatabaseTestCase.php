<?php

declare(strict_types=1);

namespace Library\Tests\Integration\Support;

use Library\Application\CancelReservation;
use Library\Application\LendCopy;
use Library\Application\PayFine;
use Library\Application\PlaceReservation;
use Library\Application\Port\CopyRepository;
use Library\Application\Port\ReservationRepository;
use Library\Application\ReturnLoan;
use Library\Config\DatabaseConfig;
use Library\Database\ConnectionFactory;
use Library\Domain\Clock;
use Library\Domain\FinePolicy;
use Library\Domain\ReservationQueue;
use Library\Infrastructure\Persistence\PdoBookRepository;
use Library\Infrastructure\Persistence\PdoCopyRepository;
use Library\Infrastructure\Persistence\PdoLoanRepository;
use Library\Infrastructure\Persistence\PdoMemberRepository;
use Library\Infrastructure\Persistence\PdoReservationRepository;
use Library\Infrastructure\Persistence\PdoTransactionManager;
use Library\Tests\Fake\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real MySQL server: a scratch database with the application schema,
 * emptied before every test. Two connections: one for the code under test and one
 * to look at what was really committed.
 */
abstract class DatabaseTestCase extends TestCase
{
    /** @var \PDO */
    protected $pdo;

    /** @var \PDO */
    protected $observer;

    public static function setUpBeforeClass(): void
    {
        $config = DatabaseConfig::fromEnvironment();
        $server = new \PDO(
            'mysql:host=' . $config->getHost() . ';charset=utf8mb4',
            $config->getUser(),
            $config->getPassword(),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $name = '`' . str_replace('`', '', $config->getName()) . '`';
        $server->exec('DROP DATABASE IF EXISTS ' . $name);
        $server->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $server->exec('USE ' . $name);
        $server->exec((string) file_get_contents(__DIR__ . '/../../../db/01-esquema.sql'));
    }

    protected function setUp(): void
    {
        $config = DatabaseConfig::fromEnvironment();
        $this->pdo = ConnectionFactory::create($config);
        $this->observer = ConnectionFactory::create($config);
        foreach (['reservas', 'prestamos', 'ejemplares', 'libros', 'socios'] as $table) {
            $this->pdo->exec('TRUNCATE TABLE ' . $table);
        }
        $this->loadFixture();
    }

    protected function tearDown(): void
    {
        self::assertFalse($this->pdo->inTransaction(), 'No transaction may be left open.');
    }

    /** One book, three members, one copy on loan to the first member, and a pending reservation. */
    protected function loadFixture(): void
    {
        $this->pdo->exec("INSERT INTO socios (id, documento, nombre, fecha_alta, activo) VALUES
            (1, '1001', 'Ana Torres', '2026-01-01', 1),
            (2, '1002', 'Zoe Primera', '2026-01-01', 1),
            (3, '1003', 'Álvaro Segundo', '2026-01-01', 1),
            (4, '1004', 'Inactivo Perez', '2026-01-01', 0)");
        $this->pdo->exec("INSERT INTO libros (id, titulo, autor) VALUES (5, 'Rayuela', 'Julio Cortázar')");
        $this->pdo->exec("INSERT INTO ejemplares (id, libro_id, codigo, estado) VALUES
            (10, 5, 'BIB-0010', 'prestado'),
            (11, 5, 'BIB-0011', 'disponible')");
        $this->pdo->exec("INSERT INTO prestamos (id, ejemplar_id, socio_id, fecha_prestamo, fecha_vence, fecha_devolucion, multa, multa_pagada) VALUES
            (1, 10, 1, '2026-11-01', '2026-11-14', NULL, 0, 0)");
    }

    /** @param array<int, mixed> $params @return mixed */
    protected function scalar(string $sql, array $params = [])
    {
        $statement = $this->observer->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    protected function clock(): Clock
    {
        return new FixedClock('2026-11-27 12:00:00');
    }

    protected function transactions(): PdoTransactionManager
    {
        return new PdoTransactionManager($this->pdo);
    }

    protected function copyRepository(): PdoCopyRepository
    {
        return new PdoCopyRepository($this->pdo);
    }

    protected function reservationRepository(): PdoReservationRepository
    {
        return new PdoReservationRepository($this->pdo);
    }

    protected function lendCopy(): LendCopy
    {
        return new LendCopy(
            $this->transactions(),
            new PdoMemberRepository($this->pdo),
            $this->copyRepository(),
            new PdoLoanRepository($this->pdo),
            $this->reservationRepository(),
            $this->clock()
        );
    }

    protected function returnLoan(?CopyRepository $copies = null, ?ReservationRepository $reservations = null): ReturnLoan
    {
        return new ReturnLoan(
            $this->transactions(),
            new PdoLoanRepository($this->pdo),
            $copies ?? $this->copyRepository(),
            $reservations ?? $this->reservationRepository(),
            $this->clock(),
            new FinePolicy(),
            new ReservationQueue()
        );
    }

    protected function placeReservation(): PlaceReservation
    {
        return new PlaceReservation(
            $this->transactions(),
            new PdoMemberRepository($this->pdo),
            new PdoBookRepository($this->pdo),
            $this->copyRepository(),
            $this->reservationRepository(),
            $this->clock()
        );
    }

    protected function cancelReservation(): CancelReservation
    {
        return new CancelReservation(
            $this->transactions(),
            $this->reservationRepository(),
            $this->copyRepository(),
            new ReservationQueue()
        );
    }

    protected function payFine(): PayFine
    {
        return new PayFine($this->transactions(), new PdoLoanRepository($this->pdo));
    }
}
