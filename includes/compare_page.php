<?php
/**
 * compare_page.php - shared "Compare prices" page for every dashboard.
 * Pick products (plus an optional market and period) and compare their
 * average normalized market price with a bar graph + detail table.
 * Role wrappers must run bootstrap + Auth::requireRole() and set $actionUrl.
 */
$pageTitle     = 'Compare prices';
$activeSection = 'compare';

$products = Database::select(
    "SELECT p.id, p.product_name, u.code AS unit_code
       FROM products p
       JOIN units u ON u.id = p.base_unit_id
      WHERE p.deleted_at IS NULL
      ORDER BY p.product_name"
);
$markets = Database::select(
    "SELECT id, market_name FROM markets WHERE deleted_at IS NULL ORDER BY market_name"
);
$farmers = Database::select(
    "SELECT f.id AS fid,
            COALESCE(NULLIF(CONCAT_WS(' ', f.first_name, f.last_name), ''), f.username, f.email, 'Farmer') AS fname
       FROM users f
      WHERE f.role = 'farmer' AND f.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM crops c WHERE c.farmer_id = f.id AND c.deleted_at IS NULL)
      ORDER BY fname"
);
$farmerNameOf = [];
foreach ($farmers as $f) {
    $farmerNameOf[(int)$f['fid']] = $f['fname'];
}

$nameOf  = [];
$unitOf  = [];
$validId = [];
foreach ($products as $p) {
    $pid = (int)$p['id'];
    $nameOf[$pid] = $p['product_name'];
    $unitOf[$pid] = $p['unit_code'];
    $validId[]    = $pid;
}
$marketNameOf = [0 => 'All markets'];
foreach ($markets as $m) {
    $marketNameOf[(int)$m['id']] = $m['market_name'];
}

$days     = in_array((int)($_GET['days'] ?? 0), [7, 30, 90], true) ? (int)$_GET['days'] : 30;
$mode     = $_GET['mode'] ?? 'product';
if (!in_array($mode, ['product', 'markets', 'buyers', 'buyer-mkt'], true)) { $mode = 'product'; }
$mid      = (int)($_GET['m'] ?? 0);
$marketId = isset($marketNameOf[$mid]) ? $mid : 0;
if ($mode === 'markets') { $marketId = 0; }   // market vs market always covers every market
$bid = (int)($_GET['b'] ?? 0);
if (!isset($farmerNameOf[$bid])) { $bid = 0; }
if ($mode === 'buyer-mkt' && $bid === 0 && $farmers) { $bid = (int)$farmers[0]['fid']; }
$buyerLabel = $farmerNameOf[$bid] ?? '';

/* Selected products: hidden p[] inputs (form) + links, p_add from the
 * combobox; first visit preselects five that carry data. */
$addId  = (int)($_GET['p_add'] ?? 0);
$chosen = [];
if (array_key_exists('p', $_GET) || array_key_exists('p_present', $_GET) || $addId > 0) {
    foreach ((array)($_GET['p'] ?? []) as $rawId) {
        $pid = (int)$rawId;
        if (in_array($pid, $validId, true)) {
            $chosen[$pid] = true;
        }
    }
    if (in_array($addId, $validId, true)) {
        $chosen[$addId] = true;
    }
} else {
    foreach (Database::select(
        "SELECT DISTINCT p.id
           FROM products p
           JOIN price_reports r ON r.product_id = p.id AND r.deleted_at IS NULL
          WHERE p.deleted_at IS NULL
          ORDER BY p.product_name
          LIMIT 5"
    ) as $row) {
        $chosen[(int)$row['id']] = true;
    }
}
$chosenIds = array_keys($chosen);

