<?php
/**
 * dashboard/admin/markets.php - Market CRUD (create, edit, soft-delete / restore).
 * Archived markets stay in the database so their price reports remain intact;
 * every picker/chart query filters them out via markets.deleted_at IS NULL.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

/* Shared input validation for create + update. Returns '' when valid. */
$validateInput = static function (string $name, string $loc, string $lat, string $lng): string {
    if ($name === '' || $loc === '') {
        return 'Market name and location are required.';
    }
    if (mb_strlen($name) > 120 || mb_strlen($loc) > 190) {
        return 'Name (max 120 characters) or location (max 190 characters) is too long.';
    }
    if (($lat !== '' && (!is_numeric($lat) || abs((float)$lat) > 90))
     || ($lng !== '' && (!is_numeric($lng) || abs((float)$lng) > 180))) {
        return 'Coordinates must be valid numbers (latitude -90…90, longitude -180…180).';
    }
    return '';
};

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // 1) Create market
    if ($action === 'add_market') {
        $name = trim(post('market_name'));
        $loc  = trim(post('location'));
        $lat  = trim(post('lat'));
        $lng  = trim(post('lng'));
        $err  = $validateInput($name, $loc, $lat, $lng);
        if ($err !== '') {
            flash($err, 'error');
        } else {
            $existing = Database::first("SELECT id, deleted_at FROM markets WHERE market_name = ?", [$name]);
            if ($existing) {
                flash($existing['deleted_at'] !== null
                    ? 'That name belongs to an archived market — restore it instead.'
                    : 'A market with that name already exists.', 'error');
            } else {
                Database::execute(
                    "INSERT INTO markets (market_name, location, lat, lng) VALUES (?,?,?,?)",
                    [$name, $loc, $lat === '' ? null : (float)$lat, $lng === '' ? null : (float)$lng]
                );
                flash('Market "' . e($name) . '" added.', 'success');
            }
        }
        redirect('/dashboard/admin/markets.php');
    }

    // 2) Update market
    if ($action === 'update_market') {
        $id     = (int)post('market_id');
        $name   = trim(post('market_name'));
        $loc    = trim(post('location'));
        $lat    = trim(post('lat'));
        $lng    = trim(post('lng'));
        $market = Database::first("SELECT id FROM markets WHERE id = ?", [$id]);
        $err    = $validateInput($name, $loc, $lat, $lng);
        if (!$market) {
            flash('Market not found.', 'error');
        } elseif ($err !== '') {
            flash($err, 'error');
        } elseif (Database::scalar("SELECT COUNT(*) FROM markets WHERE market_name = ? AND id <> ?", [$name, $id])) {
            flash('Another market already uses that name.', 'error');
        } else {
            Database::execute(
                "UPDATE markets SET market_name = ?, location = ?, lat = ?, lng = ? WHERE id = ?",
                [$name, $loc, $lat === '' ? null : (float)$lat, $lng === '' ? null : (float)$lng, $id]
            );
            flash('Market "' . e($name) . '" updated.', 'success');
        }
        redirect('/dashboard/admin/markets.php');
    }

    // 3) Soft-delete / restore
    if ($action === 'toggle_market') {
        $id     = (int)post('market_id');
        $market = Database::first("SELECT id, market_name, deleted_at FROM markets WHERE id = ?", [$id]);
        if (!$market) {
            flash('Market not found.', 'error');
        } elseif ($market['deleted_at'] === null) {
            Database::execute("UPDATE markets SET deleted_at = NOW() WHERE id = ?", [$id]);
            flash('Market "' . e($market['market_name']) . '" removed from pickers and charts. Existing price reports are preserved.', 'success');
        } else {
            Database::execute("UPDATE markets SET deleted_at = NULL WHERE id = ?", [$id]);
            flash('Market "' . e($market['market_name']) . '" restored.', 'success');
        }
        redirect('/dashboard/admin/markets.php');
    }

    // 4) Record a manual price report for this market
    if ($action === 'add_price_report') {
        $marketId = (int)post('market_id');
        $pid      = (int)post('product_id');
        $market   = Database::first("SELECT market_name, deleted_at FROM markets WHERE id = ?", [$marketId]);
        $product  = Database::first("SELECT product_name, deleted_at FROM products WHERE id = ?", [$pid]);
        if (!$market || $market['deleted_at'] !== null) {
            flash('Market not found.', 'error');
        } elseif (!$product || $product['deleted_at'] !== null) {
            flash('Choose an active product for the price report.', 'error');
        } else {
            $result = PriceService::addReport([
                'product_id'    => $pid,
                'market_id'     => $marketId,
                'unit_id'       => (int)post('unit_id'),
                'price'         => (float)post('price'),
                'date_reported' => post('date_reported'),
                'source_type'   => 'manual',
                'reported_by'   => (int)$user['id'],
            ]);
            if ($result['ok']) {
                $msg = 'Price report saved: ' . e($product['product_name']) . ' · ' . e($market['market_name'])
                     . ' — ' . money((float)post('price')) . ' (normalized ' . money($result['normalized']) . ')';
                if ($result['flagged']) {
                    $msg .= ' — unusual movement flagged.';
                }
                flash($msg, 'success');
            } else {
                flash($result['error'], 'error');
            }
        }
        redirect('/dashboard/admin/markets.php');
    }

    // 5) Edit a price report from a market's price list
    if ($action === 'edit_market_price') {
        $view  = (int)($_GET['prices'] ?? 0);
        $id    = (int)post('report_id');
        $pid   = (int)post('product_id');
        $uid   = (int)post('unit_id');
        $raw   = post('price');
        $price = is_numeric($raw) ? (float)$raw : -1;
        $d     = trim(post('date_reported'));
        $rep   = Database::first(
            "SELECT id, market_id, product_id, DATE(date_reported) AS old_day
               FROM price_reports WHERE id = ? AND deleted_at IS NULL", [$id]
        );
        $prod = $pid > 0 ? Database::first("SELECT id, deleted_at FROM products WHERE id = ?", [$pid]) : null;
        $unit = $uid > 0 ? Database::first("SELECT id, deleted_at FROM units WHERE id = ?", [$uid]) : null;
        $mkt  = $rep ? Database::first("SELECT id, deleted_at FROM markets WHERE id = ?", [$rep['market_id']]) : null;
        $day  = strtotime($d) ? date('Y-m-d', strtotime($d)) : '';
        if (!$rep) {
            flash('Price report not found.', 'error');
        } elseif (!$prod || $prod['deleted_at'] !== null || !$unit || $unit['deleted_at'] !== null) {
            flash('Choose an active product and unit.', 'error');
        } elseif (!$mkt || $mkt['deleted_at'] !== null) {
            flash('This market is archived — restore it first to edit its prices.', 'error');
        } elseif ($price < 0) {
            flash('Enter a valid price.', 'error');
        } elseif ($day === '') {
            flash('Enter a valid date observed.', 'error');
        } elseif (PriceService::factor($pid, $uid) === null) {
            flash('That unit does not convert to the product base unit.', 'error');
        } else {
            $norm = PriceService::normalize($pid, $uid, $price);
            $base = PriceService::baseline($pid, (int)$rep['market_id'], $day);
            $dv   = PriceService::displayDeviation($pid, (int)$rep['market_id'], $day, (float)$norm, $id, $base);
            $dev  = $dv['dev'];
            Database::execute(
                "UPDATE price_reports
                    SET product_id = ?, unit_id = ?, price = ?, normalized_price = ?,
                        baseline_price = ?, deviation_pct = ?, date_reported = ?
                  WHERE id = ?",
                [$pid, $uid, $price, $norm, $base['baseline'] ?? null, $dev,
                 date('Y-m-d H:i:s', strtotime($d)), $id]
            );
            // Refresh the daily aggregates for the new (and, if the date or
            // product changed, the previous) day so charts stay in sync.
            PriceService::rebuildDay($pid, (int)$rep['market_id'], $day);
            if ((int)$rep['product_id'] !== $pid || $rep['old_day'] !== $day) {
                PriceService::rebuildDay((int)$rep['product_id'], (int)$rep['market_id'], $rep['old_day']);
            }
            flash('Price report updated: ' . money($price) . ' — normalized ' . money((float)$norm) . '.', 'success');
        }
        redirect($view > 0 ? '/dashboard/admin/markets.php?prices=' . $view : '/dashboard/admin/markets.php');
    }

    // 6) Delete a price report from a market's price list
    if ($action === 'delete_market_price') {
        $view = (int)($_GET['prices'] ?? 0);
        $id   = (int)post('report_id');
        $rep  = Database::first(
            "SELECT id, product_id, market_id, DATE(date_reported) AS day
               FROM price_reports WHERE id = ? AND deleted_at IS NULL", [$id]
        );
        if (!$rep) {
            flash('Price report not found.', 'error');
        } else {
            Database::execute("UPDATE price_reports SET deleted_at = NOW() WHERE id = ?", [$id]);
            PriceService::rebuildDay((int)$rep['product_id'], (int)$rep['market_id'], $rep['day']);
            flash('Price report removed.', 'success');
        }
        redirect($view > 0 ? '/dashboard/admin/markets.php?prices=' . $view : '/dashboard/admin/markets.php');
    }

    redirect('/dashboard/admin/markets.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/admin/markets.php');

