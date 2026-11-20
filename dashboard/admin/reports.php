<?php /**
 * dashboard/admin/reports.php - Recent price reports with search + pagination + CRUD.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

$actionUrl = base_url('dashboard/admin/reports.php');

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    if ($action === 'add_report') {
        $pid = (int)post('product_id');
        $mid = (int)post('market_id');
        $product  = Database::first("SELECT product_name, deleted_at FROM products WHERE id = ?", [$pid]);
        $market   = Database::first("SELECT market_name, deleted_at FROM markets WHERE id = ?", [$mid]);
        if (!$product || $product['deleted_at'] !== null || !$market || $market['deleted_at'] !== null) {
            flash('Invalid product or market.', 'error');
        } else {
            $res = PriceService::addReport([
                'product_id'    => $pid,
                'market_id'     => $mid,
                'unit_id'       => (int)post('unit_id'),
                'price'         => (float)post('price'),
                'date_reported' => post('date_reported'),
                'source_type'   => 'manual',
                'reported_by'   => (int)$user['id'],
            ]);
            if ($res['ok']) {
                $msg = 'Price report saved: ' . e($product['product_name']) . ' · ' . e($market['market_name'])
                     . ' · ' . money((float)post('price')) . ' (normalized ' . money($res['normalized']) . ')';
                if ($res['flagged']) { $msg .= ' · unusual movement flagged.'; }
                flash($msg, 'success');
            } else {
                flash($res['error'], 'error');
            }
        }
        redirect('/dashboard/admin/reports.php');
    }

    if ($action === 'edit_report') {
        $id = (int)post('report_id');
        $rep = Database::first("SELECT id, product_id, market_id, unit_id, price, date_reported FROM price_reports WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$rep) {
            flash('Report not found.', 'error');
        } else {
            $pid = (int)post('product_id');
            $mid = (int)post('market_id');
            $uid = (int)post('unit_id');
            $price = (float)post('price');
            $d = post('date_reported');
            $prod = Database::first("SELECT id, deleted_at FROM products WHERE id=?", [$pid]);
            $mkt  = Database::first("SELECT id, deleted_at FROM markets WHERE id=?", [$mid]);
            $unit = Database::first("SELECT id, deleted_at FROM units WHERE id=?", [$uid]);
            if (!$prod || $prod['deleted_at']!==null || !$mkt || $mkt['deleted_at']!==null || !$unit || $unit['deleted_at']!==null) {
                flash('Invalid selection.', 'error');
            } else {
                $f = Database::scalar("SELECT factor_to_base FROM product_units WHERE product_id=? AND unit_id=?", [$pid, $uid]);
                if ($f === null || $f <= 0) { $f = 1.0; }
                $norm = round($price / (float)$f, 4);
                $base = PriceService::baseline($pid, $mid, $d);
                $dv   = PriceService::displayDeviation($pid, $mid, $d, (float)$norm, $id, $base);
                $dev  = $dv['dev'];
                Database::execute("UPDATE price_reports SET product_id=?, market_id=?, unit_id=?, price=?, normalized_price=?, baseline_price=?, deviation_pct=?, date_reported=? WHERE id=?",
                    [$pid,$mid,$uid,$price,$norm,$base['baseline'] ?? null,$dev,$d,$id]);
                flash('Price report updated.', 'success');
            }
        }
        redirect('/dashboard/admin/reports.php');
    }

    if ($action === 'delete_report') {
        $id = (int)post('report_id');
        Database::execute("UPDATE price_reports SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$id]);
        flash('Price report removed.', 'success');
        redirect('/dashboard/admin/reports.php');
    }
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */

/* --- Recent reports (search: rq · rows: rlimit · page: rpage) --- */
$rCtl    = table_controls(['prefix' => 'r', 'action' => $actionUrl, 'default_limit' => 10]);
$rWhere  = 'r.deleted_at IS NULL';
$rParams = [];
if ($rCtl['q'] !== '') {
    $rWhere .= ' AND (p.product_name LIKE ? OR m.market_name LIKE ? OR r.source_type LIKE ?)';
    $like = '%' . $rCtl['q'] . '%';
    array_push($rParams, $like, $like, $like);
}
$rCtl = table_paginate($rCtl, (int)Database::scalar(
    "SELECT COUNT(*) FROM price_reports r
       JOIN products p ON p.id = r.product_id
       JOIN markets m ON m.id = r.market_id
      WHERE {$rWhere}",
    $rParams
));
$recentReports = Database::select(
    "SELECT r.id, r.product_id, r.market_id, r.price, r.normalized_price, r.source_type, r.deviation_pct,
            r.date_reported, p.product_name, m.market_name, u.code AS unit_code
       FROM price_reports r
       JOIN products p ON p.id = r.product_id
       JOIN markets m ON m.id = r.market_id
       JOIN units u ON u.id = r.unit_id
      WHERE {$rWhere}
      ORDER BY r.date_reported DESC
      LIMIT {$rCtl['limit']} OFFSET {$rCtl['offset']}",
    $rParams
);

$pageTitle     = 'Price reports';
$activeSection = 'reports';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<section class="section" id="reports">
  <div class="section-head">
    <h2 class="section-title">Recent price reports</h2>
    <span class="small muted">Flagged rows exceed ±<?= UNUSUAL_PRICE_PCT ?>% — measured vs the 7-day baseline, the market's recent price, or the product average (hover for basis)</span>
  </div>
  <div class="table-card">
    <?= table_search_bar($rCtl, 'Search product, market or source') ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Product</th><th>Market</th><th>Quoted</th><th>Normalized</th><th>Source</th><th>Deviation</th><th>Observed</th></tr>
        </thead>
        <tbody>
        <?php if (!$recentReports): ?>
          <tr><td colspan="7"><div class="empty-state"><span class="empty-icon">📊</span>No price reports yet.</div></td></tr>
        <?php else: foreach ($recentReports as $r):
          $dv  = PriceService::displayDeviation((int)$r['product_id'], (int)$r['market_id'], $r['date_reported'], (float)$r['normalized_price'], (int)$r['id']);
          $dev = $dv['dev'];
          if ($dev === null) {
              $devTitle = 'No price history to compare against yet';
          } else {
              $label = ['baseline' => 'vs 7-day baseline ', 'recent_price' => 'vs recent price ', 'product_avg' => 'vs product average '][$dv['basis']] ?? '';
              $devTitle = $label . money((float)$dv['basis_value']);
          }
        ?>
          <tr>
            <td class="bold"><?= e($r['product_name']) ?></td>
            <td><?= e($r['market_name']) ?></td>
            <td><?= money($r['price']) ?> <span class="small muted">/ <?= e($r['unit_code']) ?></span></td>
            <td class="bold" style="color:var(--green-800)"><?= money($r['normalized_price']) ?></td>
            <td><span class="badge badge-slate"><?= e($r['source_type']) ?></span></td>
            <td>
              <?php if ($dev === null): ?>
                <span class="muted small" title="<?= e($devTitle) ?>">N/A</span>
              <?php elseif (abs($dev) > UNUSUAL_PRICE_PCT): ?>
                <span class="badge badge-red" title="<?= e($devTitle) ?>"><?= number_format($dev, 1) ?>%</span>
              <?php else: ?>
                <span class="badge badge-green" title="<?= e($devTitle) ?>"><?= number_format($dev, 1) ?>%</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e(date('M j, Y · g:i A', strtotime($r['date_reported']))) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($rCtl) ?>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>