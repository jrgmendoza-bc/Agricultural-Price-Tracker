<?php
/**
 * dashboard/admin/overview.php - Administrator overview: KPI cards,
 * 30-day report trend, market/product distributions, registration trend
 * and the unusual-price feed.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

$stats = [
    'users'    => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL"),
    'farmers'  => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE role='farmer' AND deleted_at IS NULL"),
    'buyers'   => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE role='buyer' AND deleted_at IS NULL"),
    'pending'  => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE role IN ('buyer','farmer') AND verification_status='pending' AND deleted_at IS NULL"),
    'products' => (int)Database::scalar("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL"),
    'markets'  => (int)Database::scalar("SELECT COUNT(*) FROM markets WHERE deleted_at IS NULL"),
    'reports'  => (int)Database::scalar("SELECT COUNT(*) FROM price_reports WHERE deleted_at IS NULL"),
    'flagged'  => (int)Database::scalar("SELECT COUNT(*) FROM price_reports WHERE deleted_at IS NULL AND deviation_pct IS NOT NULL AND ABS(deviation_pct) > " . UNUSUAL_PRICE_PCT),
    'listings' => (int)Database::scalar("SELECT COUNT(*) FROM crops WHERE deleted_at IS NULL"),
];

/* Same palette/order as the compare page: markets sorted by name. */
$palette = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2'];
$marketColor = [];
foreach (Database::select("SELECT market_name FROM markets WHERE deleted_at IS NULL ORDER BY market_name") as $i => $m) {
    $marketColor[$m['market_name']] = $palette[$i % count($palette)];
}

/* --- Price report trend: one bucket per day, last 30 days --- */
$dayRows = Database::select(
    "SELECT DATE(date_reported) AS d, COUNT(*) AS n
       FROM price_reports
      WHERE deleted_at IS NULL AND date_reported >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
      GROUP BY DATE(date_reported)"
);
$dayMap = [];
foreach ($dayRows as $d) {
    $dayMap[$d['d']] = (int)$d['n'];
}
$trendDays  = [];
$maxDay     = 0;
for ($i = 29; $i >= 0; $i--) {
    $dt = date('Y-m-d', strtotime("-{$i} days"));
    $n  = $dayMap[$dt] ?? 0;
    $maxDay = max($maxDay, $n);
    $trendDays[] = ['label' => date('M j', strtotime($dt)), 'n' => $n];
}
$trendTotal = array_sum(array_column($trendDays, 'n'));

/* --- Reports by market (active markets, including empty ones) --- */
$mktRows = Database::select(
    "SELECT m.market_name, m.id, COALESCE(r.n, 0) AS n
       FROM markets m
       LEFT JOIN (SELECT market_id, COUNT(*) AS n
                    FROM price_reports WHERE deleted_at IS NULL GROUP BY market_id) r
              ON r.market_id = m.id
      WHERE m.deleted_at IS NULL
      ORDER BY n DESC, m.market_name"
);
$mktTotal = 0;
foreach ($mktRows as $m) {
    $mktTotal += (int)$m['n'];
}

/* --- Top products by report count --- */
$topProducts = Database::select(
    "SELECT p.product_name, u.code AS unit_code, COUNT(*) AS n
       FROM price_reports r
       JOIN products p ON p.id = r.product_id
       JOIN units u ON u.id = p.base_unit_id
      WHERE r.deleted_at IS NULL
      GROUP BY p.id, p.product_name, u.code
      ORDER BY n DESC, p.product_name
      LIMIT 5"
);
$topTotal = 0;
foreach ($topProducts as $t) {
    $topTotal += (int)$t['n'];
}

/* --- New registrations: one bucket per month, last 6 months --- */
$months = [];
$firstYm = date('Y-m', strtotime(date('Y-m-01') . ' -5 months'));
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime(date('Y-m-01') . " -{$i} months"));
    $months[$ym] = ['label' => date('M Y', strtotime($ym . '-01')), 'n' => 0];
}
foreach (Database::select(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n
       FROM users
      WHERE created_at >= ?
      GROUP BY DATE_FORMAT(created_at, '%Y-%m')",
    [$firstYm . '-01']
) as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['n'] = (int)$row['n'];
    }
}
$maxMonth = 0;
foreach ($months as $m) {
    $maxMonth = max($maxMonth, $m['n']);
}
$userTrendTotal = array_sum(array_column($months, 'n'));

/* --- Recent unusual prices: three bases, one feed --- */