/* --- Markets (search: mq · rows: mlimit · page: mpage) --- */
$mCtl    = table_controls(['prefix' => 'm', 'action' => $actionUrl, 'default_limit' => 10]);
$mWhere  = '1 = 1';
$mParams = [];
if ($mCtl['q'] !== '') {
    $mWhere .= ' AND (mk.market_name LIKE ? OR mk.location LIKE ?)';
    $like = '%' . $mCtl['q'] . '%';
    array_push($mParams, $like, $like);
}
$mCtl = table_paginate($mCtl, (int)Database::scalar("SELECT COUNT(*) FROM markets mk WHERE {$mWhere}", $mParams));
$markets = Database::select(
    "SELECT mk.id, mk.market_name, mk.location, mk.lat, mk.lng, mk.deleted_at, mk.created_at,
            COALESCE(r.n, 0) AS report_count
       FROM markets mk
       LEFT JOIN (SELECT market_id, COUNT(*) AS n
                    FROM price_reports WHERE deleted_at IS NULL GROUP BY market_id) r
              ON r.market_id = mk.id
      WHERE {$mWhere}
      ORDER BY mk.deleted_at IS NOT NULL, mk.market_name
      LIMIT {$mCtl['limit']} OFFSET {$mCtl['offset']}",
    $mParams
);

