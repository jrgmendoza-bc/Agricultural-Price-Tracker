<?php
/**
 * dashboard/admin/catalog.php - Product catalog management.
 * Add / edit products. Deleting a product is a hard delete: the row and all
 * dependent listings, price reports and price history are removed from the
 * database (FKs cascade). Price entry lives on the Markets page instead.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');
$pdo  = Database::getConnection();

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // 1) Add product
    if ($action === 'add_product') {
        $name     = trim(post('product_name'));
        $category = post('category');
        $kind     = post('product_kind');
        $baseUnit = (int)post('base_unit_id');
        if ($name === '' || $baseUnit <= 0 || !in_array($kind, ['crop', 'livestock'], true)) {
            flash('Product name, category, kind and base unit are all required.', 'error');
        } else {
            $existing = Database::first("SELECT id FROM products WHERE product_name = ?", [$name]);
            if ($existing) {
                flash('A product with that name already exists.', 'error');
            } else {
                try {
                    Database::execute(
                        "INSERT INTO products (product_name, category, base_unit_id, kind) VALUES (?,?,?,?)",
                        [$name, $category, $baseUnit, $kind]
                    );
                    $newId = (int)$pdo->lastInsertId();
                    Database::execute(
                        "INSERT INTO product_units (product_id, unit_id, factor_to_base, is_default) VALUES (?,?,1,1)",
                        [$newId, $baseUnit]
                    );
                    flash('Product "' . e($name) . '" added to the catalog.', 'success');
                } catch (Throwable $ex) {
                    flash('Could not add the product: ' . $ex->getMessage(), 'error');
                }
            }
        }
        redirect('/dashboard/admin/catalog.php');
    }

    // 2) Update product (name / category / kind / base unit)
    if ($action === 'update_product') {
        $pid      = (int)post('product_id');
        $name     = trim(post('product_name'));
        $category = post('category');
        // field is product_kind: main.js reserves the data-kind trigger key for
        // the listings stage modal, so the trigger cannot target a field named "kind"
        $kind     = post('product_kind');
        $baseUnit = (int)post('base_unit_id');
        $product  = Database::first("SELECT * FROM products WHERE id = ?", [$pid]);
        if (!$product) {
            flash('Product not found.', 'error');
        } elseif ($name === '' || $baseUnit <= 0 || !in_array($kind, ['crop', 'livestock'], true)) {
            flash('Product name, category, kind and base unit are all required.', 'error');
        } elseif (Database::scalar("SELECT COUNT(*) FROM products WHERE product_name = ? AND id <> ?", [$name, $pid])) {
            flash('Another product already uses that name.', 'error');
        } else {
            try {
                Database::execute(
                    "UPDATE products SET product_name = ?, category = ?, kind = ?, base_unit_id = ? WHERE id = ?",
                    [$name, $category, $kind, $baseUnit, $pid]
                );
                // Keep the default conversion row in sync with the base unit
                if ((int)$product['base_unit_id'] !== $baseUnit) {
                    $hasDefault = (int)Database::scalar(
                        "SELECT COUNT(*) FROM product_units WHERE product_id = ? AND is_default = 1",
                        [$pid]
                    );
                    if ($hasDefault) {
                        Database::execute(
                            "UPDATE product_units SET unit_id = ?, factor_to_base = 1 WHERE product_id = ? AND is_default = 1",
                            [$baseUnit, $pid]
                        );
                    } else {
                        Database::execute(
                            "INSERT INTO product_units (product_id, unit_id, factor_to_base, is_default) VALUES (?,?,1,1)",
                            [$pid, $baseUnit]
                        );
                    }
                }
                flash('Product "' . e($name) . '" updated.', 'success');
            } catch (Throwable $ex) {
                flash('Could not update the product: ' . $ex->getMessage(), 'error');
            }
        }
        redirect('/dashboard/admin/catalog.php');
    }

    // 3) Hard delete - removes the product and (via FK cascade) all of its
    //    listings, price reports, snapshots and conversion rows
    if ($action === 'delete_product') {
        $pid     = (int)post('product_id');
        $product = Database::first("SELECT id, product_name FROM products WHERE id = ?", [$pid]);
        if (!$product) {
            flash('Product not found.', 'error');
        } else {
            try {
                Database::execute("DELETE FROM products WHERE id = ?", [$pid]);
                flash('Product "' . e($product['product_name']) . '" and all of its listings, price reports and price history were permanently deleted.', 'success');
            } catch (Throwable $ex) {
                flash('Could not delete the product: ' . $ex->getMessage(), 'error');
            }
        }
        redirect('/dashboard/admin/catalog.php');
    }

    redirect('/dashboard/admin/catalog.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/admin/catalog.php');

/* --- Catalog (search: cq · rows: climit · page: cpage) --- */
$cCtl    = table_controls(['prefix' => 'c', 'action' => $actionUrl, 'default_limit' => 10]);
$cWhere  = '1 = 1';
$cParams = [];
if ($cCtl['q'] !== '') {
    $cWhere .= " AND (p.product_name LIKE ? OR p.category LIKE ?)";
    $like = '%' . $cCtl['q'] . '%';
    array_push($cParams, $like, $like);
}
$cCtl = table_paginate($cCtl, (int)Database::scalar("SELECT COUNT(*) FROM products p WHERE {$cWhere}", $cParams));
$catalogRows = Database::select(
    "SELECT p.id, p.product_name, p.category, p.kind, p.base_unit_id, p.deleted_at,
            u.code AS base_unit, COALESCE(r.n, 0) AS report_count
       FROM products p JOIN units u ON u.id = p.base_unit_id
       LEFT JOIN (SELECT product_id, COUNT(*) AS n
                    FROM price_reports WHERE deleted_at IS NULL GROUP BY product_id) r
              ON r.product_id = p.id
      WHERE {$cWhere}
      ORDER BY p.deleted_at IS NOT NULL, p.category, p.product_name
      LIMIT {$cCtl['limit']} OFFSET {$cCtl['offset']}",
    $cParams
);
$unitsList  = Database::select("SELECT id, code, name FROM units WHERE deleted_at IS NULL ORDER BY name");
$categories = Database::select("SELECT name FROM categories WHERE deleted_at IS NULL ORDER BY name");