// A) Market reports far from their own market's 7-day baseline
$flaggedRecent = Database::select(
    "SELECT r.product_id, r.market_id, r.normalized_price, r.deviation_pct, r.date_reported,
            p.product_name, u.code AS unit_code, m.market_name
       FROM price_reports r
       JOIN products p ON p.id = r.product_id
       JOIN units u ON u.id = p.base_unit_id
       JOIN markets m ON m.id = r.market_id
      WHERE r.deleted_at IS NULL AND r.deviation_pct IS NOT NULL
        AND ABS(r.deviation_pct) > " . UNUSUAL_PRICE_PCT . "
      ORDER BY ABS(r.deviation_pct) DESC
      LIMIT 20"
);

// B) Market lows & highs: each market's LATEST price vs the average of the
//    other markets' latest prices for the same product (cross-market check).
$latestPerMarket = [];
foreach (Database::select(
    "SELECT r.product_id, r.market_id, r.normalized_price, r.date_reported,
            p.product_name, u.code AS unit_code, m.market_name
       FROM price_reports r
       JOIN products p ON p.id = r.product_id
       JOIN units u ON u.id = p.base_unit_id
       JOIN markets m ON m.id = r.market_id
      WHERE r.deleted_at IS NULL
      ORDER BY r.date_reported ASC, r.id ASC"
) as $r) {
    $latestPerMarket[$r['product_id'] . '-' . $r['market_id']] = $r;   // last wins = latest
}
$byProduct = [];
foreach ($latestPerMarket as $r) {
    $byProduct[$r['product_id']][] = $r;
}
$crossDevs = [];
foreach ($byProduct as $list) {
    if (count($list) < 2) {
        continue;                                   // one market cannot be unusual vs itself
    }
    $sum = 0.0;
    foreach ($list as $r) {
        $sum += (float)$r['normalized_price'];
    }
    $avg = $sum / count($list);
    if ($avg <= 0) {
        continue;
    }
    foreach ($list as $r) {
        $dev = ((float)$r['normalized_price'] - $avg) / $avg * 100;
        if (abs($dev) > UNUSUAL_PRICE_PCT) {
            $crossDevs[] = [
                'kind'         => 'market',
                'basis'        => 'vs market avg',
                'product_id'   => (int)$r['product_id'],
                'market_id'    => (int)$r['market_id'],
                'product_name' => $r['product_name'],
                'unit_code'    => $r['unit_code'],
                'where'        => $r['market_name'],
                'price'        => (float)$r['normalized_price'],
                'dev'          => $dev,
                'when'         => $r['date_reported'],
            ];
        }
    }
}

// C) Active farmer listings whose asking price sits far from the latest market
//    price — the same comparison the listings/marketplace pages already show.
$listingDevs = [];
$activeListings = Database::select(
    "SELECT c.product_id, c.normalized_asking_price, c.created_at,
            p.product_name, bu.code AS unit_code,
            COALESCE(NULLIF(CONCAT_WS(' ', f.first_name, f.last_name), ''), f.username) AS farmer_name
       FROM crops c
       JOIN products p ON p.id = c.product_id
       JOIN units bu   ON bu.id = p.base_unit_id
       JOIN users f    ON f.id = c.farmer_id
      WHERE c.deleted_at IS NULL AND f.deleted_at IS NULL
      ORDER BY c.created_at DESC"
);
$rateCache = [];
foreach ($activeListings as $l) {
    $pid = (int)$l['product_id'];
    if (!array_key_exists($pid, $rateCache)) {
        $rateCache[$pid] = PriceService::latestMarketPrice($pid);
    }
    $rate = $rateCache[$pid];
    if ($rate === null || $rate <= 0 || $l['normalized_asking_price'] === null) {
        continue;
    }
    $dev = ((float)$l['normalized_asking_price'] - $rate) / $rate * 100;
    if (abs($dev) > UNUSUAL_PRICE_PCT) {
        $listingDevs[] = [
            'kind'         => 'listing',
            'basis'        => 'listing',
            'product_id'   => (int)$l['product_id'],
            'market_id'    => null,
            'product_name' => $l['product_name'],
            'unit_code'    => $l['unit_code'],
            'where'        => $l['farmer_name'],
            'price'        => (float)$l['normalized_asking_price'],
            'dev'          => $dev,
            'when'         => $l['created_at'],
        ];
    }
}

/* Merge all three bases. A market flagged both ways appears once — the
 * stronger deviation wins. Biggest deviations first, then recency. */
