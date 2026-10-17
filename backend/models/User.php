<?php
declare(strict_types=1);

class User
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM users ORDER BY id DESC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function usernameTaken(string $username, int $excludeId = 0): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function emailTaken(string $email, int $excludeId = 0): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, name, email, password_hash, role, plan, status, joined_at)
             VALUES (:username, :name, :email, :password_hash, :role, :plan, :status, :joined_at)'
        );
        $stmt->execute([
            'username'      => $data['username'],
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password_hash' => $data['password_hash'],
            'role'          => $data['role'] ?? 'user',
            'plan'          => $data['plan'] ?? 'Free',
            'status'        => $data['status'] ?? 'active',
            'joined_at'     => $data['joined_at'] ?? date('Y-m-d'),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Updates the mutable profile fields; password_hash only changes when a non-null value is passed. */
    public static function update(int $id, array $data): bool
    {
        $fields = ['username', 'name', 'email', 'role', 'plan', 'status'];
        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $set[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }
        if (!empty($data['password_hash'])) {
            $set[] = 'password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }
        if (empty($set)) {
            return true;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = Database::pdo()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function countByStatus(string $status): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }
}
