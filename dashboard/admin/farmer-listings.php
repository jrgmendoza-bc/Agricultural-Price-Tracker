<?php
/**
 * dashboard/admin/farmer-listings.php - read-only list of every farmer's
 * active crop listings: who is offering what, at which asking price,
 * stage and status (the farmer side of the price data menu).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::requireRole('admin');

$actionUrl = base_url('dashboard/admin/farmer-listings.php');

/* --- Farmer listings (search: fq · rows: flimit · page: fpage) --- */
$fCtl    = table_controls(['prefix' => 'f', 'action' => $actionUrl, 'default_limit' => 10]);
$fWhere  = 'c.deleted_at IS NULL AND f.deleted_at IS NULL';
$fParams = [];
if ($fCtl['q'] !== '') {
    $fWhere .= " AND (CONCAT_WS(' ', f.first_name, f.last_name) LIKE ? OR f.username LIKE ?
                    OR p.product_name LIKE ? OR c.pickup_location_name LIKE ?
                    OR c.status LIKE ? OR c.current_stage LIKE ?)";
    $like = '%' . $fCtl['q'] . '%';
    array_push($fParams, $like, $like, $like, $like, $like, $like);
}
$fCtl = table_paginate($fCtl, (int)Database::scalar(
    "SELECT COUNT(*)
       FROM crops c
       JOIN products p ON p.id = c.product_id
       JOIN users f    ON f.id = c.farmer_id
      WHERE {$fWhere}",
    $fParams
));
$listings = Database::select(
    "SELECT c.id, c.quantity, c.asking_price, c.normalized_asking_price, c.status,
            c.current_stage, c.pickup_location_name, c.created_at, c.product_id,
            p.product_name, u.code AS unit_code,
            COALESCE(NULLIF(CONCAT_WS(' ', f.first_name, f.last_name), ''), f.username, f.email) AS farmer_name,
            f.username AS farmer_handle,
            (SELECT COALESCE(SUM(cs.quantity_sold), 0) FROM crop_sales cs WHERE cs.crop_id = c.id) AS sold
       FROM crops c
       JOIN products p ON p.id = c.product_id
       JOIN units u    ON u.id = c.unit_id
       JOIN users f    ON f.id = c.farmer_id
      WHERE {$fWhere}
      ORDER BY c.created_at DESC
      LIMIT {$fCtl['limit']} OFFSET {$fCtl['offset']}",
    $fParams
);

$pageTitle     = 'Farmer listings';
$activeSection = 'farmer-listings';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ================= FARMER LISTINGS ======================= -->
<section class="section" id="farmer-listings">
  <div class="section-head">
    <h2 class="section-title">🌱 Farmer listings</h2>
    <span class="small muted"><?= $fCtl['total'] ?> active listing<?= $fCtl['total'] === 1 ? '' : 's' ?> · asking prices shown against the latest market price</span>
  </div>

  <div class="table-card">
    <?= table_search_bar($fCtl, 'Search farmer, product, location or status…') ?>
    <div class="table-wrap">
      <table class="data-table" style="min-width:1080px">
        <thead>
          <tr>
            <th>Farmer</th><th>Product</th><th>Qty</th><th>Asking</th>
            <th>vs. market</th><th>Status</th><th>Stage</th><th>Pickup</th><th>Listed</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$listings): ?>
          <tr><td colspan="9"><div class="empty-state"><span class="empty-icon">🌱</span>No farmer listings match this search.</div></td></tr>
        <?php else: foreach ($listings as $l):
            $marketRate = PriceService::latestMarketPrice((int)$l['product_id']);
            $sold       = (float)$l['sold'];
            $remaining  = (float)$l['quantity'] - $sold;
            $diff = ($marketRate !== null && $marketRate > 0)
                ? (($l['normalized_asking_price'] - $marketRate) / $marketRate) * 100 : null;
          ?>
          <tr>
            <td>
              <span class="bold"><?= e($l['farmer_name']) ?></span><br>
              <span class="small muted">@<?= e($l['farmer_handle']) ?></span>
            </td>
            <td class="bold"><?= e($l['product_name']) ?></td>
            <td><?= number_format($remaining, 2) ?> / <?= number_format((float)$l['quantity'], 2) ?> <?= e($l['unit_code']) ?>
              <?php if ($sold > 0): ?><br><span class="small muted"><?= number_format($sold, 2) ?> sold</span><?php endif; ?>
            </td>
            <td><?= money($l['asking_price']) ?> <span class="small muted">/ <?= e($l['unit_code']) ?></span><br>
              <span class="small muted"><?= money($l['normalized_asking_price']) ?> normalized</span></td>
            <td>
              <?php if ($diff === null): ?>
                <span class="muted small">no market data</span>
              <?php elseif ($diff <= 0): ?>
                <span class="badge badge-green"><?= number_format(abs($diff), 1) ?>% below</span>
              <?php else: ?>
                <span class="badge badge-amber"><?= number_format($diff, 1) ?>% above</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $l['status'] === 'available' ? 'green' : ($l['status'] === 'reserved' ? 'amber' : 'slate') ?>"><?= e($l['status']) ?></span></td>
            <td><span class="bold small"><?= $l['current_stage'] !== null && $l['current_stage'] !== '' ? e(stage_label($l['current_stage'])) : '—' ?></span></td>
            <td class="small"><?= e($l['pickup_location_name'] ?? '—') ?></td>
            <td class="small"><?= e(date('M j, Y', strtotime($l['created_at']))) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($fCtl) ?>
  </div>
  <p class="small muted" style="margin-top:10px">Showing active listings only — soft-deleted listings and listings from
    removed farmer accounts are excluded. Prices are normalized per base unit for comparison with market reports.</p>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>