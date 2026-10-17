<?php
/**
 * CineNova — backend bootstrap
 * Loads .env, the Database connection, every Model class, then every
 * service module the rest of the backend depends on. Any API/
 * controller entry point requires this once, near the top, before
 * calling env(), a Model, or a service function.
 */
declare(strict_types=1);

require_once __DIR__ . '/Env.php';
load_env(__DIR__ . '/../.env');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Country.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Movie.php';
require_once __DIR__ . '/../models/Setting.php';
require_once __DIR__ . '/../models/Visit.php';

require_once __DIR__ . '/../services/TmdbService.php';
require_once __DIR__ . '/../services/CatalogService.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/AnalyticsService.php';
require_once __DIR__ . '/../services/JsonStoreService.php';
require_once __DIR__ . '/../services/UpdateService.php';
require_once __DIR__ . '/../services/NewsletterService.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// ============================================================
// SITE-WIDE CONSTANTS
// ============================================================
define('SITE_NAME', 'CineNova');
define('SITE_TAGLINE', 'Stream the Future of Entertainment');
define('SITE_DESCRIPTION', 'CineNova is a premium streaming destination for movies and series — trending titles, curated picks, and a library that keeps growing.');
