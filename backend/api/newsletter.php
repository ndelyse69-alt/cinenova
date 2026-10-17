<?php
/**
 * CineNova newsletter API.
 * POST -> subscribe a visitor email to CineNova movie updates.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

auth_start_session();
$method = api_resolve_method();

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}

api_require_csrf();

// Lightweight anti-bot honeypot. Real users never see this field.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    api_json(['success' => true, 'message' => 'Thanks! You are subscribed.']);
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('Please enter a valid email address.', 422);
}
if (strlen($email) > 190) {
    api_error('Email address is too long.', 422);
}

// Prevent repeated submissions from the same browser in a very short window.
$now = time();
$last = (int) ($_SESSION['newsletter_last_submit'] ?? 0);
if ($last > 0 && ($now - $last) < 3) {
    api_error('Please wait a moment and try again.', 429);
}
$_SESSION['newsletter_last_submit'] = $now;

try {
    $result = NewsletterService::subscribe($email);
    api_json([
        'success' => true,
        'data' => [
            'subscribed' => true,
            'already_subscribed' => !$result['created'],
        ],
        'message' => $result['created']
            ? 'You are subscribed to CineNova movie updates.'
            : 'This email is already subscribed to CineNova updates.',
    ]);
} catch (InvalidArgumentException $e) {
    api_error($e->getMessage(), 422);
} catch (Throwable $e) {
    api_error('Could not save your subscription. Please try again.', 500);
}