/* Canonical URL for a chip's ✕ remove link (drops p_add, keeps scope). */
$cmpUrl = static function (array $ids) use ($actionUrl, $marketId, $days, $mode, $bid): string {
    $q = ['p_present' => 1, 'm' => $marketId, 'days' => $days, 'mode' => $mode];
    if ($bid) { $q['b'] = $bid; }
    if ($ids) {
        $q['p'] = array_values($ids);
    }
    return $actionUrl . '?' . http_build_query($q);
};

/* Window stats + overall latest price per selected product. */
$stats  = [];
$latest = [];
if ($chosenIds) {
    $in     = implode(',', $chosenIds);   // validated ints only
    $params = [$days];                    // bound: INTERVAL ? DAY (+ optional market)
    $whereM = '';
    if ($marketId) {
        $whereM   = ' AND r.market_id = ?';
        $params[] = $marketId;
    }
    foreach (Database::select(
        "SELECT r.product_id, COUNT(*) AS n, AVG(r.normalized_price) AS avg_price,
                MIN(r.normalized_price) AS min_price, MAX(r.normalized_price) AS max_price,
                MAX(r.date_reported) AS last_at
           FROM price_reports r
          WHERE r.deleted_at IS NULL
            AND r.product_id IN ({$in})
            AND r.date_reported >= DATE_SUB(NOW(), INTERVAL ? DAY){$whereM}
          GROUP BY r.product_id",
        $params
    ) as $row) {
        $stats[(int)$row['product_id']] = $row;
    }

    $lateParams = [];
    $lateJoin   = '';
    if ($marketId) {
        $lateJoin    = ' AND market_id = ?';
        $lateParams  = [$marketId, $marketId];
    }
    foreach (Database::select(
        "SELECT t.product_id, AVG(r.normalized_price) AS latest_price, MAX(r.date_reported) AS latest_at
           FROM (SELECT product_id, MAX(date_reported) AS mx
                   FROM price_reports
                  WHERE deleted_at IS NULL AND product_id IN ({$in}){$lateJoin}
                  GROUP BY product_id) t
           JOIN price_reports r ON r.product_id = t.product_id
                               AND r.date_reported = t.mx
                               AND r.deleted_at IS NULL{$lateJoin}
          GROUP BY t.product_id",
        $lateParams
    ) as $row) {
        $latest[(int)$row['product_id']] = $row;
    }
}

/* Market-name and buyer-name breakdowns: chart groups + table name columns. */
$palette     = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2'];
$marketColor = [];
foreach ($markets as $i => $m) {
    $marketColor[(int)$m['id']] = $palette[$i % count($palette)];
}
$byMarket   = [];   // pid => [label rows with avg_price / n], ordered by market name
$byBuyer    = [];   // pid => [label rows with avg_price / n], one per farmer (latest listing)
$buyerColor = [];
if ($chosenIds) {
    foreach (Database::select(
        "SELECT r.product_id, r.market_id, m.market_name AS label,
                AVG(r.normalized_price) AS avg_price, COUNT(*) AS n
           FROM price_reports r
           JOIN markets m ON m.id = r.market_id
          WHERE r.deleted_at IS NULL
            AND r.product_id IN ({$in})
            AND r.date_reported >= DATE_SUB(NOW(), INTERVAL ? DAY){$whereM}
          GROUP BY r.product_id, r.market_id, m.market_name
          ORDER BY m.market_name",
        $params
    ) as $row) {
        $byMarket[(int)$row['product_id']][] = $row;
    }

    /* Farmer prices come from each farmer's latest listing per product
     * (crops.normalized_asking_price) — not from the user who recorded
     * the market price. */
    $farmerListings = [];   // farmer_id => product_id => latest listing row
    foreach (Database::select(
        "SELECT c.farmer_id, c.product_id, c.normalized_asking_price, c.created_at
           FROM crops c
          WHERE c.deleted_at IS NULL
            AND c.product_id IN ({$in})
            AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          ORDER BY c.created_at DESC, c.id DESC",
        [$days]
    ) as $row) {
        $fid = (int)$row['farmer_id'];
        $pid = (int)$row['product_id'];
        if (!isset($farmerListings[$fid][$pid])) {
            $farmerListings[$fid][$pid] = $row;
        }
    }
    foreach ($farmerListings as $fid => $prods) {
        $label = $farmerNameOf[$fid] ?? ('Farmer #' . $fid);
        foreach ($prods as $pid => $row) {
            $byBuyer[$pid][] = [
                'label'     => $label,
                'avg_price' => $row['normalized_asking_price'],
                'n'         => 1,
            ];
        }
    }
    foreach ($byBuyer as $pid => $rows) {
        usort($rows, static fn($a, $b) => strcasecmp($a['label'], $b['label']));
        $byBuyer[$pid] = $rows;
    }
    $buyerLabels = [];
    foreach ($byBuyer as $rows) {
        foreach ($rows as $r) {
            $buyerLabels[$r['label']] = true;
        }
    }
    $buyerLabels = array_keys($buyerLabels);
    sort($buyerLabels);
    foreach ($buyerLabels as $i => $lb) {
        $buyerColor[$lb] = $palette[$i % count($palette)];
    }
}

