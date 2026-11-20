<?php
/**
 * dashboard/admin/categories.php - Category CRUD (create, rename, archive / restore).
 * Renames cascade to every product via the FK on products.category
 * (ON UPDATE CASCADE); archiving hides a category from pickers while
 * products that already use it keep the name.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // 1) Create category
    if ($action === 'add_category') {
        $name = trim(post('category_name'));
        if ($name === '') {
            flash('Category name is required.', 'error');
        } elseif (mb_strlen($name) > 80) {
            flash('Category name is too long (max 80 characters).', 'error');
        } else {
            $existing = Database::first("SELECT id, deleted_at FROM categories WHERE name = ?", [$name]);
            if ($existing && $existing['deleted_at'] !== null) {
                flash('That name belongs to an archived category — restore it instead.', 'error');
            } elseif ($existing) {
                flash('A category with that name already exists.', 'error');
            } else {
                try {
                    Database::execute("INSERT INTO categories (name) VALUES (?)", [$name]);
                    flash('Category "' . e($name) . '" added.', 'success');
                } catch (Throwable $ex) {
                    flash('Could not add the category: ' . $ex->getMessage(), 'error');
                }
            }
        }
        redirect('/dashboard/admin/categories.php');
    }

    // 2) Rename category (products follow automatically via FK cascade)
    if ($action === 'update_category') {
        $id   = (int)post('category_id');
        $name = trim(post('category_name'));
        $cat  = Database::first("SELECT id, name FROM categories WHERE id = ?", [$id]);
        if (!$cat) {
            flash('Category not found.', 'error');
        } elseif ($name === '') {
            flash('Category name is required.', 'error');
        } elseif (mb_strlen($name) > 80) {
            flash('Category name is too long (max 80 characters).', 'error');
        } elseif (Database::scalar("SELECT COUNT(*) FROM categories WHERE name = ? AND id <> ?", [$name, $id])) {
            flash('Another category already uses that name.', 'error');
        } else {
            try {
                $moved = (int)Database::scalar("SELECT COUNT(*) FROM products WHERE category = ?", [$cat['name']]);
                Database::execute("UPDATE categories SET name = ? WHERE id = ?", [$name, $id]);
                flash('Category renamed to "' . e($name) . '"'
                    . ($moved ? ' — ' . $moved . ' product' . ($moved === 1 ? '' : 's') . ' updated automatically.' : '.'), 'success');
            } catch (Throwable $ex) {
                flash('Could not rename the category: ' . $ex->getMessage(), 'error');
            }
        }
        redirect('/dashboard/admin/categories.php');
    }

    // 3) Archive / restore
    if ($action === 'toggle_category') {
        $id   = (int)post('category_id');
        $cat  = Database::first("SELECT id, name, deleted_at FROM categories WHERE id = ?", [$id]);
        if (!$cat) {
            flash('Category not found.', 'error');
        } elseif ($cat['deleted_at'] === null) {
            Database::execute("UPDATE categories SET deleted_at = NOW() WHERE id = ?", [$id]);
            flash('Category "' . e($cat['name']) . '" archived. Products that use it are unaffected.', 'success');
        } else {
            Database::execute("UPDATE categories SET deleted_at = NULL WHERE id = ?", [$id]);
            flash('Category "' . e($cat['name']) . '" restored.', 'success');
        }
        redirect('/dashboard/admin/categories.php');
    }

    redirect('/dashboard/admin/categories.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/admin/categories.php');

/* --- Categories (search: ctq · rows: ctlimit · page: ctpage) --- */
$ctCtl    = table_controls(['prefix' => 'ct', 'action' => $actionUrl, 'default_limit' => 10]);
$ctWhere  = '1 = 1';
$ctParams = [];
if ($ctCtl['q'] !== '') {
    $ctWhere .= ' AND c.name LIKE ?';
    $ctParams[] = '%' . $ctCtl['q'] . '%';
}
$ctCtl = table_paginate($ctCtl, (int)Database::scalar("SELECT COUNT(*) FROM categories c WHERE {$ctWhere}", $ctParams));
$categories = Database::select(
    "SELECT c.id, c.name, c.deleted_at, c.created_at, COALESCE(p.n, 0) AS product_count
       FROM categories c
       LEFT JOIN (SELECT category, COUNT(*) AS n
                    FROM products WHERE deleted_at IS NULL GROUP BY category) p
              ON p.category = c.name
      WHERE {$ctWhere}
      ORDER BY c.deleted_at IS NOT NULL, c.name
      LIMIT {$ctCtl['limit']} OFFSET {$ctCtl['offset']}",
    $ctParams
);

