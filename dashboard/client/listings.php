<?php
/**
 * dashboard/client/listings.php - Farmer's listings: growth-stage history,
 * sale recording and soft-delete.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('farmer');

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // 1) Stage update - appended as a new dated history row (never overwritten)
    if ($action === 'update_stage') {
        $cropId = (int)post('crop_id');
        $stage  = post('stage');
        $crop = Database::first(
            "SELECT c.*, p.kind FROM crops c JOIN products p ON p.id = c.product_id
              WHERE c.id = ? AND c.farmer_id = ? AND c.deleted_at IS NULL",
            [$cropId, $user['id']]
        );
        $allowed = $crop && $crop['kind'] === 'livestock' ? LIVESTOCK_STAGES : CROP_STAGES;
        if (!$crop) {
            flash('Listing not found or access denied.', 'error');
        } elseif (!in_array($stage, $allowed, true)) {
            flash('Invalid stage for this listing.', 'error');
        } elseif ($crop['current_stage'] === $stage) {
            flash('That is already the current stage.', 'error');
        } else {
            Database::execute(
                "INSERT INTO crop_growth_stages (crop_id, stage, updated_by) VALUES (?,?,?)",
                [$cropId, $stage, $user['id']]
            );
            Database::execute("UPDATE crops SET current_stage = ? WHERE id = ?", [$stage, $cropId]);
            flash('Stage history updated: ' . stage_label($stage) . '.', 'success');
        }
        redirect('/dashboard/client/listings.php');
    }

    // 2) Record a (partial) sale - reduces the remaining quantity
    if ($action === 'record_sale') {
        $cropId = (int)post('crop_id');
        $qty    = (float)str_replace(',', '', post('quantity_sold'));
        $crop = Database::first(
            "SELECT * FROM crops WHERE id = ? AND farmer_id = ? AND deleted_at IS NULL",
            [$cropId, $user['id']]
        );
        if (!$crop) {
            flash('Listing not found or access denied.', 'error');
            redirect('/dashboard/client/listings.php');
        }
        $sold = (float)Database::scalar(
            "SELECT COALESCE(SUM(quantity_sold), 0) FROM crop_sales WHERE crop_id = ?",
            [$cropId]
        );
        $remaining = (float)$crop['quantity'] - $sold;

        if ($qty <= 0) {
            flash('Sold quantity must be above zero.', 'error');
        } elseif ($qty > $remaining) {
            $unitCode = Database::scalar("SELECT code FROM units WHERE id = ?", [$crop['unit_id']]);
            flash('Only ' . number_format($remaining, 2) . ' ' . $unitCode . ' remain on this listing.', 'error');
        } else {
            Database::execute(
                "INSERT INTO crop_sales (crop_id, quantity_sold, recorded_by) VALUES (?,?,?)",
                [$cropId, $qty, $user['id']]
            );
            $newStatus = ($remaining - $qty) <= 0.000001 ? 'sold' : 'reserved';
            Database::execute("UPDATE crops SET status = ? WHERE id = ?", [$newStatus, $cropId]);
            flash('Sale recorded. Remaining quantity: ' . number_format($remaining - $qty, 2) . '.', 'success');
        }
        redirect('/dashboard/client/listings.php');
    }

    // 3) Soft-delete own listing (goes to the admin Recycle Bin)
    if ($action === 'delete_listing') {
        $cropId = (int)post('crop_id');
        $owned = Database::scalar(
            "SELECT COUNT(*) FROM crops WHERE id = ? AND farmer_id = ? AND deleted_at IS NULL",
            [$cropId, $user['id']]
        );
        if ($owned) {
            Database::execute("UPDATE crops SET deleted_at = NOW() WHERE id = ?", [$cropId]);
            flash('Listing removed. An administrator can still restore it from the Recycle Bin.', 'success');
        } else {
            flash('Listing not found or access denied.', 'error');
        }
        redirect('/dashboard/client/listings.php');
    }

    redirect('/dashboard/client/listings.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/client/listings.php');

/* --- My listings (search: lq · rows: llimit · page: lpage) --- */
$lCtl   = table_controls([
    'prefix'       => 'l',
    'action'       => $actionUrl,
    'default_limit' => 10,
]);
$lWhere  = "c.farmer_id = ? AND c.deleted_at IS NULL";
$lParams = [$user['id']];
if ($lCtl['q'] !== '') {
    $lWhere .= " AND (p.product_name LIKE ? OR c.pickup_location_name LIKE ? OR c.status LIKE ?)";
    $like = '%' . $lCtl['q'] . '%';
    array_push($lParams, $like, $like, $like);
}
$lCtl = table_paginate($lCtl, (int)Database::scalar(
    "SELECT COUNT(*) FROM crops c JOIN products p ON p.id = c.product_id WHERE {$lWhere}",
    $lParams
));
$listings = Database::select(
    "SELECT c.*, p.product_name, p.kind, u.code AS unit_code
       FROM crops c
       JOIN products p ON p.id = c.product_id
       JOIN units u    ON u.id = c.unit_id
      WHERE {$lWhere}
      ORDER BY c.created_at DESC
      LIMIT {$lCtl['limit']} OFFSET {$lCtl['offset']}",
    $lParams
);

