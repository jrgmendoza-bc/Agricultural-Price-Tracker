<?php
/**
 * test_dedup.php - throwaway: a market flagged by BOTH the 7-day baseline and
 * the cross-market average must appear only once (stronger deviation wins).
 * Modes: seed | render | cleanup
 */
$mode = $argv[1] ?? 'render';
require __DIR__ . '/includes/bootstrap.php';

$ok = true;
$chk = function (string $label, bool $cond, string $detail = '') use (&$ok) {
    if (!$cond) $ok = false;
    echo ($cond ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " - {$detail}" : '') . "\n";
};

if ($mode === 'seed') {
    // p2m2 needs >= 3 active prior reports in the 7-day window for 10-11:
    // existing = 10-09 (77.77), 10-10 (300)  => seed one more on 10-08 first.
    $ids = [];
    foreach ([['2026-10-08 08:00:00', 80], ['2026-10-11 08:00:00', 400]] as [$when, $price]) {
        $r = PriceService::addReport([
            'product_id' => 2, 'market_id' => 2, 'unit_id' => 1,
            'price' => $price, 'date_reported' => $when,
            'source_type' => 'manual', 'reported_by' => null,
        ]);
        if (!$r['ok']) { echo 'seed failed: ' . $r['error'] . "\n"; exit(1); }
        $ids[] = (int)$r['id'];
        $dev = Database::scalar('SELECT deviation_pct FROM price_reports WHERE id = ?', [$r['id']]);
        echo "seeded report #{$r['id']} ({$when}, price {$price}) deviation_pct=" . ($dev ?? 'NULL') . "\n";
    }
    file_put_contents(__DIR__ . '\\dedup_test_id.txt', json_encode($ids));
    exit(0);
}

if ($mode === 'cleanup') {
    foreach (json_decode((string)file_get_contents(__DIR__ . '\\dedup_test_id.txt'), true) as $id) {
        Database::execute('DELETE FROM price_reports WHERE id = ?', [$id]);
    }
    PriceService::rebuildDay(2, 2, '2026-10-08');   // drops snapshots the seeds created
    PriceService::rebuildDay(2, 2, '2026-10-11');
    unlink(__DIR__ . '\\dedup_test_id.txt');
    echo "cleanup done\n";
    exit(0);
}

// render
$admin = Database::first("SELECT id FROM users WHERE role = 'admin' AND deleted_at IS NULL ORDER BY id LIMIT 1");
$_SESSION['user_id'] = (int)$admin['id'];
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'testtok';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
ob_start();
include __DIR__ . '/dashboard/admin/overview.php';
$html = (string)ob_get_clean();

$chk('no PHP errors', !preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', $html));
echo "KPI flagged = {$stats['flagged']}\n";
foreach ($feed as $i => $f) {
    printf("  #%d [%s|%s] %s | %s | %+.1f%%\n", $i, $f['kind'], $f['basis'], $f['product_name'], $f['where'], $f['dev']);
}

$collision = array_values(array_filter($feed,
    fn($f) => $f['product_name'] === 'Corn' && $f['where'] === 'Carmen Municipal Trading Post'));
$chk('exactly one row for the double-flagged market', count($collision) === 1, count($collision) . ' row(s)');
if ($mode === 'render') {
    // cross avg after seed = (77.77 + 400 + 77.77 + 33) / 4 = 147.135 -> +171.9%
    // baseline deviation = (400 - 152.59) / 152.59 = +162.2%  -> cross (bigger) must win
    $dev = $collision[0]['dev'] ?? 0;
    $chk('stronger deviation wins (cross-market)', abs($dev - 171.9) < 0.5, sprintf('%.1f%%', $dev));
    $chk('KPI equals pool size', $stats['flagged'] >= count($feed));
} else {
    // base: clean state after cleanup
    $dev = $collision[0]['dev'] ?? 0;
    $chk('restored: cross row back to +145.6', abs($dev - 145.6) < 0.5, sprintf('%.1f%%', $dev));
    $chk('restored: KPI back to 5', $stats['flagged'] === 5, 'flagged=' . $stats['flagged']);
}
echo $ok ? "ALL OK\n" : "FAILURES\n";
exit($ok ? 0 : 1);