/* Per-product colour (used when products are the bar groups). */
$prodColor = [];
foreach ($chosenIds as $pi => $cp) {
    $prodColor[$cp] = $palette[$pi % count($palette)];
}

/* Market vs market: each market is a bar column, one bar per selected product. */
$mktAgg = [];   // market_id => ['name' =>, 'prod' => [pid => ['avg'=>,'n'=>]]]
$mktTot = [];   // market_id => totals (windows stats across selected products)
if ($chosenIds && $mode === 'markets') {
    foreach (Database::select(
        "SELECT r.market_id, m.market_name, r.product_id,
                AVG(r.normalized_price) AS avg_price, COUNT(*) AS n
           FROM price_reports r
           JOIN markets m ON m.id = r.market_id
          WHERE r.deleted_at IS NULL AND r.product_id IN ({$in})
            AND r.date_reported >= DATE_SUB(NOW(), INTERVAL ? DAY)
          GROUP BY r.market_id, m.market_name, r.product_id
          ORDER BY m.market_name", [$days]
    ) as $row) {
        $mkT = (int)$row['market_id'];
        $mktAgg[$mkT]['name'] = $row['market_name'];
        $mktAgg[$mkT]['prod'][(int)$row['product_id']] = ['avg' => $row['avg_price'], 'n' => (int)$row['n']];
    }
    foreach (Database::select(
        "SELECT r.market_id, COUNT(*) AS n, AVG(r.normalized_price) AS avg_price,
                MIN(r.normalized_price) AS min_price, MAX(r.normalized_price) AS max_price,
                MAX(r.date_reported) AS last_at
           FROM price_reports r
          WHERE r.deleted_at IS NULL AND r.product_id IN ({$in})
            AND r.date_reported >= DATE_SUB(NOW(), INTERVAL ? DAY)
          GROUP BY r.market_id", [$days]
    ) as $row) {
        $mktTot[(int)$row['market_id']] = $row;
    }
}

/* Farmer vs farmer: each farmer is a bar column (one bar per product) whose
 * price is their latest listing, not who recorded the market price. */
$byAgg = [];   // farmer label => ['prod' => [pid => ['avg'=>,'n'=>]]]
$byTot = [];   // farmer label => totals over their latest listings
if ($chosenIds && $mode === 'buyers') {
    foreach ($farmerListings as $fid => $prods) {
        $label  = $farmerNameOf[$fid] ?? ('Farmer #' . $fid);
        $prices = [];
        foreach ($prods as $pid => $row) {
            $byAgg[$label]['prod'][$pid] = ['avg' => $row['normalized_asking_price'], 'n' => 1];
            $prices[] = (float)$row['normalized_asking_price'];
        }
        $lats = array_column($prods, 'created_at');
        $byTot[$label] = [
            'n'         => count($prices),
            'avg_price' => array_sum($prices) / max(1, count($prices)),
            'min_price' => min($prices),
            'max_price' => max($prices),
            'last_at'   => max($lats),
        ];
    }
    ksort($byAgg);
    ksort($byTot);
}

