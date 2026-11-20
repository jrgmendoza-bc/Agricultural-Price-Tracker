<?php
/**
 * dashboard/client/overview.php - Farmer overview: KPI cards, market-price
 * trend for their own products, listing status / stage breakdowns and the
 * sales trend.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('farmer');
$uid  = (int)$user['id'];

// Document upload / remove posted from the card on this page.
VerificationDocs::handleAccountPost($user, '/dashboard/client/overview.php');

$stats = [
    'listings'  => (int)Database::scalar("SELECT COUNT(*) FROM crops WHERE farmer_id = ? AND deleted_at IS NULL", [$uid]),
    'available' => (int)Database::scalar("SELECT COUNT(*) FROM crops WHERE farmer_id = ? AND status = 'available' AND deleted_at IS NULL", [$uid]),
    'sold'      => (int)Database::scalar("SELECT COUNT(*) FROM crops WHERE farmer_id = ? AND status = 'sold' AND deleted_at IS NULL", [$uid]),
    'threads'   => (int)Database::scalar("SELECT COUNT(*) FROM conversations WHERE farmer_id = ?", [$uid]),
    'sales'     => (int)Database::scalar(
        "SELECT COUNT(*) FROM crop_sales s JOIN crops c ON c.id = s.crop_id WHERE c.farmer_id = ?",
        [$uid]
    ),
    'unread'    => (int)Database::scalar(
        "SELECT COUNT(*) FROM messages m
           JOIN conversations c ON c.id = m.conversation_id
          WHERE m.read_at IS NULL AND m.sender_id <> ?
            AND (c.farmer_id = ? OR c.buyer_id = ?)",
        [$uid, $uid, $uid]
    ),
    'mktReports' => (int)Database::scalar(
        "SELECT COUNT(*) FROM price_reports
          WHERE deleted_at IS NULL
            AND product_id IN (SELECT DISTINCT product_id FROM crops WHERE farmer_id = ? AND deleted_at IS NULL)",
        [$uid]
    ),
];
$stats['reserved'] = max(0, $stats['listings'] - $stats['available'] - $stats['sold']);

/* --- Market price trend for the farmer's own products (30 days) --- */
$priceRows = Database::select(
    "SELECT DATE(r.date_reported) AS d, AVG(r.normalized_price) AS avg_price, COUNT(*) AS n
       FROM price_reports r
      WHERE r.deleted_at IS NULL
        AND r.product_id IN (SELECT DISTINCT product_id FROM crops
                              WHERE farmer_id = ? AND deleted_at IS NULL)
        AND r.date_reported >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
      GROUP BY DATE(r.date_reported)",
    [$uid]
);
$priceMap = [];
foreach ($priceRows as $p) {
    $priceMap[$p['d']] = $p;
}
$priceDays  = [];
$maxPrice   = 0.0;
$priceTotal = 0;
$priceSum   = 0.0;
for ($i = 29; $i >= 0; $i--) {
    $dt  = date('Y-m-d', strtotime("-{$i} days"));
    $row = $priceMap[$dt] ?? null;
    $avg = $row ? (float)$row['avg_price'] : 0.0;
    $n   = $row ? (int)$row['n'] : 0;
    if ($row) {
        $maxPrice  = max($maxPrice, $avg);
        $priceTotal += $n;
        $priceSum  += $avg * $n;
    }
    $priceDays[] = ['label' => date('M j', strtotime($dt)), 'avg' => $avg, 'n' => $n];
}
$priceAvg = $priceTotal > 0 ? $priceSum / $priceTotal : 0.0;

/* --- Sales trend: one bucket per day, last 30 days --- */
$saleRows = Database::select(
    "SELECT DATE(s.sold_at) AS d, COUNT(*) AS n
       FROM crop_sales s JOIN crops c ON c.id = s.crop_id
      WHERE c.farmer_id = ? AND s.sold_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
      GROUP BY DATE(s.sold_at)",
    [$uid]
);
$saleMap = [];
foreach ($saleRows as $s) {
    $saleMap[$s['d']] = (int)$s['n'];
}
$saleDays = [];
$maxSale  = 0;
for ($i = 29; $i >= 0; $i--) {
    $dt = date('Y-m-d', strtotime("-{$i} days"));
    $n  = $saleMap[$dt] ?? 0;
    $maxSale = max($maxSale, $n);
    $saleDays[] = ['label' => date('M j', strtotime($dt)), 'n' => $n];
}
$saleTotal = array_sum(array_column($saleDays, 'n'));

