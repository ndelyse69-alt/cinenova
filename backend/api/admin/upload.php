<?php
/**
 * CineNova Admin API — standalone upload endpoint.
 * ---------------------------------------------------------------
 * POST /backend/api/admin/upload.php  kind=video  file=<video>
 * POST /backend/api/admin/upload.php  kind=poster file=<image>
 *
 * Used by Admin → Upload Video for a quick drag-and-drop upload that
 * returns a URL to paste into the Movies form's episode list —
 * independent of admin/movies.php's own inline upload fields, for
 * when the admin wants to upload first and attach it to a title
 * (new or existing) afterward.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

api_require_admin();

if (api_resolve_method() !== 'POST') {
    api_error('Method not allowed.', 405);
}
api_require_csrf();

$kind = (string) ($_POST['kind'] ?? 'video');
if (empty($_FILES['file'])) {
    api_error('No file was uploaded.', 422);
}

$result = $kind === 'poster'
    ? upload_handle_poster($_FILES['file'])
    : upload_handle_video($_FILES['file']);

if (!$result['ok']) {
    api_error($result['error'], 422);
}

if ($kind !== 'poster' && isset($result['size'])) {
    $result['size_formatted'] = upload_format_bytes($result['size']);
}

api_json(['success' => true, 'data' => $result, 'message' => 'Upload complete.']);
