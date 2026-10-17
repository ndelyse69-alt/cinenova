<?php
declare(strict_types=1);

class Country
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM countries ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM countries WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM countries WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $code, string $status): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO countries (name, code, status) VALUES (?, ?, ?)');
        $stmt->execute([$name, $code, $status]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $name, string $code, string $status): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE countries SET name = ?, code = ?, status = ? WHERE id = ?');
        return $stmt->execute([$name, $code, $status, $id]);
    }

    /** Movies referencing this country get country_id set to NULL (ON DELETE SET NULL) — always safe to delete. */
    public static function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM countries WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
