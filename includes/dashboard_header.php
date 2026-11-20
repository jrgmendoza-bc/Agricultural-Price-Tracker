<?php
/**
 * dashboard_header.php - shared dashboard shell: <head>, the fixed glass
 * sidebar (navigation + notifications + account menu) and the opening of
 * <main>. There is no top bar: every topbar control lives in the sidebar.
 *
 * Pages must set $pageTitle / $activeSection BEFORE including this file,
 * and handle their own POST actions before any output is sent.
 */

// --- POST: mark every notification of the signed-in user as read -------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_notifications_read') {
    csrf_verify();
    Database::execute(
        "UPDATE notifications SET read_at = NOW()
          WHERE notifiable_id = ? AND read_at IS NULL",
        [Auth::id()]
    );
    redirect(($_SERVER['HTTP_REFERER'] ?? Auth::dashboardUrl((string)Auth::role())) . '#');
}

$authUser = Auth::user();
$pageTitle   = $pageTitle   ?? 'Dashboard';
$activeSection = $activeSection ?? '';

$notifCount = 0;
$notifItems = [];
try {
    $notifCount = (int)Database::scalar(
        "SELECT COUNT(*) FROM notifications WHERE notifiable_id = ? AND read_at IS NULL",
        [$authUser['id']]
    );
    $notifItems = Database::select(
        "SELECT id, type, data, read_at, created_at FROM notifications
          WHERE notifiable_id = ? ORDER BY created_at DESC LIMIT 8",
        [$authUser['id']]
    );
} catch (Throwable $e) {
    // notification feed optional
}

$roleLabel = ['admin' => 'Administrator', 'farmer' => 'Client · Farmer', 'buyer' => 'Buyer'][$authUser['role']] ?? $authUser['role'];
$flashes = pull_flashes();

function notif_text(array $n): string
{
    $data = json_decode($n['data'] ?? '{}', true);
    return is_array($data) && isset($data['message']) ? (string)$data['message'] : (string)($n['type'] ?? 'Notification');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body class="app-body">
<div class="app-overlay" id="appOverlay"></div>

<?php require __DIR__ . '/sidebar.php'; ?>

<!-- Mobile-only trigger: opens the fixed sidebar (its close button is inside) -->
<button class="side-fab" id="sidebarToggle" aria-label="Open menu">
  <span>☰</span>
  <span class="side-fab-title"><?= e($pageTitle) ?></span>
</button>

<div class="app-main">
  <main class="app-content">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>" data-auto-hide><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