/* --- Listings by growth stage (ordered like the stage lists) --- */
$stageOrder = array_flip(array_merge(CROP_STAGES, LIVESTOCK_STAGES));
$stageRows  = Database::select(
    "SELECT current_stage, COUNT(*) AS n
       FROM crops
      WHERE farmer_id = ? AND deleted_at IS NULL AND current_stage IS NOT NULL
      GROUP BY current_stage",
    [$uid]
);
usort($stageRows, static function ($a, $b) use ($stageOrder) {
    $ai = $stageOrder[$a['current_stage']] ?? PHP_INT_MAX;
    $bi = $stageOrder[$b['current_stage']] ?? PHP_INT_MAX;
    return $ai <=> $bi;
});
$stageTotal = 0;
foreach ($stageRows as $s) {
    $stageTotal += (int)$s['n'];
}

$pageTitle     = 'Overview';
$activeSection = 'overview';
require __DIR__ . '/../../includes/dashboard_header.php';

$trendH = static function (int $n, int $max): int {
    return $n <= 0 ? 0 : max(6, (int)round($n / max(1, $max) * 100));
};
$pctOf  = static function (int $n, int $total): int {
    return $total > 0 ? (int)round($n / $total * 100) : 0;
};
$statusRows = [
    ['label' => 'Available', 'n' => $stats['available'], 'color' => '#0f766e'],
    ['label' => 'Reserved',  'n' => $stats['reserved'],  'color' => '#d97706'],
    ['label' => 'Sold',      'n' => $stats['sold'],      'color' => '#2563eb'],
];
$stagePalette = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#0891b2', '#dc2626'];
?>

