<?php
declare(strict_types=1);

class Setting
{
    /** Always exactly one row (id=1), created on first read if missing. */
    public static function get(): array
    {
        $pdo = Database::pdo();
        $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
        if ($row) {
            return $row;
        }
        $pdo->exec("INSERT INTO settings (id, site_name) VALUES (1, 'CineNova')");
        return $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    }

    public static function update(array $data): bool
    {
        self::get(); // ensure the row exists first
        $stmt = Database::pdo()->prepare(
            'UPDATE settings SET site_name = :site_name, site_tagline = :site_tagline,
             site_description = :site_description, support_email = :support_email,
             maintenance_mode = :maintenance_mode, social_twitter = :social_twitter,
             social_facebook = :social_facebook, social_instagram = :social_instagram
             WHERE id = 1'
        );
        return $stmt->execute([
            'site_name'        => $data['site_name'],
            'site_tagline'     => $data['site_tagline'] ?? null,
            'site_description' => $data['site_description'] ?? null,
            'support_email'    => $data['support_email'] ?? null,
            'maintenance_mode' => !empty($data['maintenance_mode']) ? 1 : 0,
            'social_twitter'   => $data['social_twitter'] ?? null,
            'social_facebook'  => $data['social_facebook'] ?? null,
            'social_instagram' => $data['social_instagram'] ?? null,
        ]);
    }
}
