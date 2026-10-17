<?php
/**
 * CineNova — JSON → MySQL data migration
 * ---------------------------------------------------------------
 * Run once from the CLI:  php backend/database/migrate.php
 * Reads the existing backend/data/*.json files (the app's original
 * flat-file store) and populates the posterg database with the
 * same data. Idempotent — safe to re-run; it clears the relevant
 * tables first each time rather than accumulating duplicates.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$dataDir = __DIR__ . '/../data';
$pdo = Database::pdo();

function read_json(string $path, array $default = []): array
{
    if (!is_file($path)) return $default;
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}

echo "== CineNova JSON -> MySQL migration ==\n";

// ---------------------------------------------------------
// Categories
// ---------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE movie_sources');
$pdo->exec('TRUNCATE TABLE movies');
$pdo->exec('TRUNCATE TABLE categories');
$pdo->exec('TRUNCATE TABLE countries');
$pdo->exec('TRUNCATE TABLE users');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$categoryIdByName = [];
foreach (read_json("$dataDir/categories.json") as $cat) {
    $id = Category::create($cat['name'], $cat['slug'] ?? admin_slug($cat['name']), $cat['description'] ?? '');
    $categoryIdByName[$cat['name']] = $id;
}
echo 'Categories migrated: ' . count($categoryIdByName) . "\n";

// ---------------------------------------------------------
// Countries
// ---------------------------------------------------------
$countryIdByName = [];
foreach (read_json("$dataDir/countries.json") as $c) {
    $id = Country::create($c['name'], $c['code'], $c['status'] ?? 'active');
    $countryIdByName[$c['name']] = $id;
}
echo 'Countries migrated: ' . count($countryIdByName) . "\n";

// ---------------------------------------------------------
// Users
// ---------------------------------------------------------
$userCount = 0;
foreach (read_json("$dataDir/users.json") as $u) {
    User::create([
        'username'      => $u['username'],
        'name'          => $u['name'],
        'email'         => $u['email'],
        'password_hash' => $u['password_hash'],
        'role'          => $u['role'] ?? 'user',
        'plan'          => $u['plan'] ?? 'Free',
        'status'        => $u['status'] ?? 'active',
        'joined_at'     => $u['joined'] ?? date('Y-m-d'),
    ]);
    $userCount++;
}
echo "Users migrated: $userCount\n";

// ---------------------------------------------------------
// Movies + movie_sources
// ---------------------------------------------------------
$movieCount = 0;
$sourceCount = 0;
foreach (read_json("$dataDir/movies-catalog.json") as $slug => $m) {
    $categoryId = $categoryIdByName[$m['genre'] ?? ''] ?? null;
    $countryId = $countryIdByName[$m['country'] ?? ''] ?? null;

    $movieId = Movie::create([
        'slug'        => $slug,
        'title'       => $m['title'],
        'tmdb_id'     => $m['tmdb_id'] ?? null,
        'source'      => $m['source'] ?? 'tmdb',
        'media_type'  => $m['media_type'] ?? 'movie',
        'category_id' => $categoryId,
        'country_id'  => $countryId,
        'year'        => !empty($m['year']) ? (int) $m['year'] : null,
        'description' => $m['description'] ?? null,
        'poster_url'  => $m['poster_url'] ?? null,
        'rating'      => $m['rating'] ?? 0,
    ]);

    $sources = [];
    foreach ($m['episodes'] ?? [] as $label => $url) {
        $type = 'embed';
        if (str_contains($url, 'uploads/videos/')) $type = 'upload';
        elseif (str_contains($url, 'vidsrc')) $type = 'vidsrc';
        $sources[] = ['label' => $label, 'url' => $url, 'source_type' => $type];
        $sourceCount++;
    }
    Movie::replaceSources($movieId, $sources);
    $movieCount++;
}
echo "Movies migrated: $movieCount\n";
echo "Movie sources migrated: $sourceCount\n";

// ---------------------------------------------------------
// Settings
// ---------------------------------------------------------
$settings = read_json("$dataDir/settings.json");
if (!empty($settings)) {
    Setting::update([
        'site_name'        => $settings['site_name'] ?? 'CineNova',
        'site_tagline'     => $settings['site_tagline'] ?? null,
        'site_description' => $settings['site_description'] ?? null,
        'support_email'    => $settings['support_email'] ?? null,
        'maintenance_mode' => $settings['maintenance_mode'] ?? false,
        'social_twitter'   => $settings['social']['twitter'] ?? null,
        'social_facebook'  => $settings['social']['facebook'] ?? null,
        'social_instagram' => $settings['social']['instagram'] ?? null,
    ]);
    echo "Settings migrated.\n";
}

echo "== Migration complete ==\n";

function admin_slug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}