/* Bar geometry: tallest group average in the window = 100%.
 * - product: bars per product, grouped by market (all markets) or buyer (one market)
 * - markets: bars per market, one bar per selected product
 * - buyers:  bars per reporter, one bar per selected product
 * - buyer-mkt: bars per product, [market average] + [selected reporter] side by side */
$groupMode = $marketId ? 'buyer' : 'market';
$groupsOf  = $marketId ? $byBuyer : $byMarket;
$maxAvg = 0.0;
$windowTotal = 0;
foreach ($chosenIds as $pid) {
    $windowTotal += isset($stats[$pid]) ? (int)$stats[$pid]['n'] : 0;
}
$bars        = [];
$legendOrder = [];   // group name => color
if ($mode === 'product') {
    foreach ($groupsOf as $rows) {
        foreach ($rows as $g) {
            $maxAvg = max($maxAvg, (float)$g['avg_price']);
        }
    }
    foreach ($chosenIds as $pid) {
        $items = [];
        foreach ($groupsOf[$pid] ?? [] as $g) {
            $avg = (float)$g['avg_price'];
            $col = $marketId
                ? ($buyerColor[$g['label']] ?? $palette[0])
                : ($marketColor[(int)$g['market_id']] ?? $palette[0]);
            $legendOrder[$g['label']] = $col;
            $items[] = [
                'label' => $g['label'],
                'short' => round($avg),
                'exact' => money($g['avg_price']),
                'n'     => (int)$g['n'],
                'color' => $col,
                'h'     => ($maxAvg > 0) ? max(3, (int)round($avg / $maxAvg * 100)) : 4,
                'ghost' => false,
            ];
        }
        if (!$items) {
            $items[] = ['label' => 'No data', 'short' => null, 'exact' => 'no data', 'n' => 0,
                        'color' => $palette[0], 'h' => 4, 'ghost' => true];
        }
        $bars[] = ['name' => $nameOf[$pid], 'unit' => $unitOf[$pid], 'items' => $items];
    }
}

