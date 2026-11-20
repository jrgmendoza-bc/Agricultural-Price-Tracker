<?php
/**
 * dashboard/buyer/marketplace.php - Browse listings and contact farmers.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('buyer');

$isVerified = $user['verification_status'] === 'verified';

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // Open (or reuse) a listing-anchored conversation with a farmer
    if ($action === 'start_conversation') {
        $result = Messaging::openForListing((int)post('crop_id'), (int)$user['id']);
        if ($result['ok']) {
            redirect('/dashboard/buyer/messages.php?thread=' . $result['conversation_id']);
        }
        flash($result['error'], 'error');
        redirect('/dashboard/buyer/marketplace.php');
    }

    redirect('/dashboard/buyer/marketplace.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/buyer/marketplace.php');

/* --- Marketplace (search: mq · rows: mlimit · page: mpage) --- */
$mCtl   = table_controls([
    'prefix'        => 'm',
    'action'        => $actionUrl,
    'default_limit' => 10,
]);
$mWhere  = "c.deleted_at IS NULL AND f.deleted_at IS NULL";
$mParams = [];
if ($mCtl['q'] !== '') {
    $mWhere .= " AND (p.product_name LIKE ? OR f.first_name LIKE ? OR f.last_name LIKE ?
                    OR c.pickup_location_name LIKE ? OR c.status LIKE ?)";
    $like = '%' . $mCtl['q'] . '%';
    array_push($mParams, $like, $like, $like, $like, $like);
}
$mCtl = table_paginate($mCtl, (int)Database::scalar(
    "SELECT COUNT(*) FROM crops c
       JOIN products p ON p.id = c.product_id
       JOIN users    f ON f.id = c.farmer_id
      WHERE {$mWhere}",
    $mParams
));
$marketplace = Database::select(
    "SELECT c.*, p.product_name, p.kind, u.code AS unit_code,
            f.first_name AS farmer_first, f.last_name AS farmer_last, f.id AS farmer_id
       FROM crops c
       JOIN products p ON p.id = c.product_id
       JOIN units u    ON u.id = c.unit_id
       JOIN users f    ON f.id = c.farmer_id
      WHERE {$mWhere}
      ORDER BY c.normalized_asking_price ASC, c.created_at DESC
      LIMIT {$mCtl['limit']} OFFSET {$mCtl['offset']}",
    $mParams
);

$pageTitle     = 'Marketplace';
$activeSection = 'marketplace';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ======================= MARKETPLACE ==================== -->
<section class="section" id="marketplace">
  <div class="section-head">
    <h2 class="section-title">🛒 Marketplace</h2>
    <span class="small muted">Sorted by normalized asking price — comparable across every unit.</span>
  </div>

  <div class="table-card">
    <?= table_search_bar($mCtl, 'Search product, farmer or pickup location…') ?>
    <div class="table-wrap">
      <table class="data-table" style="min-width:980px">
        <thead>
          <tr>
            <th>Listing</th><th>Farmer</th><th>Quantity</th><th>Asking (normalized)</th>
            <th>Market rate</th><th>Stage</th><th>Pickup</th><th>Contact</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$marketplace): ?>
          <tr><td colspan="8"><div class="empty-state"><span class="empty-icon">🛒</span>No listings have been published yet.</div></td></tr>
        <?php else: foreach ($marketplace as $l):
            $marketRate = PriceService::latestMarketPrice((int)$l['product_id']);
            $diff = ($marketRate !== null && $marketRate > 0)
                ? (($l['normalized_asking_price'] - $marketRate) / $marketRate) * 100 : null;
            $sold = (float)Database::scalar("SELECT COALESCE(SUM(quantity_sold),0) FROM crop_sales WHERE crop_id = ?", [$l['id']]);
            $remaining = (float)$l['quantity'] - $sold;
            $isMine = (int)$l['farmer_id'] === (int)$user['id'];
          ?>
          <tr>
            <td>
              <span class="bold"><?= e($l['product_name']) ?></span><br>
              <span class="badge badge-<?= $l['status'] === 'available' ? 'green' : ($l['status'] === 'reserved' ? 'amber' : 'slate') ?>"><?= e($l['status']) ?></span>
              <span class="small muted">#<?= $l['id'] ?></span>
            </td>
            <td><?= e($l['farmer_first'] . ' ' . $l['farmer_last']) ?></td>
            <td><?= number_format($remaining, 2) ?> / <?= number_format((float)$l['quantity'], 2) ?> <?= e($l['unit_code']) ?></td>
            <td>
              <span class="bold" style="color:var(--green-800)"><?= money($l['normalized_asking_price']) ?></span>
              <span class="small muted">/ <?= e($l['unit_code'] === 'tray' ? 'pc' : ($l['unit_code'])) ?></span><br>
              <span class="small muted">ask <?= money($l['asking_price']) ?>/<?= e($l['unit_code']) ?></span>
            </td>
            <td>
              <?php if ($marketRate === null): ?>
                <span class="muted small">no data</span><br><span class="small muted">—</span>
              <?php else: ?>
                <?= money($marketRate) ?>
                <br>
                <?php if ($diff === null): ?>
                  <span class="small muted">—</span>
                <?php elseif ($diff <= 0): ?>
                  <span class="badge badge-green"><?= number_format(abs($diff), 1) ?>% below</span>
                <?php else: ?>
                  <span class="badge badge-amber"><?= number_format($diff, 1) ?>% above</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= e(stage_label($l['current_stage'] ?? '—')) ?></td>
            <td class="small"><?= e($l['pickup_location_name'] ?? 'Not set') ?>
              <?php if ($l['pickup_lat'] !== null): ?><br><span class="muted"><?= e($l['pickup_lat']) ?>, <?= e($l['pickup_lng']) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($isMine): ?>
                <span class="small muted">your listing</span>
              <?php elseif (!$isVerified): ?>
                <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/buyer/account.php') ?>" title="Verification required">🔒 Verify to chat</a>
              <?php elseif ($l['status'] === 'sold'): ?>
                <span class="small muted">sold</span>
              <?php else: ?>
                <button class="btn btn-primary btn-sm" type="button"
                        data-modal-open="modal-contact"
                        data-crop-id="<?= $l['id'] ?>"
                        data-fill-product="<?= e($l['product_name']) ?> #<?= $l['id'] ?>"
                        data-fill-farmer="<?= e($l['farmer_first'] . ' ' . $l['farmer_last']) ?>">💬 Message farmer</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($mCtl) ?>
  </div>
</section>

<!-- ======================= CRUD MODALS ==================== -->
<?= modal_open('modal-contact', 'Message the farmer', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start_conversation">
    <input type="hidden" name="crop_id" value="">
    <p class="modal-note">
      Start a conversation with <strong data-fill="farmer">the farmer</strong> about listing
      <strong data-fill="product">this listing</strong>. The thread stays anchored to this
      listing; your contact details are never shown publicly.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">💬 Start conversation</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
