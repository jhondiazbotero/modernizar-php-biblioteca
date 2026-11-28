<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence;

use Library\Application\Port\MemberRepository;
use Library\Domain\MemberSnapshot;

final class PdoMemberRepository implements MemberRepository
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findForUpdate(int $id): ?MemberSnapshot
    {
        $statement = $this->pdo->prepare('SELECT id, nombre, activo FROM socios WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        return new MemberSnapshot((int) $row['id'], (string) $row['nombre'], (bool) $row['activo']);
    }

    public function documentTaken(string $document, int $exceptId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM socios WHERE documento = ? AND id <> ?');
        $statement->execute([$document, $exceptId]);

        return $statement->fetch() !== false;
    }

    public function add(string $document, string $name, string $email, string $phone, \DateTimeImmutable $registeredOn, bool $active): int
    {
        $statement = $this->pdo->prepare('INSERT INTO socios (documento, nombre, correo, telefono, fecha_alta, activo) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$document, $name, $email, $phone, $registeredOn->format('Y-m-d'), $active ? 1 : 0]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $document, string $name, string $email, string $phone, bool $active): void
    {
        $statement = $this->pdo->prepare('UPDATE socios SET documento = ?, nombre = ?, correo = ?, telefono = ?, activo = ? WHERE id = ?');
        $statement->execute([$document, $name, $email, $phone, $active ? 1 : 0, $id]);
    }
}
