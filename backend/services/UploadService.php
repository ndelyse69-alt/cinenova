<?php
/**
 * CineNova — Upload service.
 * ---------------------------------------------------------------
 * Handles the two upload types the Admin Panel supports: a locally
 * hosted video file (Admin → Upload Video) and a poster/cover image
 * (Admin → Movies, "Upload poster" instead of pasting a URL).
 * Uploaded files are written under the top-level uploads/ directory
 * (a sibling of frontend/ and backend/, never inside either), and
 * only ever referenced back to the browser as the public URL below —
 * the admin JS never needs or sees a filesystem path.
 */
declare(strict_types=1);

define('UPLOAD_ROOT_DIR', __DIR__ . '/../../uploads');
define('UPLOAD_VIDEO_DIR', UPLOAD_ROOT_DIR . '/videos');
define('UPLOAD_POSTER_DIR', UPLOAD_ROOT_DIR . '/posters');
$cnScriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$cnBackendPos = strpos($cnScriptName, '/backend/');
$cnBasePath = $cnBackendPos === false ? '' : substr($cnScriptName, 0, $cnBackendPos);
define('UPLOAD_VIDEO_URL_PREFIX', $cnBasePath . '/uploads/videos/');
define('UPLOAD_POSTER_URL_PREFIX', $cnBasePath . '/uploads/posters/');

define('UPLOAD_MAX_VIDEO_BYTES', 2 * 1024 * 1024 * 1024); // 2 GB
define('UPLOAD_MAX_POSTER_BYTES', 8 * 1024 * 1024);        // 8 MB
const UPLOAD_ALLOWED_VIDEO_EXT = ['mp4', 'webm', 'mkv', 'mov', 'm4v'];
const UPLOAD_ALLOWED_POSTER_EXT = ['jpg', 'jpeg', 'png', 'webp'];

/** @return array{ok:bool, url?:string, filename?:string, size?:int, duration?:?string, error?:string} */
function upload_handle_video(array $file): array
{
    if (!is_dir(UPLOAD_VIDEO_DIR)) {
        @mkdir(UPLOAD_VIDEO_DIR, 0755, true);
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    if ((int) ($file['size'] ?? 0) > UPLOAD_MAX_VIDEO_BYTES) {
        return ['ok' => false, 'error' => 'Video is larger than the 2GB limit.'];
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_VIDEO_EXT, true)) {
        return ['ok' => false, 'error' => 'Unsupported video format. Allowed: ' . implode(', ', UPLOAD_ALLOWED_VIDEO_EXT)];
    }

    $safeName = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = UPLOAD_VIDEO_DIR . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded video. Check backend/../uploads/videos is writable.'];
    }
    @chmod($destination, 0644);

    return [
        'ok'       => true,
        'url'      => UPLOAD_VIDEO_URL_PREFIX . $safeName,
        'filename' => $safeName,
        'size'     => filesize($destination) ?: 0,
        'duration' => upload_probe_video_duration($destination),
    ];
}

/** @return array{ok:bool, url?:string, filename?:string, error?:string} */
function upload_handle_poster(array $file): array
{
    if (!is_dir(UPLOAD_POSTER_DIR)) {
        @mkdir(UPLOAD_POSTER_DIR, 0755, true);
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    if ((int) ($file['size'] ?? 0) > UPLOAD_MAX_POSTER_BYTES) {
        return ['ok' => false, 'error' => 'Poster image is larger than the 8MB limit.'];
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_POSTER_EXT, true)) {
        return ['ok' => false, 'error' => 'Unsupported image format. Allowed: ' . implode(', ', UPLOAD_ALLOWED_POSTER_EXT)];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['ok' => false, 'error' => 'The uploaded file is not a valid image.'];
    }

    $safeName = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = UPLOAD_POSTER_DIR . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded poster.'];
    }
    @chmod($destination, 0644);

    return ['ok' => true, 'url' => UPLOAD_POSTER_URL_PREFIX . $safeName, 'filename' => $safeName];
}

/**
 * Resolve whatever the admin submitted for a poster (a pasted URL,
 * or a freshly-uploaded file already turned into a URL above) into
 * the single 'poster_url' value stored on the catalog entry.
 */
function upload_resolve_poster(?string $pastedUrl, ?string $uploadedUrl): ?string
{
    if (!empty($uploadedUrl)) {
        return $uploadedUrl;
    }
    $pastedUrl = trim((string) $pastedUrl);
    return $pastedUrl !== '' ? $pastedUrl : null;
}

/** Best-effort duration probe via ffprobe; null (shown as "—" in the UI) when ffprobe isn't installed. */
function upload_probe_video_duration(string $path): ?string
{
    if (!function_exists('shell_exec')) {
        return null;
    }
    $escaped = escapeshellarg($path);
    $output = @shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$escaped} 2>&1");
    if ($output === null) {
        return null;
    }
    $seconds = (float) trim($output);
    if ($seconds <= 0) {
        return null;
    }
    return upload_format_duration((int) round($seconds));
}

function upload_format_duration(int $totalSeconds): string
{
    $h = intdiv($totalSeconds, 3600);
    $m = intdiv($totalSeconds % 3600, 60);
    $s = $totalSeconds % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $s)
        : sprintf('%d:%02d', $m, $s);
}

function upload_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = (int) floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);
    return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL => 'The file only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server could not store the upload.',
        default => 'Upload failed. Please try again.',
    };
}