if ($mode === 'markets') {
    foreach ($mktAgg as $agg) {
        foreach ($agg['prod'] as $pr) { $maxAvg = max($maxAvg, (float)$pr['avg']); }
    }
    $bars = [];
    foreach ($mktAgg as $mkB => $agg) {
        $items = [];
        foreach ($chosenIds as $pid) {
            $pd = $agg['prod'][$pid] ?? null;
            $avg = $pd ? (float)$pd['avg'] : 0.0;
            $col = $prodColor[$pid] ?? $palette[0];
            $legendOrder[$nameOf[$pid]] = $col;
            $items[] = [
                'label' => $nameOf[$pid],
                'short' => $pd ? round($avg) : null,
                'exact' => $pd ? money($pd['avg']) : 'no data',
                'n'     => $pd ? $pd['n'] : 0,
                'color' => $col,
                'h'     => ($pd && $maxAvg > 0) ? max(3, (int)round($avg / $maxAvg * 100)) : 4,
                'ghost' => !$pd,
            ];
        }
        $bars[] = ['name' => $agg['name'], 'unit' => '', 'noUnit' => true, 'items' => $items];
    }
} elseif ($mode === 'buyers') {
    foreach ($byAgg as $agg) {
        foreach ($agg['prod'] as $pr) { $maxAvg = max($maxAvg, (float)$pr['avg']); }
    }
    $bars = [];
    foreach ($byAgg as $lbB => $agg) {
        $items = [];
        foreach ($chosenIds as $pid) {
            $pd = $agg['prod'][$pid] ?? null;
            $avg = $pd ? (float)$pd['avg'] : 0.0;
            $col = $prodColor[$pid] ?? $palette[0];
            $legendOrder[$nameOf[$pid]] = $col;
            $items[] = [
                'label' => $nameOf[$pid],
                'short' => $pd ? round($avg) : null,
                'exact' => $pd ? money($pd['avg']) : 'no data',
                'n'     => $pd ? $pd['n'] : 0,
                'color' => $col,
                'h'     => ($pd && $maxAvg > 0) ? max(3, (int)round($avg / $maxAvg * 100)) : 4,
                'ghost' => !$pd,
            ];
        }
        $bars[] = ['name' => $lbB, 'unit' => '', 'noUnit' => true, 'items' => $items];
    }
} elseif ($mode === 'buyer-mkt') {
    $bmRef = [];
    foreach ($chosenIds as $pid) {
        $mAvg = 0.0; $mN = 0;
        if ($marketId) {
            foreach (($byMarket[$pid] ?? []) as $g) {
                if ((int)$g['market_id'] === $marketId) { $mAvg = (float)$g['avg_price']; $mN = (int)$g['n']; break; }
            }
        } else {
            $s = $stats[$pid] ?? null;
            if ($s) { $mAvg = (float)$s['avg_price']; $mN = (int)$s['n']; }
        }
        $bAvg = 0.0; $bN = 0;
        if ($buyerLabel) {
            foreach (($byBuyer[$pid] ?? []) as $g) {
                if ($g['label'] === $buyerLabel) { $bAvg = (float)$g['avg_price']; $bN = (int)$g['n']; break; }
            }
        }
        $bmRef[$pid] = ['mAvg' => $mAvg, 'mN' => $mN, 'bAvg' => $bAvg, 'bN' => $bN];
        $maxAvg = max($maxAvg, $mAvg, $bAvg);
    }
    $mCol     = $marketId ? ($marketColor[$marketId] ?? '#64748b') : '#64748b';
    $bCol     = $buyerLabel ? ($buyerColor[$buyerLabel] ?? $palette[0]) : $palette[0];
    $mktLabel = $marketId ? $marketNameOf[$marketId] : 'All markets';
    $legendOrder[$mktLabel] = $mCol;
    if ($buyerLabel) { $legendOrder[$buyerLabel] = $bCol; }
    $bars = [];
    foreach ($chosenIds as $pid) {
        $ref = $bmRef[$pid];
        $mkItem = [
            'label' => $mktLabel,
            'short' => $ref['mN'] ? round($ref['mAvg']) : null,
            'exact' => $ref['mN'] ? money($ref['mAvg']) : 'no data',
            'n'     => $ref['mN'],
            'color' => $mCol,
            'h'     => ($ref['mN'] && $maxAvg > 0) ? max(3, (int)round($ref['mAvg'] / $maxAvg * 100)) : 4,
            'ghost' => !$ref['mN'],
        ];
        $byItem = [
            'label' => $buyerLabel ?: 'Buyer',
            'short' => $ref['bN'] ? round($ref['bAvg']) : null,
            'exact' => $ref['bN'] ? money($ref['bAvg']) : 'no data',
            'n'     => $ref['bN'],
            'color' => $bCol,
            'h'     => ($ref['bN'] && $maxAvg > 0) ? max(3, (int)round($ref['bAvg'] / $maxAvg * 100)) : 4,
            'ghost' => !$ref['bN'],
        ];
        $bars[] = ['name' => $nameOf[$pid], 'unit' => $unitOf[$pid], 'items' => [$mkItem, $byItem]];
    }
}
ksort($legendOrder);
$headNote   = $mode === 'markets' ? 'per market'
            : ($mode === 'buyers' ? 'per farmer'
            : ($mode === 'buyer-mkt' ? 'farmer vs market' : 'by ' . $groupMode));
$scopeLabel = $marketNameOf[$marketId] . ' · last ' . $days . ' days';

