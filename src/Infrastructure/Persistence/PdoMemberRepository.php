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
}