$pageTitle     = 'Catalog';
$activeSection = 'catalog';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ================= CATALOG ============================= -->
<section class="section" id="catalog">
  <div class="section-head">
    <h2 class="section-title">🗂️ Product catalog</h2>
    <div class="flex" style="gap:12px;align-items:center">
      <span class="small muted"><?= $cCtl['total'] ?> tracked commodities</span>
      <button class="btn btn-primary btn-sm" type="button" data-modal-open="modal-add-product">＋ Add product</button>
    </div>
  </div>

  <div class="table-card">
    <?= table_search_bar($cCtl, 'Search product or category…') ?>
    <div class="table-wrap">
      <table class="data-table" style="min-width:900px">
        <thead>
          <tr><th>Product</th><th>Category</th><th>Kind</th><th>Base unit</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (!$catalogRows): ?>
            <tr><td colspan="5"><div class="empty-state"><span class="empty-icon">🗂️</span>No products match this search.</div></td></tr>
          <?php else: foreach ($catalogRows as $p): ?>
              <tr>
                <td class="bold"><?= e($p['product_name']) ?>
                  <br><span class="small muted">#<?= $p['id'] ?> · <?= (int)$p['report_count'] ?> price report<?= (int)$p['report_count'] === 1 ? '' : 's' ?></span></td>
                <td><span class="badge badge-slate"><?= e($p['category']) ?></span></td>
                <td><span class="badge badge-<?= $p['kind'] === 'livestock' ? 'amber' : 'green' ?>"><?= e($p['kind']) ?></span></td>
                <td>₱ per <?= e($p['base_unit']) ?></td>
                <td style="white-space:nowrap">
                  <button class="btn btn-outline btn-sm" type="button"
                          data-modal-open="modal-edit-product"
                          data-product-id="<?= $p['id'] ?>"
                          data-product-name="<?= e($p['product_name']) ?>"
                          data-category="<?= e($p['category']) ?>"
                          data-product-kind="<?= e($p['kind']) ?>"
                          data-base-unit-id="<?= (int)$p['base_unit_id'] ?>">Edit</button>
                  <button class="btn btn-danger btn-sm" type="button"
                          data-modal-open="modal-delete-product"
                          data-product-id="<?= $p['id'] ?>"
                          data-fill-name="<?= e($p['product_name']) ?>">Delete</button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($cCtl) ?>
  </div>
  <p class="small muted" style="margin-top:10px">Deleting a product is permanent: the product row, its listings,
    price reports and price history are removed from the database and cannot be restored.</p>
</section>

<!-- ================= CRUD MODALS ======================== -->
<?= modal_open('modal-add-product', 'Add product') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_product">
    <div class="form-group">
      <label>Product name</label>
      <input type="text" name="product_name" required maxlength="120" placeholder="e.g. Squash">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Category</label>
        <select name="category" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Kind</label>
        <select name="product_kind" required>
          <option value="crop">crop</option>
          <option value="livestock">livestock</option>
        </select>
      </div>
      <div class="form-group">
        <label>Base unit</label>
        <select name="base_unit_id" required>
          <?php foreach ($unitsList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Add product</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-edit-product', 'Edit product') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_product">
    <input type="hidden" name="product_id" value="">
    <div class="form-group">
      <label>Product name</label>
      <input type="text" name="product_name" required maxlength="120" placeholder="e.g. Squash">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Category</label>
        <select name="category" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Kind</label>
        <select name="product_kind" required>
          <option value="crop">crop</option>
          <option value="livestock">livestock</option>
        </select>
      </div>
      <div class="form-group">
        <label>Base unit</label>
        <select name="base_unit_id" required>
          <?php foreach ($unitsList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-delete-product', 'Confirm product deletion', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_product">
    <input type="hidden" name="product_id" value="">
    <p class="modal-note">
      You are about to permanently delete <strong data-fill="name">this product</strong>.
      The product is removed from the database together with all of its listings,
      price reports and price history. This cannot be undone.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-danger" type="submit" data-modal-confirm>Delete product</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