$pageTitle     = 'My listings';
$activeSection = 'listings';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ======================= MY LISTINGS ==================== -->
<section class="section" id="listings">
  <div class="section-head">
    <h2 class="section-title">🌱 My listings</h2>
    <span class="small muted">Stage updates are appended as dated history — never overwritten.</span>
  </div>

  <div class="table-card">
    <?= table_search_bar($lCtl, 'Search product or pickup location…') ?>
    <div class="table-wrap">
      <table class="data-table" style="min-width:1040px">
        <thead>
          <tr>
            <th>Product</th><th>Qty</th><th>Asking</th><th>vs. market</th>
            <th>Status</th><th>Stage</th><th>Sale</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$listings): ?>
          <tr><td colspan="8"><div class="empty-state"><span class="empty-icon">🌱</span>You have no listings yet — publish your first batch from the New listing page.</div></td></tr>
        <?php else: foreach ($listings as $l):
            $marketRate = PriceService::latestMarketPrice((int)$l['product_id']);
            $sold = (float)Database::scalar("SELECT COALESCE(SUM(quantity_sold),0) FROM crop_sales WHERE crop_id = ?", [$l['id']]);
            $remaining = (float)$l['quantity'] - $sold;
            $diff = ($marketRate !== null && $marketRate > 0)
                ? (($l['normalized_asking_price'] - $marketRate) / $marketRate) * 100 : null;
          ?>
          <tr>
            <td>
              <span class="bold"><?= e($l['product_name']) ?></span><br>
              <span class="small muted">#<?= $l['id'] ?> · <?= e($l['pickup_location_name'] ?? 'No pickup point set') ?></span>
            </td>
            <td><?= number_format($remaining, 2) ?> / <?= number_format((float)$l['quantity'], 2) ?> <?= e($l['unit_code']) ?>
              <?php if ($sold > 0): ?><br><span class="small muted"><?= number_format($sold, 2) ?> sold</span><?php endif; ?>
            </td>
            <td><?= money($l['asking_price']) ?> <span class="small muted">/ <?= e($l['unit_code']) ?></span><br>
              <span class="small muted"><?= money($l['normalized_asking_price']) ?> normalized</span></td>
            <td>
              <?php if ($diff === null): ?>
                <span class="muted small">no market data</span>
              <?php elseif ($diff <= 0): ?>
                <span class="badge badge-green"><?= number_format(abs($diff), 1) ?>% below market</span>
              <?php else: ?>
                <span class="badge badge-amber"><?= number_format($diff, 1) ?>% above market</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $l['status'] === 'available' ? 'green' : ($l['status'] === 'reserved' ? 'amber' : 'slate') ?>"><?= e($l['status']) ?></span></td>
            <td>
              <span class="bold small"><?= stage_label($l['current_stage']) ?></span><br>
              <button class="btn btn-outline btn-sm" type="button"
                      data-modal-open="modal-stage"
                      data-crop-id="<?= $l['id'] ?>"
                      data-stage="<?= e($l['current_stage']) ?>"
                      data-kind="<?= e($l['kind']) ?>"
                      data-fill-product="<?= e($l['product_name']) ?> (<?= e($l['kind']) ?>) #<?= $l['id'] ?>">Update</button>
            </td>
            <td>
              <?php if ($remaining > 0 && $l['status'] !== 'sold'): ?>
              <button class="btn btn-primary btn-sm" type="button"
                      data-modal-open="modal-sale"
                      data-crop-id="<?= $l['id'] ?>"
                      data-max-quantity-sold="<?= $remaining ?>"
                      data-fill-product="<?= e($l['product_name']) ?> #<?= $l['id'] ?>"
                      data-fill-remaining="<?= number_format($remaining, 2) ?> <?= e($l['unit_code']) ?>"
                      data-fill-unit="<?= e($l['unit_code']) ?>">Record</button>
              <?php else: ?>
                <span class="small muted">fully sold</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-ghost btn-sm" type="button" title="Remove listing"
                      data-modal-open="modal-delete-listing"
                      data-crop-id="<?= $l['id'] ?>"
                      data-fill-product="<?= e($l['product_name']) ?> #<?= $l['id'] ?>">🗑</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($lCtl) ?>
  </div>
</section>

<!-- ======================= CRUD MODALS ==================== -->
<?= modal_open('modal-stage', 'Update growth stage', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_stage">
    <input type="hidden" name="crop_id" value="">
    <p class="modal-note">
      Listing <strong data-fill="product">this listing</strong> — stage history is appended
      as a dated row, previous stages are never overwritten.
    </p>
    <div class="form-group">
      <label>Stage</label>
      <select name="stage" required>
        <optgroup label="Crop stages" data-kind="crop">
          <?php foreach (CROP_STAGES as $s): ?><option value="<?= $s ?>"><?= stage_label($s) ?></option><?php endforeach; ?>
        </optgroup>
        <optgroup label="Livestock &amp; poultry stages" data-kind="livestock">
          <?php foreach (LIVESTOCK_STAGES as $s): ?><option value="<?= $s ?>"><?= stage_label($s) ?></option><?php endforeach; ?>
        </optgroup>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save stage</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-sale', 'Record a sale', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="record_sale">
    <input type="hidden" name="crop_id" value="">
    <p class="modal-note">
      Listing <strong data-fill="product">this listing</strong> —
      remaining: <strong data-fill="remaining">—</strong>.
    </p>
    <div class="form-group">
      <label>Sold quantity</label>
      <input type="number" name="quantity_sold" step="0.01" min="0.01" required placeholder="e.g. 25">
      <div class="hint">Enter the quantity in <span data-fill="unit">the listing unit</span>.
        When the remaining quantity reaches zero the listing is marked as sold.</div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Record sale</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-delete-listing', 'Remove listing', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_listing">
    <input type="hidden" name="crop_id" value="">
    <p class="modal-note">
      Remove listing <strong data-fill="product">this listing</strong>? It disappears from the
      marketplace immediately. An administrator can still restore it from the Recycle Bin.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Keep listing</button>
      <button class="btn btn-danger" type="submit" data-modal-confirm>Remove listing</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