$products  = Database::select("SELECT id, product_name, kind FROM products WHERE deleted_at IS NULL ORDER BY product_name");
$unitsList = Database::select("SELECT id, code, name FROM units WHERE deleted_at IS NULL ORDER BY name");
$unitMap   = [];
foreach (Database::select("SELECT product_id, unit_id FROM product_units") as $pu) {
    $unitMap[$pu['product_id']][] = (int)$pu['unit_id'];
}

/* --- Price list for one market (?prices=ID): every recorded price for
 * all products at that market — editable / deletable via the modals. --- */
$pricesView   = (int)($_GET['prices'] ?? 0);
$pricesMarket = null;
$priceRows    = [];
if ($pricesView > 0) {
    $pricesMarket = Database::first(
        "SELECT id, market_name, location, deleted_at FROM markets WHERE id = ?",
        [$pricesView]
    );
    if (!$pricesMarket) {
        flash('Market not found.', 'error');
        $pricesView = 0;
    } else {
        $priceRows = Database::select(
            "SELECT r.id, r.product_id, r.unit_id, r.price, r.normalized_price, r.deviation_pct,
                    r.date_reported, r.source_type, p.product_name, p.kind,
                    u.name AS unit_name, u.code AS unit_code
               FROM price_reports r
               JOIN products p ON p.id = r.product_id
               JOIN units u ON u.id = r.unit_id
              WHERE r.market_id = ? AND r.deleted_at IS NULL
              ORDER BY p.product_name, r.date_reported DESC, r.id DESC",
            [$pricesView]
        );
    }
}

