<?php
require __DIR__ . '/includes/bootstrap.php';

$admin = Database::first("SELECT id FROM users WHERE role = 'admin' AND deleted_at IS NULL ORDER BY id LIMIT 1");
$_SESSION['user_id'] = (int)$admin['id'];
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'testtok';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
ob_start();
include __DIR__ . '/dashboard/admin/overview.php';
$html = (string)ob_get_clean();

$ok = true;
$chk = function (string $label, bool $cond, string $detail = '') use (&$ok) {
    if (!$cond) $ok = false;
    echo ($cond ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " - {$detail}" : '') . "\n";
};

$chk('no PHP errors', !preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', $html));
echo "KPI flagged = {$stats['flagged']}\n";
echo 'feed rows: ' . count($feed) . "\n";
foreach ($feed as $i => $f) {
    printf("  #%d [%s|%s] %s | %s | %+.1f%% | %s\n",
        $i, $f['kind'], $f['basis'], $f['product_name'], $f['where'], $f['dev'], $f['when']);
}

$kinds  = array_column($feed, 'kind');
$basises = array_column($feed, 'basis');
$cross  = array_values(array_filter($feed, fn($f) => $f['basis'] === 'vs market avg'));
$chk('KPI counts entries', is_int($stats['flagged']) && $stats['flagged'] >= count($feed));
$chk('cross-market rows present', count($cross) > 0, count($cross) . ' row(s)');
$chk('has a market HIGH (positive dev)', in_array(true, array_map(fn($f) => $f['dev'] > 0, $cross), false));
$chk('has a market LOW (negative dev)', in_array(true, array_map(fn($f) => $f['dev'] < 0, $cross), false));
$chk('listing rows still present', in_array('listing', $kinds, true));
$chk('feed sorted by |dev| desc', count($feed) < 2 ||
    abs($feed[0]['dev']) >= abs($feed[1]['dev']));
$chk('source tag in HTML', strpos($html, 'vs market avg') !== false);
$chk('subtitle mentions cross-market average', strpos($html, 'cross-market average') !== false);
$marketRows = array_values(array_filter($feed, fn($f) => $f['kind'] === 'market'));
$keys = array_map(fn($f) => $f['product_name'] . '|' . $f['where'], $marketRows);
$chk('dedup: at most one row per product+market among market kinds',
    count($keys) === count(array_unique($keys)), implode(', ', $keys));

echo $ok ? "ALL OK\n" : "FAILURES\n";
exit($ok ? 0 : 1);
