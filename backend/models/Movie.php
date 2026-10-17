<?php
declare(strict_types=1);

class Movie
{
    private const SELECT_WITH_JOINS = '
        SELECT m.*, c.name AS category_name, co.name AS country_name
        FROM movies m
        LEFT JOIN categories c ON c.id = m.category_id
        LEFT JOIN countries co ON co.id = m.country_id
    ';

    public static function all(): array
    {
        $stmt = Database::pdo()->query(self::SELECT_WITH_JOINS . ' ORDER BY m.id DESC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT_WITH_JOINS . ' WHERE m.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT_WITH_JOINS . ' WHERE m.slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The (tmdb_id, media_type) pair is the same "reliable unique id" the rest of the app already keys watch.php on. */
    public static function findByTmdbId(int $tmdbId, string $mediaType): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT_WITH_JOINS . ' WHERE m.tmdb_id = ? AND m.media_type = ?');
        $stmt->execute([$tmdbId, $mediaType]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function slugTaken(string $slug, int $excludeId = 0): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM movies WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function searchByTitle(string $query, ?string $mediaType = null, int $limit = 20): array
    {
        $sql = self::SELECT_WITH_JOINS . ' WHERE m.title LIKE :q';
        $params = ['q' => '%' . $query . '%'];
        if ($mediaType !== null) {
            $sql .= ' AND m.media_type = :type';
            $params['type'] = $mediaType;
        }
        $sql .= ' ORDER BY m.title ASC LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** All source rows (embed/upload/vidsrc) for one movie, in display order. */
    public static function sources(int $movieId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM movie_sources WHERE movie_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$movieId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO movies (slug, title, tmdb_id, source, media_type, category_id, country_id, year, description, poster_url, rating)
             VALUES (:slug, :title, :tmdb_id, :source, :media_type, :category_id, :country_id, :year, :description, :poster_url, :rating)'
        );
        $stmt->execute([
            'slug'        => $data['slug'],
            'title'       => $data['title'],
            'tmdb_id'     => ($data['tmdb_id'] ?? null) ?: null,
            'source'      => $data['source'] ?? 'tmdb',
            'media_type'  => $data['media_type'] ?? 'movie',
            'category_id' => ($data['category_id'] ?? null) ?: null,
            'country_id'  => ($data['country_id'] ?? null) ?: null,
            'year'        => ($data['year'] ?? null) ?: null,
            'description' => $data['description'] ?? null,
            'poster_url'  => $data['poster_url'] ?? null,
            'rating'      => $data['rating'] ?? 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE movies SET slug = :slug, title = :title, tmdb_id = :tmdb_id, source = :source,
             media_type = :media_type, category_id = :category_id, country_id = :country_id,
             year = :year, description = :description, poster_url = :poster_url, rating = :rating
             WHERE id = :id'
        );
        return $stmt->execute([
            'id'          => $id,
            'slug'        => $data['slug'],
            'title'       => $data['title'],
            'tmdb_id'     => ($data['tmdb_id'] ?? null) ?: null,
            'source'      => $data['source'] ?? 'tmdb',
            'media_type'  => $data['media_type'] ?? 'movie',
            'category_id' => ($data['category_id'] ?? null) ?: null,
            'country_id'  => ($data['country_id'] ?? null) ?: null,
            'year'        => ($data['year'] ?? null) ?: null,
            'description' => $data['description'] ?? null,
            'poster_url'  => $data['poster_url'] ?? null,
            'rating'      => $data['rating'] ?? 0,
        ]);
    }

    /** Deleting a movie cascades to its movie_sources rows automatically (FK ON DELETE CASCADE). */
    public static function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM movies WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Replace all of a movie's sources in one go — simplest correct semantics for an admin "save" form. */
    public static function replaceSources(int $movieId, array $sources): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM movie_sources WHERE movie_id = ?')->execute([$movieId]);
        $stmt = $pdo->prepare(
            'INSERT INTO movie_sources (movie_id, label, url, source_type, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        foreach (array_values($sources) as $i => $source) {
            $stmt->execute([$movieId, $source['label'], $source['url'], $source['source_type'] ?? 'embed', $i]);
        }
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM movies')->fetchColumn();
    }

    public static function countByMediaType(string $mediaType): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM movies WHERE media_type = ?');
        $stmt->execute([$mediaType]);
        return (int) $stmt->fetchColumn();
    }
}