$entries = [];
$marketEntry = [];
$addEntry = static function (array $e) use (&$entries, &$marketEntry): void {
    if ($e['kind'] !== 'listing') {
        $key = $e['product_id'] . '|' . $e['market_id'];
        if (isset($marketEntry[$key])) {
            $i = $marketEntry[$key];
            if (abs($e['dev']) <= abs($entries[$i]['dev'])) {
                return;
            }
            $entries[$i] = $e;
            return;
        }
        $marketEntry[$key] = count($entries);
    }
    $entries[] = $e;
};
foreach ($flaggedRecent as $f) {
    $addEntry([
        'kind'         => 'market',
        'basis'        => 'vs baseline',
        'product_id'   => (int)$f['product_id'],
        'market_id'    => (int)$f['market_id'],
        'product_name' => $f['product_name'],
        'unit_code'    => $f['unit_code'],
        'where'        => $f['market_name'],
        'price'        => (float)$f['normalized_price'],
        'dev'          => (float)$f['deviation_pct'],
        'when'         => $f['date_reported'],
    ]);
}
foreach ($crossDevs as $e) {
    $addEntry($e);
}
foreach ($listingDevs as $e) {
    $addEntry($e);
}
$stats['flagged'] = count($entries);

usort($entries, static fn($a, $b) =>
    (abs($b['dev']) <=> abs($a['dev'])) ?: (strtotime($b['when']) <=> strtotime($a['when'])));
$feed = array_slice($entries, 0, 5);

$pageTitle     = 'Overview';
$activeSection = 'overview';
require __DIR__ . '/../../includes/dashboard_header.php';

/* Bar height helper: share of the window peak, never invisible. */
$trendH = static function (int $n, int $max): int {
    return $n <= 0 ? 0 : max(6, (int)round($n / max(1, $max) * 100));
};
?>

