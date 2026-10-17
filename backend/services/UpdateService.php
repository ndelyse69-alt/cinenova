<?php
declare(strict_types=1);

/**
 * CineNova — public movie/update announcements.
 * Stored as a small JSON feed so admins can publish immediately without
 * changing the movie catalog schema. The public site reads only the latest
 * announcements; writes are protected by the admin middleware.
 */
class UpdateService
{
    private const FILE = 'updates.json';
    private const MAX_ITEMS = 100;

    public static function latest(int $limit = 5): array
    {
        $rows = store_read_json(self::FILE, []);
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        return array_slice($rows, 0, max(1, min($limit, 20)));
    }

    public static function add(string $message, ?string $movieSlug = null, ?string $movieTitle = null): array
    {
        $rows = store_read_json(self::FILE, []);
        $item = [
            'id' => store_next_id($rows),
            'message' => trim($message),
            'movie_slug' => $movieSlug,
            'movie_title' => $movieTitle,
            'created_at' => gmdate('c'),
        ];
        array_unshift($rows, $item);
        $rows = array_slice($rows, 0, self::MAX_ITEMS);
        if (!store_write_json(self::FILE, $rows)) {
            throw new RuntimeException('Could not save the update.');
        }

        // Email delivery is intentionally best-effort: a mail-server problem
        // must never roll back a movie/update that was already published.
        try {
            $item['delivery'] = NewsletterService::sendUpdate($item);
        } catch (Throwable $e) {
            $item['delivery'] = [
                'recipients' => 0,
                'sent' => 0,
                'failed' => 0,
                'mail_configured' => false,
                'error' => $e->getMessage(),
            ];
        }
        return $item;
    }
}
