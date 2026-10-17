<?php
declare(strict_types=1);

class Category
{
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Number of movies currently assigned to this category (used to guard deletes). */
    public static function movieCount(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM movies WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public static function create(string $name, string $slug, string $description): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)');
        $stmt->execute([$name, $slug, $description]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $name, string $slug, string $description): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?');
        return $stmt->execute([$name, $slug, $description, $id]);
    }

    /**
     * Delete a category. Refuses (returns false) if any movie still
     * references it — the fk_movies_category FK is ON DELETE RESTRICT,
     * so this check exists to give a clean error instead of a raw
     * SQL exception bubbling up.
     */
    public static function delete(int $id): bool
    {
        if (self::movieCount($id) > 0) {
            return false;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM categories WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