<!-- ======================= OVERVIEW ======================= -->
<section class="section" id="overview">
  <div class="section-head">
    <h2 class="section-title">📊 Overview</h2>
    <span class="small muted">Platform activity at a glance · <?= e(date('M j, Y · g:i A')) ?></span>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">👥</span><div><div class="stat-value"><?= $stats['users'] ?></div><div class="stat-label">Total users</div></div></div>
    <div class="stat-card"><span class="stat-icon">🌾</span><div><div class="stat-value"><?= $stats['farmers'] ?></div><div class="stat-label">Farmers</div></div></div>
    <div class="stat-card"><span class="stat-icon">🛒</span><div><div class="stat-value"><?= $stats['buyers'] ?></div><div class="stat-label">Buyers</div></div></div>
    <div class="stat-card"><span class="stat-icon">🛡️</span><div><div class="stat-value"><?= $stats['pending'] ?></div><div class="stat-label">Pending verification</div></div></div>
    <div class="stat-card"><span class="stat-icon">🗂️</span><div><div class="stat-value"><?= $stats['products'] ?></div><div class="stat-label">Products</div></div></div>
    <div class="stat-card"><span class="stat-icon">📍</span><div><div class="stat-value"><?= $stats['markets'] ?></div><div class="stat-label">Markets</div></div></div>
    <div class="stat-card"><span class="stat-icon">🌱</span><div><div class="stat-value"><?= $stats['listings'] ?></div><div class="stat-label">Listings</div></div></div>
    <div class="stat-card"><span class="stat-icon">📈</span><div><div class="stat-value"><?= $stats['reports'] ?></div><div class="stat-label">Price reports</div></div></div>
    <div class="stat-card"><span class="stat-icon">🚩</span><div><div class="stat-value"><?= $stats['flagged'] ?></div><div class="stat-label">Unusual prices</div></div></div>
  </div>

  <!-- 30-day report trend + market share -->
  <div class="grid-2-wide">
    <div class="card">
      <div class="card-head">
        <h3 class="card-title">📈 Price reports · last 30 days</h3>
        <span class="small muted"><?= $trendTotal ?> report(s) · peak <?= $maxDay ?>/day</span>
      </div>
      <div class="card-body">
        <div class="trend-bars">
          <?php foreach ($trendDays as $t): ?>
            <span class="trend-bar<?= $t['n'] <= 0 ? ' zero' : '' ?>" style="--h:<?= $trendH($t['n'], $maxDay) ?>%"
                  title="<?= e($t['label']) ?> · <?= $t['n'] ?> report(s)">
              <i></i>
            </span>
          <?php endforeach; ?>
        </div>
        <div class="trend-axis">
          <span><?= e($trendDays[0]['label']) ?></span>
          <span><?= e($trendDays[14]['label']) ?></span>
          <span><?= e($trendDays[29]['label']) ?></span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3 class="card-title">📍 Reports by market</h3>
        <span class="small muted"><?= $mktTotal ?> total</span>
      </div>
      <div class="card-body">
        <?php if (!$mktTotal): ?>
          <div class="empty-state"><span class="empty-icon">📍</span>No price reports yet.</div>
        <?php else: ?>
          <div class="hbars">
            <?php foreach ($mktRows as $m): $n = (int)$m['n'];
                  $pct = $mktTotal > 0 ? round($n / $mktTotal * 100) : 0; ?>
              <div class="hbar-row">
                <span class="hbar-name" title="<?= e($m['market_name']) ?>"><?= e($m['market_name']) ?></span>
                <span class="hbar-track"><i class="hbar-fill" style="--w:<?= $pct ?>%;--c:<?= $marketColor[$m['market_name']] ?? $palette[0] ?>"></i></span>
                <span class="hbar-val"><?= $n ?> <span class="pct">· <?= $pct ?>%</span></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top products + registration trend -->
  <div class="grid-2" style="margin-top:22px">
    <div class="card">
      <div class="card-head">
        <h3 class="card-title">🏆 Top products by reports</h3>
        <span class="small muted">last <?= $topTotal ?> report(s)</span>
      </div>
      <div class="card-body">
        <?php if (!$topProducts): ?>
          <div class="empty-state"><span class="empty-icon">🏆</span>No price reports yet.</div>
        <?php else: ?>
          <div class="hbars">
            <?php foreach ($topProducts as $i => $t): $n = (int)$t['n'];
                  $pct = $topTotal > 0 ? round($n / $topTotal * 100) : 0; ?>
              <div class="hbar-row">
                <span class="hbar-name" title="<?= e($t['product_name']) ?>"><?= e($t['product_name']) ?> <span class="small muted">/ <?= e($t['unit_code']) ?></span></span>
                <span class="hbar-track"><i class="hbar-fill" style="--w:<?= $pct ?>%;--c:<?= $palette[$i % count($palette)] ?>"></i></span>
                <span class="hbar-val"><?= $n ?> <span class="pct">· <?= $pct ?>%</span></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3 class="card-title">👥 New users · last 6 months</h3>
        <span class="small muted"><?= $userTrendTotal ?> registration(s)</span>
      </div>
      <div class="card-body">
        <div class="trend-bars">
          <?php foreach ($months as $m): ?>
            <span class="trend-bar<?= $m['n'] <= 0 ? ' zero' : '' ?>" style="--h:<?= $trendH($m['n'], $maxMonth) ?>%"
                  title="<?= e($m['label']) ?> · <?= $m['n'] ?> new user(s)">
              <i></i>
            </span>
          <?php endforeach; ?>
        </div>
        <div class="trend-axis">
          <?php foreach ($months as $m): ?>
            <span><?= e($m['label']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Unusual price feed -->
  <div class="section-head" style="margin-top:22px">
    <h3 class="section-title">🚩 Recent unusual prices</h3>
    <div style="display:flex;gap:10px;align-items:center">
      <span class="small muted">Reports vs 7-day baseline · market lows/highs vs cross-market average · listings vs latest market price · beyond ±<?= (int)UNUSUAL_PRICE_PCT ?>%</span>
      <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/admin/reports.php') ?>">Open price reports</a>
    </div>
  </div>
  <div class="table-card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Product</th><th>Source</th><th>Price</th><th>Deviation</th><th>Observed</th></tr>
        </thead>
        <tbody>
          <?php if (!$feed): ?>
            <tr><td colspan="5"><div class="empty-state"><span class="empty-icon">✅</span>No unusual prices recorded.</div></td></tr>
          <?php else: foreach ($feed as $f): $dev = (float)$f['dev']; ?>
              <tr>
                <td class="bold"><?= e($f['product_name']) ?> <span class="small muted">/ <?= e($f['unit_code']) ?></span></td>
                <td><?php if ($f['kind'] === 'market'): ?>🏪 <?= e($f['where']) ?>
                    <span class="small muted"><?= e($f['basis']) ?></span>
                    <?php else: ?>🌱 <?= e($f['where']) ?> <span class="small muted">listing</span><?php endif; ?></td>
                <td class="bold"><?= money($f['price']) ?></td>
                <td><span class="badge badge-<?= abs($dev) > 2 * UNUSUAL_PRICE_PCT ? 'red' : 'amber' ?>">
                  <?= ($dev > 0 ? '+' : '') . number_format($dev, 1) ?>%</span></td>
                <td class="small"><?= e(date('M j, Y · g:i A', strtotime($f['when']))) ?></td>
              </tr>
            <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
