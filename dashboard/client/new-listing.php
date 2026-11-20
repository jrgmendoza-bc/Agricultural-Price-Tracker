<?php
/**
 * dashboard/client/new-listing.php - Publish a produce / livestock listing.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('farmer');

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // Create a new listing (produce / livestock / poultry)
    if ($action === 'add_listing') {
        $productId = (int)post('product_id');
        $unitId    = (int)post('unit_id');
        $quantity  = (float)str_replace(',', '', post('quantity'));
        $asking    = (float)str_replace(',', '', post('asking_price'));
        $stage     = post('stage');
        $lat       = post('pickup_lat');
        $lng       = post('pickup_lng');
        $placeName = post('pickup_location_name');

        $product = Database::first(
            "SELECT * FROM products WHERE id = ? AND deleted_at IS NULL",
            [$productId]
        );
        $allowed = $product && $product['kind'] === 'livestock' ? LIVESTOCK_STAGES : CROP_STAGES;

        if (!$product) {
            flash('Please select a valid product.', 'error');
        } elseif ($unitId <= 0 || !PriceService::factor($productId, $unitId)) {
            flash('Choose a unit that converts to this product\'s base unit.', 'error');
        } elseif ($quantity <= 0 || $asking < 0) {
            flash('Quantity must be above zero and the asking price must not be negative.', 'error');
        } elseif (!in_array($stage, $allowed, true)) {
            flash('Invalid growth stage for this product kind.', 'error');
        } elseif (($lat === '') !== ($lng === '')) {
            flash('Provide both pickup coordinates, or leave both empty.', 'error');
        } elseif ($lat !== '' && (!is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 90 || abs((float)$lng) > 180)) {
            flash('Pickup coordinates are out of range.', 'error');
        } else {
            $normalized = PriceService::normalize($productId, $unitId, $asking);
            Database::execute(
                "INSERT INTO crops (farmer_id, product_id, unit_id, quantity, asking_price,
                                    normalized_asking_price, status, pickup_lat, pickup_lng,
                                    pickup_location_name, current_stage)
                 VALUES (?,?,?,?,?,?, 'available', ?,?,?,?)",
                [
                    $user['id'], $productId, $unitId, $quantity, $asking, $normalized,
                    $lat !== '' ? $lat : null,
                    $lng !== '' ? $lng : null,
                    $placeName !== '' ? $placeName : null,
                    $stage,
                ]
            );
            $cropId = (int)Database::getConnection()->lastInsertId();
            Database::execute(
                "INSERT INTO crop_growth_stages (crop_id, stage, updated_by, notes) VALUES (?,?,?,?)",
                [$cropId, $stage, $user['id'], 'Listing created']
            );
            flash('Listing published with a normalized asking price of ' . money($normalized) . '.', 'success');
        }
        redirect('/dashboard/client/listings.php');
    }

    redirect('/dashboard/client/new-listing.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/client/new-listing.php');

$products  = Database::select("SELECT id, product_name, kind FROM products WHERE deleted_at IS NULL ORDER BY product_name");
$unitsList = Database::select("SELECT id, code, name FROM units WHERE deleted_at IS NULL ORDER BY name");

$unitMap = [];
foreach (Database::select("SELECT product_id, unit_id FROM product_units") as $pu) {
    $unitMap[$pu['product_id']][] = (int)$pu['unit_id'];
}

$pageTitle     = 'New listing';
$activeSection = 'new-listing';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<script>
  window.PRODUCT_UNITS = <?= json_encode($unitMap) ?>;
  document.addEventListener('DOMContentLoaded', function () {
    var product = document.getElementById('listing_product');
    var unit = document.getElementById('listing_unit');
    if (!product || !unit) return;
    function filterUnits() {
      var allowed = window.PRODUCT_UNITS[product.value] || [];
      Array.prototype.forEach.call(unit.options, function (opt) {
        if (!opt.value) return;
        opt.hidden = allowed.length > 0 && allowed.indexOf(parseInt(opt.value, 10)) === -1;
      });
      var selectedVisible = unit.options[unit.selectedIndex];
      if (selectedVisible && selectedVisible.hidden) {
        unit.value = allowed[0] ? String(allowed[0]) : '';
      }
    }
    product.addEventListener('change', filterUnits);
    filterUnits();
  });
</script>

<!-- ======================= NEW LISTING ==================== -->
<section class="section" id="new-listing">
  <div class="section-head"><h2 class="section-title">➕ New listing</h2></div>
  <div class="card">
    <div class="card-body flex-between" style="gap:18px;flex-wrap:wrap">
      <p class="small muted" style="margin:0;max-width:640px">
        Publish produce, livestock or poultry for verified buyers. The asking price is
        normalized to the product's base unit and compared against the market rate.
      </p>
      <button class="btn btn-primary" type="button" data-modal-open="modal-new-listing">＋ New listing</button>
    </div>
  </div>
</section>

<!-- ======================= CRUD MODALS ==================== -->
<?= modal_open('modal-new-listing', 'New listing') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_listing">
    <div class="form-grid">
      <div class="form-group">
        <label>Product</label>
        <select name="product_id" id="listing_product" required>
          <option value="">Select…</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?> (<?= e($p['kind']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Unit</label>
        <select name="unit_id" id="listing_unit" required>
          <option value="">Select…</option>
          <?php foreach ($unitsList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Quantity</label>
        <input type="number" name="quantity" step="0.01" min="0.01" placeholder="e.g. 120" required>
      </div>
      <div class="form-group">
        <label>Asking price (₱, in the chosen unit)</label>
        <input type="number" name="asking_price" step="0.0001" min="0" placeholder="e.g. 1450" required>
        <div class="hint">Normalized automatically for comparison with market rates.</div>
      </div>
      <div class="form-group">
        <label>Growth / raising stage</label>
        <select name="stage" required>
          <optgroup label="Crop stages">
            <?php foreach (CROP_STAGES as $s): ?><option value="<?= $s ?>"><?= stage_label($s) ?></option><?php endforeach; ?>
          </optgroup>
          <optgroup label="Livestock &amp; poultry stages">
            <?php foreach (LIVESTOCK_STAGES as $s): ?><option value="<?= $s ?>"><?= stage_label($s) ?></option><?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="form-group">
        <label>Pickup location name</label>
        <input type="text" name="pickup_location_name" placeholder="e.g. Poblacion, Carmen">
      </div>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label>Pickup latitude</label>
        <input type="text" name="pickup_lat" id="pickup_lat" placeholder="7.20405000">
      </div>
      <div class="form-group">
        <label>Pickup longitude</label>
        <input type="text" name="pickup_lng" id="pickup_lng" placeholder="124.78950000">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button type="button" class="btn btn-outline" data-geolocate="pickup">📍 Use my location</button>
        <div class="hint">Or type the coordinates manually. Left empty = shown honestly as "not set".</div>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Publish listing</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
