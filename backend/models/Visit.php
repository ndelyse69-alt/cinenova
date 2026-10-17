<?php
declare(strict_types=1);

class Visit
{
    public static function record(string $page, string $queryString, string $ipHash, string $userAgent, string $referrer): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO visits (page, query_string, ip_hash, user_agent, referrer) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$page, $queryString, $ipHash, $userAgent, $referrer]);
    }

    public static function recent(int $limit = 200, string $pagePrefix = ''): array
    {
        $sql = 'SELECT * FROM visits';
        $params = [];
        if ($pagePrefix !== '') {
            $sql .= ' WHERE page LIKE ?';
            $params[] = $pagePrefix . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM visits')->fetchColumn();
    }

    public static function countToday(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM visits WHERE DATE(visited_at) = CURDATE()')->fetchColumn();
    }

    public static function uniqueVisitorsToday(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(DISTINCT ip_hash) FROM visits WHERE DATE(visited_at) = CURDATE()')->fetchColumn();
    }

    public static function uniqueVisitorsAll(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(DISTINCT ip_hash) FROM visits')->fetchColumn();
    }

    public static function trend(int $days = 14): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT DATE(visited_at) AS day, COUNT(*) AS total FROM visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(visited_at) ORDER BY day ASC'
        );
        $stmt->execute([$days - 1]);
        return $stmt->fetchAll();
    }

    public static function popularPages(int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT SUBSTRING_INDEX(page, ':', 1) AS page_group, COUNT(*) AS total
             FROM visits GROUP BY page_group ORDER BY total DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function truncate(): void
    {
        Database::pdo()->exec('TRUNCATE TABLE visits');
    }
}