require __DIR__ . '/dashboard_header.php';
?>

<!-- ================= COMPARE PRICES ======================= -->
<section class="section" id="compare">
  <div class="section-head">
    <h2 class="section-title">⚖️ Compare prices</h2>
    <span class="small muted"><?= e($scopeLabel) ?> · average per base unit</span>
  </div>

  <!-- Product combobox + market / period picker -->
  <div class="table-card cmp-card">
    <form method="get" action="<?= e($actionUrl) ?>" class="cmp-form">
      <input type="hidden" name="p_present" value="1">
      <?php foreach ($chosenIds as $cid): ?>
        <input type="hidden" name="p[]" value="<?= $cid ?>">
      <?php endforeach; ?>
      <div class="cmp-label">Add products to compare</div>
      <div class="cmp-row">
        <label class="cmp-field">Compare by
          <select name="mode" data-autosubmit>
            <option value="product" <?= $mode === 'product' ? 'selected' : '' ?>>Product groups (by market / farmer)</option>
            <option value="markets" <?= $mode === 'markets' ? 'selected' : '' ?>>Market vs market</option>
            <option value="buyers" <?= $mode === 'buyers' ? 'selected' : '' ?>>Farmer vs farmer</option>
            <option value="buyer-mkt" <?= $mode === 'buyer-mkt' ? 'selected' : '' ?>>Farmer vs market</option>
          </select>
        </label>
        <label class="cmp-field cmp-combo">Product
          <select name="p_add" data-autosubmit>
            <option value=""><?= $chosenIds ? 'Choose another product…' : 'Choose a product…' ?></option>
            <?php foreach ($products as $p): $pid = (int)$p['id'];
                  if (isset($chosen[$pid])) { continue; } ?>
              <option value="<?= $pid ?>"><?= e($p['product_name']) ?> / <?= e($p['unit_code']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if ($mode !== 'markets'): ?>
        <label class="cmp-field">Market
          <select name="m" data-autosubmit>
            <option value="0" <?= $marketId === 0 ? 'selected' : '' ?>>All markets</option>
            <?php foreach ($markets as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= $marketId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['market_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
        <?php if ($mode === 'buyer-mkt'): ?>
        <label class="cmp-field">Farmer
          <select name="b" data-autosubmit>
            <?php foreach ($farmers as $fp): $fid2 = (int)$fp['fid']; ?>
              <option value="<?= $fid2 ?>" <?= $bid === $fid2 ? 'selected' : '' ?>><?= e($fp['fname']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
        <label class="cmp-field">Period
          <select name="days" data-autosubmit>
            <?php foreach ([7, 30, 90] as $d): ?>
              <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Last <?= $d ?> days</option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-primary btn-sm" type="submit">Update graph</button>
        <a class="btn btn-ghost btn-sm" href="<?= e($actionUrl) ?>">Reset</a>
      </div>
      <?php if ($chosenIds): ?>
        <div class="cmp-chips">
          <?php foreach ($chosenIds as $cid): ?>
            <span class="cmp-chip">
              <?= e($nameOf[$cid]) ?> <span class="chip-unit">/ <?= e($unitOf[$cid]) ?></span>
              <a class="chip-x" href="<?= e($cmpUrl(array_values(array_diff($chosenIds, [$cid])))) ?>"
                 aria-label="Remove <?= e($nameOf[$cid]) ?> from comparison">✕</a>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Bar graph -->
  <div class="table-card chart-card">
    <div class="chart-head">
      <strong>Average price</strong>
      <span class="small muted">₱ per base unit · <?= $windowTotal ?> report(s) in window · <?= e($headNote) ?></span>
    </div>
    <?php if (!$bars): ?>
      <div class="empty-state"><span class="empty-icon">📊</span><?= $chosenIds ? 'No reports in this window for the chosen products — try a longer period or a different mode.' : 'Choose a product in the list above to start the comparison.' ?></div>
    <?php else: ?>
      <div class="bars">
        <?php foreach ($bars as $b): ?>
          <div class="bar-col">
            <div class="bar-slot bar-cluster">
              <?php foreach ($b['items'] as $it): ?>
                <div class="bar-item<?= $it['ghost'] ? ' ghost' : '' ?>"
                     style="--h:<?= $it['h'] ?>%;--c:<?= $it['color'] ?>"
                     title="<?= e($b['name']) ?> · <?= e($it['label']) ?>: <?= e($it['exact']) ?><?= $it['ghost'] ? '' : ' (' . $it['n'] . ' record(s))' ?>">
                  <span class="bar-val"><?= $it['ghost'] ? '—' : '₱' . number_format($it['short']) ?></span>
                  <i class="bar<?= $it['ghost'] ? ' bar-none' : '' ?>"></i>
                </div>
              <?php endforeach; ?>
            </div>
            <span class="bar-name" title="<?= e($b['name']) ?>"><?= e($b['name']) ?><?php if (!($b['noUnit'] ?? false)): ?> <span class="bar-unit">/ <?= e($b['unit']) ?></span><?php endif; ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($legendOrder): ?>
        <div class="cmp-legend">
          <?php foreach ($legendOrder as $lbName => $lbColor): ?>
            <span class="lg-item"><i class="lg-dot" style="--c:<?= $lbColor ?>"></i><?= e($lbName) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($windowTotal === 0): ?>
        <p class="cmp-hint">No reports in this window<?= $marketId ? ' for ' . e($marketNameOf[$marketId]) : '' ?> — try a longer period or All markets.</p>
      <?php endif ?>
    <?php endif ?>
  </div>

  <!-- Detail table -->
  <div class="table-card">
    <div class="table-wrap">
      <table class="data-table">
      <?php if ($mode === 'markets'): ?>
        <thead>
          <tr><th>Market</th><th>Selected products</th><th>Reports</th><th>Average</th><th>Lowest</th><th>Highest</th><th>Latest</th><th>Last observed</th></tr>
        </thead>
        <tbody>
        <?php if (!$mktAgg): ?>
          <tr><td colspan="8"><div class="empty-state"><span class="empty-icon">⚖️</span>No market data in this window.</div></td></tr>
        <?php else: foreach ($mktAgg as $mkID => $agg): $t = $mktTot[$mkID] ?? null; ?>
          <tr>
            <td class="bold"><?= e($agg['name']) ?></td>
            <td class="cmp-names">
              <?php foreach ($chosenIds as $pid): $pd = $agg['prod'][$pid] ?? null; ?>
                <span class="name-chip"><i style="--c:<?= $prodColor[$pid] ?? $palette[0] ?>"></i><?= e($nameOf[$pid]) ?><?php if ($pd): ?> <b><?= money($pd['avg']) ?></b> <span class="n">×<?= (int)$pd['n'] ?></span><?php else: ?> <span class="n">no data</span><?php endif; ?></span>
              <?php endforeach; ?>
            </td>
            <td><?= $t ? (int)$t['n'] : 0 ?></td>
            <td class="bold" style="color:var(--green-800)"><?= $t ? money($t['avg_price']) : '—' ?></td>
            <td><?= $t ? money($t['min_price']) : '—' ?></td>
            <td><?= $t ? money($t['max_price']) : '—' ?></td>
            <td class="bold"><?= $t ? money($t['avg_price']) : '—' ?></td>
            <td class="small"><?= $t ? e(date('M j, Y · g:i A', strtotime($t['last_at']))) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      <?php elseif ($mode === 'buyers'): ?>
        <thead>
          <tr><th>Farmer</th><th>Selected products</th><th>Listings</th><th>Average</th><th>Lowest</th><th>Highest</th><th>Latest</th><th>Last observed</th></tr>
        </thead>
        <tbody>
        <?php if (!$byAgg): ?>
          <tr><td colspan="8"><div class="empty-state"><span class="empty-icon">⚖️</span>No farmer listings in this window.</div></td></tr>
        <?php else: foreach ($byAgg as $lbT => $agg): $t = $byTot[$lbT] ?? null; ?>
          <tr>
            <td class="bold"><?= e($lbT) ?></td>
            <td class="cmp-names">
              <?php foreach ($chosenIds as $pid): $pd = $agg['prod'][$pid] ?? null; ?>
                <span class="name-chip"><i style="--c:<?= $prodColor[$pid] ?? $palette[0] ?>"></i><?= e($nameOf[$pid]) ?><?php if ($pd): ?> <b><?= money($pd['avg']) ?></b> <span class="n">×<?= (int)$pd['n'] ?></span><?php else: ?> <span class="n">no data</span><?php endif; ?></span>
              <?php endforeach; ?>
            </td>
            <td><?= $t ? (int)$t['n'] : 0 ?></td>
            <td class="bold" style="color:var(--green-800)"><?= $t ? money($t['avg_price']) : '—' ?></td>
            <td><?= $t ? money($t['min_price']) : '—' ?></td>
            <td><?= $t ? money($t['max_price']) : '—' ?></td>
            <td class="bold"><?= $t ? money($t['avg_price']) : '—' ?></td>
            <td class="small"><?= $t ? e(date('M j, Y · g:i A', strtotime($t['last_at']))) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      <?php else: ?>
        <thead>
          <tr><th>Product</th><th>Markets</th><th>Farmer (latest listing)</th><th>Reports</th><th>Average</th><th>Lowest</th><th>Highest</th><th>Latest</th><th>Last observed</th></tr>
        </thead>
        <tbody>
        <?php if (!$bars): ?>
          <tr><td colspan="9"><div class="empty-state"><span class="empty-icon">⚖️</span>No products selected.</div></td></tr>
        <?php else: foreach ($bars as $i => $b): $pid = $chosenIds[$i]; $s = $stats[$pid] ?? null; $l = $latest[$pid] ?? null; ?>
          <tr>
            <td class="bold"><?= e($b['name']) ?> <span class="small muted">/ <?= e($b['unit']) ?></span></td>
            <td class="cmp-names">
              <?php foreach (($byMarket[$pid] ?? []) as $g): ?>
                <span class="name-chip"><i style="--c:<?= $marketColor[(int)$g['market_id']] ?? $palette[0] ?>"></i><?= e($g['label']) ?> <b><?= money($g['avg_price']) ?></b> <span class="n">×<?= (int)$g['n'] ?></span></span>
              <?php endforeach; ?>
              <?= isset($byMarket[$pid]) ? '' : '—' ?>
            </td>
            <td class="cmp-names">
              <?php foreach (($byBuyer[$pid] ?? []) as $g): ?>
                <span class="name-chip"><i style="--c:<?= $buyerColor[$g['label']] ?? $palette[0] ?>"></i><?= e($g['label']) ?> <b><?= money($g['avg_price']) ?></b> <span class="n">×<?= (int)$g['n'] ?></span></span>
              <?php endforeach; ?>
              <?= isset($byBuyer[$pid]) ? '' : '—' ?>
            </td>
            <td><?= $s ? (int)$s['n'] : 0 ?></td>
            <td class="bold" style="color:var(--green-800)"><?= $s ? money($s['avg_price']) : '—' ?></td>
            <td><?= $s ? money($s['min_price']) : '—' ?></td>
            <td><?= $s ? money($s['max_price']) : '—' ?></td>
            <td class="bold"><?= $l ? money($l['latest_price']) : '—' ?></td>
            <td class="small"><?= $l ? e(date('M j, Y · g:i A', strtotime($l['latest_at']))) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      <?php endif; ?>
      </table>
    </div>
  </div>
</section>

<?php require __DIR__ . '/dashboard_footer.php'; ?>