$pageTitle     = 'Categories';
$activeSection = 'categories';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ================= CATEGORIES ========================== -->
<section class="section" id="categories">
  <div class="section-head">
    <h2 class="section-title">🏷️ Categories</h2>
    <div class="flex" style="gap:12px;align-items:center">
      <span class="small muted"><?= $ctCtl['total'] ?> categor<?= $ctCtl['total'] === 1 ? 'y' : 'ies' ?></span>
      <button class="btn btn-primary btn-sm" type="button" data-modal-open="modal-add-category">＋ Add category</button>
    </div>
  </div>

  <div class="table-card">
    <?= table_search_bar($ctCtl, 'Search category…') ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Category</th><th>Products</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (!$categories): ?>
            <tr><td colspan="4"><div class="empty-state"><span class="empty-icon">🏷️</span>No categories match this search.</div></td></tr>
          <?php else: foreach ($categories as $c): ?>
              <tr<?= $c['deleted_at'] ? ' style="opacity:.65"' : '' ?>>
                <td>
                  <span class="bold"><?= e($c['name']) ?></span><br>
                  <span class="small muted">added <?= e(date('M j, Y', strtotime($c['created_at']))) ?></span>
                </td>
                <td><?= (int)$c['product_count'] ?></td>
                <td><span class="badge badge-<?= $c['deleted_at'] ? 'slate' : 'green' ?>"><?= $c['deleted_at'] ? 'archived' : 'active' ?></span></td>
                <td style="white-space:nowrap">
                  <button class="btn btn-outline btn-sm" type="button"
                          data-modal-open="modal-edit-category"
                          data-category-id="<?= $c['id'] ?>"
                          data-category-name="<?= e($c['name']) ?>">Edit</button>
                  <button class="btn <?= $c['deleted_at'] ? 'btn-outline' : 'btn-danger' ?> btn-sm" type="button"
                          data-modal-open="modal-toggle-category"
                          data-category-id="<?= $c['id'] ?>"
                          data-fill-name="<?= e($c['name']) ?>"
                          data-confirm-label="<?= $c['deleted_at'] ? 'Restore category' : 'Archive category' ?>"
                          data-confirm-class="btn <?= $c['deleted_at'] ? 'btn-outline' : 'btn-danger' ?> btn-sm">
                    <?= $c['deleted_at'] ? 'Restore' : 'Archive' ?>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($ctCtl) ?>
  </div>
  <p class="small muted" style="margin-top:10px">Renaming a category updates every product that uses it automatically.
    Archiving hides it from pickers while existing products keep the name — restore it at any time.</p>
</section>

<!-- ================= CRUD MODALS ======================== -->
<?= modal_open('modal-add-category', 'Add category', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_category">
    <div class="form-group">
      <label>Category name</label>
      <input type="text" name="category_name" required maxlength="80" placeholder="e.g. Root Crops">
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Add category</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-edit-category', 'Rename category', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_category">
    <input type="hidden" name="category_id" value="">
    <p class="modal-note">
      Renaming <strong data-fill="name">this category</strong> updates every product that uses it
      automatically (foreign-key cascade).
    </p>
    <div class="form-group">
      <label>Category name</label>
      <input type="text" name="category_name" required maxlength="80">
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-toggle-category', 'Confirm category change', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle_category">
    <input type="hidden" name="category_id" value="">
    <p class="modal-note">
      You are about to change the status of <strong data-fill="name">this category</strong>.
      Archiving hides it from pickers — products that already use it are unaffected and the
      category can be restored at any time.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-danger" type="submit" data-modal-confirm>Archive category</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
