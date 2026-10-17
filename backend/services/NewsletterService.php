<?php
/**
 * CineNova — newsletter subscriber storage and email delivery.
 * Subscribers are stored separately from login accounts so visitors can
 * opt in from the footer without creating an account.
 */
declare(strict_types=1);

class NewsletterService
{
    private const FILE = 'subscribers.json';
    private const MAX_SUBSCRIBERS = 10000;

    public static function subscribe(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }

        $rows = store_read_json(self::FILE, []);
        foreach ($rows as $row) {
            if (strcasecmp((string) ($row['email'] ?? ''), $email) === 0 && ($row['status'] ?? 'active') === 'active') {
                return ['created' => false, 'subscriber' => $row];
            }
        }

        $item = [
            'id' => store_next_id($rows),
            'email' => $email,
            'status' => 'active',
            'subscribed_at' => gmdate('c'),
            'last_sent_at' => null,
        ];

        array_unshift($rows, $item);
        $rows = array_slice($rows, 0, self::MAX_SUBSCRIBERS);
        if (!store_write_json(self::FILE, $rows)) {
            throw new RuntimeException('Could not save your subscription.');
        }

        return ['created' => true, 'subscriber' => $item];
    }

    public static function activeSubscribers(): array
    {
        $rows = store_read_json(self::FILE, []);
        $active = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if (($row['status'] ?? 'active') === 'active' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $active[] = $row;
            }
        }
        return $active;
    }

    public static function sendUpdate(array $update): array
    {
        $subscribers = self::activeSubscribers();
        $sent = 0;
        $failed = 0;
        $now = gmdate('c');
        $subject = 'New Movie Update — CineNova';
        $message = trim((string) ($update['message'] ?? 'New update from CineNova.'));
        $movieTitle = trim((string) ($update['movie_title'] ?? ''));

        $body = "CineNova — New Movie Update\n\n";
        if ($movieTitle !== '') {
            $body .= "Movie: {$movieTitle}\n\n";
        }
        $body .= $message . "\n\n";
        $body .= "Stay tuned for more movies and updates from CineNova.\n";

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . self::fromAddress(),
            'Reply-To: ' . self::fromAddress(),
            'X-Mailer: CineNova Newsletter',
        ];
        $headerString = implode("\r\n", $headers);

        foreach ($subscribers as $subscriber) {
            $email = (string) $subscriber['email'];
            // One email per subscriber keeps recipient addresses private.
            $ok = @mail($email, $subject, $body, $headerString);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent > 0) {
            self::markSent($subscribers, $now);
        }

        return [
            'recipients' => count($subscribers),
            'sent' => $sent,
            'failed' => $failed,
            'mail_configured' => self::mailConfigured(),
        ];
    }

    private static function markSent(array $subscribers, string $timestamp): void
    {
        $rows = store_read_json(self::FILE, []);
        $emails = [];
        foreach ($subscribers as $subscriber) {
            $emails[strtolower((string) $subscriber['email'])] = true;
        }
        foreach ($rows as &$row) {
            $email = strtolower((string) ($row['email'] ?? ''));
            if (isset($emails[$email])) {
                $row['last_sent_at'] = $timestamp;
            }
        }
        unset($row);
        store_write_json(self::FILE, $rows);
    }

    private static function fromAddress(): string
    {
        $configured = trim((string) env('MAIL_FROM', ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }
        return 'noreply@cinenova.example';
    }

    private static function mailConfigured(): bool
    {
        return trim((string) env('MAIL_FROM', '')) !== '';
    }
}