/* Keep the market search + paging when jumping to / back from the list. */
$ctxQ = [];
foreach (['mq', 'mlimit', 'mpage'] as $ck) {
    if (isset($_GET[$ck]) && $_GET[$ck] !== '') { $ctxQ[$ck] = $_GET[$ck]; }
}

$pageTitle     = 'Markets';
$activeSection = 'markets';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<script>
  window.PRODUCT_UNITS = <?= json_encode($unitMap) ?>;
  document.addEventListener('DOMContentLoaded', function () {
    [['pe_product', 'pe_unit'], ['me_product', 'me_unit']].forEach(function (ids) {
      var product = document.getElementById(ids[0]);
      var unit = document.getElementById(ids[1]);
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
  });
</script>

<!-- ================= MARKETS ============================= -->
<section class="section" id="markets">
  <div class="section-head">
    <h2 class="section-title">📍 Markets</h2>
    <div class="flex" style="gap:12px;align-items:center">
      <span class="small muted"><?= $mCtl['total'] ?> market<?= $mCtl['total'] === 1 ? '' : 's' ?> tracked</span>
      <button class="btn btn-primary btn-sm" type="button" data-modal-open="modal-add-market">＋ Add market</button>
    </div>
  </div>

  <?php if ($pricesView > 0 && $pricesMarket): ?>
    <!-- ============ PRICES RECORDED FOR ONE MARKET ============ -->
    <div class="table-card" style="margin-bottom:16px">
      <div class="flex" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
        <div>
          <strong>🏷️ Prices recorded for <?= e($pricesMarket['market_name']) ?></strong>
          <span class="small muted"> · <?= count($priceRows) ?> price report<?= count($priceRows) === 1 ? '' : 's' ?> across all products<?=
            $pricesMarket['deleted_at'] !== null ? ' · market archived — editing disabled' : '' ?></span>
          <br><span class="small muted"><?= e($pricesMarket['location']) ?></span>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e($actionUrl . ($ctxQ ? '?' . http_build_query($ctxQ) : '')) ?>">✕ Close</a>
      </div>
      <div class="table-wrap">
        <table class="data-table" style="min-width:940px">
          <thead>
            <tr><th>Product</th><th>Unit</th><th>Price</th><th>Normalized</th><th>Deviation</th><th>Date observed</th><th>Source</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (!$priceRows): ?>
              <tr><td colspan="8"><div class="empty-state"><span class="empty-icon">🏷️</span>No price has been recorded for this market yet — use ＋ Price to add one.</div></td></tr>
            <?php else: foreach ($priceRows as $pr): ?>
                <tr>
                  <td>
                    <span class="bold"><?= e($pr['product_name']) ?></span><br>
                    <span class="small muted"><?= e($pr['kind']) ?></span>
                  </td>
                  <td><?= e($pr['unit_code']) ?> <span class="small muted">/ <?= e($pr['unit_name']) ?></span></td>
                  <td class="bold"><?= money($pr['price']) ?></td>
                  <td><?= money($pr['normalized_price']) ?></td>
                  <td><?= $pr['deviation_pct'] !== null ? e(number_format((float)$pr['deviation_pct'], 2)) . '%' : '—' ?></td>
                  <td class="small"><?= e(date('M j, Y', strtotime($pr['date_reported']))) ?></td>
                  <td><span class="badge badge-slate"><?= e($pr['source_type']) ?></span></td>
                  <td style="white-space:nowrap">
                    <?php if ($pricesMarket['deleted_at'] === null): ?>
                      <button class="btn btn-outline btn-sm" type="button"
                              data-modal-open="modal-edit-market-price"
                              data-report-id="<?= (int)$pr['id'] ?>"
                              data-fill-mname="<?= e($pricesMarket['market_name']) ?>"
                              data-fill-pname="<?= e($pr['product_name']) ?>"
                              data-price="<?= e((string)$pr['price']) ?>"
                              data-product-id="<?= (int)$pr['product_id'] ?>"
                              data-unit-id="<?= (int)$pr['unit_id'] ?>"
                              data-date-reported="<?= e(date('Y-m-d', strtotime($pr['date_reported']))) ?>">Edit</button>
                      <button class="btn btn-danger btn-sm" type="button"
                              data-modal-open="modal-delete-market-price"
                              data-report-id="<?= (int)$pr['id'] ?>"
                              data-fill-name="<?= e($pr['product_name']) ?> · <?= money($pr['price']) ?>"
                              data-confirm-label="Delete price">Delete</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="table-card">
    <?= table_search_bar($mCtl, 'Search market or location…') ?>
    <div class="table-wrap">
      <table class="data-table" style="min-width:940px">
        <thead>
          <tr><th>Market</th><th>Location</th><th>Price reports</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (!$markets): ?>
            <tr><td colspan="5"><div class="empty-state"><span class="empty-icon">📍</span>No markets match this search.</div></td></tr>
          <?php else: foreach ($markets as $mk): ?>
              <tr<?= $mk['deleted_at'] ? ' style="opacity:.65"' : '' ?>>
                <td>
                  <span class="bold"><?= e($mk['market_name']) ?></span><br>
                  <span class="small muted">#<?= $mk['id'] ?> · added <?= e(date('M j, Y', strtotime($mk['created_at']))) ?></span>
                </td>
                <td>
                  <?= e($mk['location']) ?>
                  <?php if ($mk['lat'] !== null && $mk['lng'] !== null): ?>
                    <br><span class="small muted"><?= number_format((float)$mk['lat'], 5) ?>, <?= number_format((float)$mk['lng'], 5) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= (int)$mk['report_count'] ?></td>
                <td><span class="badge badge-<?= $mk['deleted_at'] ? 'slate' : 'green' ?>"><?= $mk['deleted_at'] ? 'archived' : 'active' ?></span></td>
                <td style="white-space:nowrap">
                  <a class="btn btn-outline btn-sm"
                     href="<?= e($actionUrl . '?' . http_build_query(['prices' => (int)$mk['id']] + $ctxQ)) ?>"
                     title="View the prices recorded for all products at this market">Prices</a>
                  <?php if ($mk['deleted_at'] === null): ?>
                  <button class="btn btn-outline btn-sm" type="button"
                          data-modal-open="modal-price-entry"
                          data-market-id="<?= $mk['id'] ?>"
                          data-fill-mname="<?= e($mk['market_name']) ?>"
                          data-date-reported="<?= date('Y-m-d') ?>"
                          data-price="">＋ Price</button>
                  <?php endif; ?>
                  <button class="btn btn-outline btn-sm" type="button"
                          data-modal-open="modal-edit-market"
                          data-market-id="<?= $mk['id'] ?>"
                          data-market-name="<?= e($mk['market_name']) ?>"
                          data-location="<?= e($mk['location']) ?>"
                          data-lat="<?= $mk['lat'] !== null ? e((string)(float)$mk['lat']) : '' ?>"
                          data-lng="<?= $mk['lng'] !== null ? e((string)(float)$mk['lng']) : '' ?>">Edit</button>
                  <button class="btn <?= $mk['deleted_at'] ? 'btn-outline' : 'btn-danger' ?> btn-sm" type="button"
                          data-modal-open="modal-toggle-market"
                          data-market-id="<?= $mk['id'] ?>"
                          data-fill-name="<?= e($mk['market_name']) ?>"
                          data-confirm-label="<?= $mk['deleted_at'] ? 'Restore market' : 'Remove market' ?>"
                          data-confirm-class="btn <?= $mk['deleted_at'] ? 'btn-outline' : 'btn-danger' ?> btn-sm">
                    <?= $mk['deleted_at'] ? 'Restore' : 'Remove' ?>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($mCtl) ?>
  </div>
  <p class="small muted" style="margin-top:10px">Removing a market is a soft delete: it disappears from pickers, charts and new reports,
    while its existing price reports are preserved and the market can be restored at any time.</p>
</section>

<!-- ================= CRUD MODALS ======================== -->
<?= modal_open('modal-add-market', 'Add market', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_market">
    <div class="form-group">
      <label>Market name</label>
      <input type="text" name="market_name" required maxlength="120" placeholder="e.g. Kidapawan City Public Market">
    </div>
    <div class="form-group">
      <label>Location</label>
      <input type="text" name="location" required maxlength="190" placeholder="e.g. Kidapawan, Cotabato">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Latitude (optional)</label>
        <input type="number" name="lat" step="any" min="-90" max="90" placeholder="e.g. 7.0106">
      </div>
      <div class="form-group">
        <label>Longitude (optional)</label>
        <input type="number" name="lng" step="any" min="-180" max="180" placeholder="e.g. 124.8414">
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Add market</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-edit-market', 'Edit market', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_market">
    <input type="hidden" name="market_id" value="">
    <div class="form-group">
      <label>Market name</label>
      <input type="text" name="market_name" required maxlength="120">
    </div>
    <div class="form-group">
      <label>Location</label>
      <input type="text" name="location" required maxlength="190">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Latitude (optional)</label>
        <input type="number" name="lat" step="any" min="-90" max="90">
      </div>
      <div class="form-group">
        <label>Longitude (optional)</label>
        <input type="number" name="lng" step="any" min="-180" max="180">
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-toggle-market', 'Confirm market change', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle_market">
    <input type="hidden" name="market_id" value="">
    <p class="modal-note">
      You are about to change the status of <strong data-fill="name">this market</strong>.
      Removing is a soft delete — existing price reports are preserved, the market is hidden from
      pickers and charts, and it can be restored at any time.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-danger" type="submit" data-modal-confirm>Remove market</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-price-entry', 'Record price report') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_price_report">
    <input type="hidden" name="market_id" value="">
    <div class="form-group">
      <label>Market</label>
      <strong data-fill="mname">—</strong>
      <p class="small muted" style="margin:4px 0 0">Unit options below are filtered to the chosen product.</p>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Product</label>
        <select name="product_id" id="pe_product" required>
          <option value="">Select…</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?> (<?= e($p['kind']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Unit</label>
        <select name="unit_id" id="pe_unit" required>
          <option value="">Select…</option>
          <?php foreach ($unitsList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Price (₱)</label>
        <input type="number" name="price" required min="0" step="0.01" placeholder="e.g. 58.50">
      </div>
      <div class="form-group">
        <label>Date observed</label>
        <input type="date" name="date_reported" required value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save price report</button>
    </div>
  </form>
<?= modal_close() ?>

<?php if ($pricesView > 0 && $pricesMarket): ?>
<?= modal_open('modal-edit-market-price', 'Edit price report') ?>
  <form method="post" action="<?= e($actionUrl) ?>?prices=<?= $pricesView ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="edit_market_price">
    <input type="hidden" name="report_id" value="">
    <div class="form-group">
      <label>Market</label>
      <strong data-fill="mname">—</strong>
      <p class="small muted" style="margin:4px 0 0">Editing the price report for <span data-fill="pname">this product</span> — unit options are filtered to the chosen product.</p>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Product</label>
        <select name="product_id" id="me_product" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?> (<?= e($p['kind']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Unit</label>
        <select name="unit_id" id="me_unit" required>
          <?php foreach ($unitsList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Price (₱)</label>
        <input type="number" name="price" required min="0" step="0.01" placeholder="e.g. 58.50">
      </div>
      <div class="form-group">
        <label>Date observed</label>
        <input type="date" name="date_reported" required>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-delete-market-price', 'Delete price report', 'sm') ?>
  <form method="post" action="<?= e($actionUrl) ?>?prices=<?= $pricesView ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_market_price">
    <input type="hidden" name="report_id" value="">
    <p class="modal-note">
      Delete the price report for <strong data-fill="name">this product</strong>?
      This is a soft delete — the record stays in the database but disappears from
      the price list, charts and reports.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-danger" type="submit" data-modal-confirm>Delete price</button>
    </div>
  </form>
<?= modal_close() ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