<!-- ======================= OVERVIEW ======================= -->
<section class="section" id="overview">
  <div class="banner banner-verified">
    <span class="banner-ico">🌾</span>
    <div class="banner-body">
      <strong>Hello, <?= e(Auth::displayName($user)) ?>!</strong>
      <div class="small muted">Your listings are visible to verified buyers. Keep stages current so buyers see a real timeline.</div>
    </div>
    <a class="btn btn-primary btn-sm" href="<?= base_url('dashboard/client/new-listing.php') ?>">+ New listing</a>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">🌱</span><div><div class="stat-value"><?= $stats['listings'] ?></div><div class="stat-label">My listings</div></div></div>
    <div class="stat-card"><span class="stat-icon">✅</span><div><div class="stat-value"><?= $stats['available'] ?></div><div class="stat-label">Available</div></div></div>
    <div class="stat-card"><span class="stat-icon">🔒</span><div><div class="stat-value"><?= $stats['reserved'] ?></div><div class="stat-label">Reserved</div></div></div>
    <div class="stat-card"><span class="stat-icon">🧾</span><div><div class="stat-value"><?= $stats['sold'] ?></div><div class="stat-label">Sold</div></div></div>
    <div class="stat-card"><span class="stat-icon">🛒</span><div><div class="stat-value"><?= $stats['sales'] ?></div><div class="stat-label">Sales recorded</div></div></div>
    <div class="stat-card"><span class="stat-icon">💬</span><div><div class="stat-value"><?= $stats['threads'] ?></div><div class="stat-label">Conversations</div></div></div>
    <div class="stat-card"><span class="stat-icon">🔔</span><div><div class="stat-value"><?= $stats['unread'] ?></div><div class="stat-label">Unread messages</div></div></div>
    <div class="stat-card"><span class="stat-icon">📈</span><div><div class="stat-value"><?= $stats['mktReports'] ?></div><div class="stat-label">Market reports (my crops)</div></div></div>
  </div>

  <!-- Market prices for my products + status split -->
  <div class="grid-2-wide" style="margin-top:20px">
    <div class="card">
      <div class="card-head">
        <h3 class="card-title">📈 Market prices · my products · last 30 days</h3>
        <span class="small muted"><?= $priceTotal ?> report(s)<?= $priceTotal ? ' · avg ' . money($priceAvg) : '' ?></span>
      </div>
      <div class="card-body">
        <?php if (!$priceTotal): ?>
          <div class="empty-state"><span class="empty-icon">📈</span>No market reports yet for your products.</div>
        <?php else: ?>
          <div class="trend-bars">
            <?php foreach ($priceDays as $t): ?>
              <span class="trend-bar<?= $t['n'] <= 0 ? ' zero' : '' ?>" style="--h:<?= $trendH((int)round($t['avg']), (int)round($maxPrice)) ?>%"
                    title="<?= e($t['label']) ?> · <?= money($t['avg']) ?> (<?= $t['n'] ?> report(s))">
                <i></i>
              </span>
            <?php endforeach; ?>
          </div>
          <div class="trend-axis">
            <span><?= e($priceDays[0]['label']) ?></span>
            <span><?= e($priceDays[14]['label']) ?></span>
            <span><?= e($priceDays[29]['label']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3 class="card-title">📊 Listings by status</h3>
        <span class="small muted"><?= $stats['listings'] ?> total</span>
      </div>
      <div class="card-body">
        <?php if (!$stats['listings']): ?>
          <div class="empty-state"><span class="empty-icon">🌱</span>No listings yet — publish your first batch.</div>
        <?php else: ?>
          <div class="hbars">
            <?php foreach ($statusRows as $s): ?>
              <div class="hbar-row">
                <span class="hbar-name"><?= e($s['label']) ?></span>
                <span class="hbar-track"><i class="hbar-fill" style="--w:<?= $pctOf($s['n'], $stats['listings']) ?>%;--c:<?= $s['color'] ?>"></i></span>
                <span class="hbar-val"><?= $s['n'] ?> <span class="pct">· <?= $pctOf($s['n'], $stats['listings']) ?>%</span></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Growth stages + sales trend -->
  <div class="grid-2" style="margin-top:22px">
    <div class="card">
      <div class="card-head">
        <h3 class="card-title">🌿 Listings by growth stage</h3>
        <span class="small muted"><?= $stageTotal ?> staged</span>
      </div>
      <div class="card-body">
        <?php if (!$stageRows): ?>
          <div class="empty-state"><span class="empty-icon">🌿</span>No staged listings yet.</div>
        <?php else: ?>
          <div class="hbars">
            <?php foreach ($stageRows as $i => $s): ?>
              <div class="hbar-row">
                <span class="hbar-name"><?= e(stage_label($s['current_stage'])) ?></span>
                <span class="hbar-track"><i class="hbar-fill" style="--w:<?= $pctOf((int)$s['n'], $stageTotal) ?>%;--c:<?= $stagePalette[$i % count($stagePalette)] ?>"></i></span>
                <span class="hbar-val"><?= (int)$s['n'] ?> <span class="pct">· <?= $pctOf((int)$s['n'], $stageTotal) ?>%</span></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3 class="card-title">🛒 Sales recorded · last 30 days</h3>
        <span class="small muted"><?= $saleTotal ?> sale(s) · peak <?= $maxSale ?>/day</span>
      </div>
      <div class="card-body">
        <div class="trend-bars">
          <?php foreach ($saleDays as $t): ?>
            <span class="trend-bar<?= $t['n'] <= 0 ? ' zero' : '' ?>" style="--h:<?= $trendH($t['n'], $maxSale) ?>%"
                  title="<?= e($t['label']) ?> · <?= $t['n'] ?> sale(s)">
              <i></i>
            </span>
          <?php endforeach; ?>
        </div>
        <div class="trend-axis">
          <span><?= e($saleDays[0]['label']) ?></span>
          <span><?= e($saleDays[14]['label']) ?></span>
          <span><?= e($saleDays[29]['label']) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="grid-2" style="margin-top:22px">
    <div class="card">
      <div class="card-head"><h3 class="card-title">🌱 My listings</h3></div>
      <div class="card-body flex-between" style="gap:14px;flex-wrap:wrap">
        <p class="small muted" style="margin:0">Manage stages, record sales and review your active batches.</p>
        <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/client/listings.php') ?>">Open listings</a>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3 class="card-title">💬 Messages &amp; help</h3></div>
      <div class="card-body flex-between" style="gap:14px;flex-wrap:wrap">
        <p class="small muted" style="margin:0">Reply to buyers or ask the administrators a question.</p>
        <span style="display:flex;gap:8px">
          <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/client/messages.php') ?>">Messages</a>
          <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/client/help.php') ?>">Help</a>
        </span>
      </div>
    </div>
  </div>

  <!-- Uploaded verification documents: view, open full size, replace or remove -->
  <div style="margin-top:20px">
    <?= VerificationDocs::cardHtml($user) ?>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
