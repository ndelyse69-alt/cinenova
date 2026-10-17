<?php
/**
 * CineNova API — visit tracking beacon.
 * ---------------------------------------------------------------
 * POST /backend/api/track.php  { path: '/frontend/pages/movies.html' }
 * Fired once per page load by frontend/js/common.js. Powers Admin →
 * Analytics / Visitors (see backend/services/AnalyticsService.php).
 * No auth required — this is public traffic tracking, same as the
 * old track_visit() calls at the top of every page controller.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

if (api_resolve_method() !== 'POST') {
    api_error('Method not allowed.', 405);
}

$path = (string) ($_POST['path'] ?? '/');
analytics_track_visit(substr($path, 0, 200));

api_json(['success' => true]);
