<?php
/**
 * CineNova — public update feed.
 * GET  -> latest movie/site updates for visitors.
 * POST -> admin-only publish action.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$method = api_resolve_method();

if ($method === 'GET') {
    $limit = (int) ($_GET['limit'] ?? 5);
    api_json(['success' => true, 'data' => ['updates' => UpdateService::latest($limit)]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}

api_require_admin();
api_require_csrf();

$message = trim((string) ($_POST['message'] ?? ''));
if ($message === '') {
    api_error('Please enter an update message.', 422);
}
if (mb_strlen($message) > 300) {
    api_error('Update message must be 300 characters or fewer.', 422);
}

try {
    $item = UpdateService::add($message);
    api_json(['success' => true, 'data' => ['update' => $item], 'message' => 'Update published and email delivery processed.']);
} catch (Throwable $e) {
    api_error('Could not save the update.', 500);
}
