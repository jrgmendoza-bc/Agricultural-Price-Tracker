<?php
/**
 * bootstrap.php - application entry point for every page.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/price_service.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/uploads.php';

Auth::start();

// Keep PHP clocks in line with MySQL's NOW() (SYSTEM = +08:00) so that
// throttling windows, time_ago() and stamping of created_at agree.
date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'AgriPriceTrack');
define('APP_LOCALE', 'Carmen, North Cotabato');

/** Public URL of the app for links inside emails (empty = auto-detect from the request).
 *  e.g. define('APP_URL', 'https://carmenagri.basedev.online'); */
if (!defined('APP_URL')) {
    define('APP_URL', '');
}

/** Reports beyond this percent deviation from the 7-day baseline are flagged. */
define('UNUSUAL_PRICE_PCT', 20);

/** Growth / raising stage vocabularies shared by every dashboard page. */
define('CROP_STAGES', ['planted', 'growing', 'maturing', 'ready_for_harvest', 'harvested', 'available_for_pickup']);
define('LIVESTOCK_STAGES', ['raising', 'ready_for_sale', 'available_for_pickup']);
