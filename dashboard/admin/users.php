<?php
/**
 * dashboard/admin/users.php - User management (activate / deactivate).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // User management: soft-delete (deactivate) / restore
    if ($action === 'toggle_user') {
        $targetId = (int)post('user_id');
        if ($targetId === (int)$user['id']) {
            flash('You cannot deactivate your own account.', 'error');
            redirect('/dashboard/admin/users.php');
        }
        $target = Database::first("SELECT * FROM users WHERE id = ?", [$targetId]);
        if (!$target) {
            flash('User not found.', 'error');
        } elseif ($target['deleted_at'] === null) {
            Database::execute("UPDATE users SET deleted_at = NOW() WHERE id = ?", [$targetId]);
            flash('Account deactivated. Their listings, reports and messages are preserved.', 'success');
        } else {
            Database::execute("UPDATE users SET deleted_at = NULL WHERE id = ?", [$targetId]);
            flash('Account restored.', 'success');
        }
        redirect('/dashboard/admin/users.php');
    }

    // Create a new administrator account (mirrors register.php validation)
    if ($action === 'add_admin') {
        $firstName = trim(post('first_name'));
        $lastName  = trim(post('last_name'));
        $username  = trim(post('username'));
        $email     = trim(post('email'));
        $password  = post('password');
        $confirm   = post('password_confirm');

        if ($firstName === '' || $lastName === '' || $username === '' || $email === '') {
            flash('Please complete all required fields.', 'error');
        } elseif (!preg_match('/^[^0-9]+$/', $firstName) || !preg_match('/^[^0-9]+$/', $lastName)) {
            flash('First and last name must not contain any numbers.', 'error');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid email address.', 'error');
        } elseif (strlen($username) < 3 || strlen($username) > 60 || str_contains($username, '@')) {
            flash('Username must be 3–60 characters and cannot contain an @ sign.', 'error');
        } elseif (strlen($password) < 8) {
            flash('Password must be at least 8 characters long.', 'error');
        } elseif ($password !== $confirm) {
            flash('Password confirmation does not match.', 'error');
        } elseif ((int)Database::scalar(
            "SELECT COUNT(*) FROM users WHERE email = ? OR username = ?",
            [$email, $username]
        ) > 0) {
            flash('An account with that email or username already exists.', 'error');
        } else {
            Database::execute(
                "INSERT INTO users (first_name, last_name, username, email, password, role,
                                    email_verified_at, verification_status, verified_at, verified_by)
                 VALUES (?,?,?,?,?, 'admin', NOW(), 'verified', NOW(), ?)",
                [$firstName, $lastName, $username, $email, password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]
            );
            flash('Administrator account created for ' . e($firstName . ' ' . $lastName)
                . ' — they can sign in right away.', 'success');
        }
        redirect('/dashboard/admin/users.php');
    }

    redirect('/dashboard/admin/users.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/admin/users.php');

/* --- User management (search: q · role filter · rows: limit · page) --- */
$userQuery = get('q');
$userRole  = get('role');
$uCtl   = table_controls(['action' => $actionUrl, 'default_limit' => 10, 'keep' => ['role' => $userRole]]);
$uWhere = "1 = 1";
$uParams = [];
if ($userQuery !== '') {
    $uWhere .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $like = "%{$userQuery}%";
    array_push($uParams, $like, $like, $like, $like);
}
if (in_array($userRole, ['farmer', 'buyer', 'admin'], true)) {
    $uWhere .= " AND role = ?";
    $uParams[] = $userRole;
}
$uCtl = table_paginate($uCtl, (int)Database::scalar("SELECT COUNT(*) FROM users WHERE {$uWhere}", $uParams));
$userList = Database::select(
    "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.role, u.verification_status, u.deleted_at, u.created_at,
            (SELECT COUNT(*) FROM verification_documents d WHERE d.user_id = u.id) AS doc_count
       FROM users u
      WHERE {$uWhere}
      ORDER BY u.deleted_at IS NOT NULL, u.created_at DESC
      LIMIT {$uCtl['limit']} OFFSET {$uCtl['offset']}",
    $uParams
);

$pageTitle     = 'User management';
$activeSection = 'users';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ================= USER MANAGEMENT ====================== -->
<section class="section" id="users">
  <div class="section-head">
    <h2 class="section-title">👥 User management</h2>
    <div class="flex" style="gap:12px;align-items:center">
      <span class="small muted"><?= $uCtl['total'] ?> account<?= $uCtl['total'] === 1 ? '' : 's' ?> match this filter</span>
      <button class="btn btn-primary btn-sm" type="button" data-modal-open="modal-add-admin">＋ Add admin</button>
    </div>
  </div>

  <div class="table-card">
    <form class="table-bar" method="get" action="<?= $actionUrl ?>">
      <input class="table-search" type="search" name="q" value="<?= e($userQuery) ?>" placeholder="Search name, email, username…">
      <select name="role" data-autosubmit aria-label="Role" style="width:auto;padding:8px 10px;font-size:.88rem">
        <option value="">All roles</option>
        <option value="farmer" <?= $userRole === 'farmer' ? 'selected' : '' ?>>Farmer</option>
        <option value="buyer"  <?= $userRole === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
        <option value="admin"  <?= $userRole === 'admin'  ? 'selected' : '' ?>>Admin</option>
      </select>
      <span class="table-rows">Rows
        <select name="limit" data-autosubmit aria-label="Rows per page">
          <?php foreach ([10, 25, 50, 100] as $n): ?>
            <option value="<?= $n ?>" <?= $n === $uCtl['limit'] ? 'selected' : '' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </span>
      <button class="btn btn-outline btn-sm" type="submit">Search</button>
      <span class="table-count"><?= $uCtl['total'] ?> row<?= $uCtl['total'] === 1 ? '' : 's' ?></span>
    </form>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Verification</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php if (!$userList): ?>
          <tr><td colspan="7"><div class="empty-state"><span class="empty-icon">🔍</span>No users match this filter.</div></td></tr>
        <?php else: foreach ($userList as $u): ?>
          <tr>
            <td class="bold"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td><?= e($u['username'] ?? '—') ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge badge-<?= $u['role'] === 'admin' ? 'red' : ($u['role'] === 'buyer' ? 'amber' : 'green') ?>"><?= e(ucfirst($u['role'])) ?></span></td>
            <td>
              <?php if ($u['role'] === 'admin'): ?>
                <span class="badge badge-slate">staff</span>
              <?php else: ?>
                <span class="badge badge-<?= $u['verification_status'] === 'verified' ? 'green' : ($u['verification_status'] === 'pending' ? 'amber' : 'red') ?>"><?= e($u['verification_status']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['deleted_at']): ?>
                <span class="badge badge-slate">deactivated</span>
              <?php else: ?>
                <span class="badge badge-green">active</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions" style="flex-wrap:wrap;gap:6px">
              <?php if ($u['role'] !== 'admin'): ?>
                <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/admin/verify-account.php?user_id=' . (int)$u['id']) ?>"
                   title="View account details and uploaded documents">
                  📄 View details<?= (int)$u['doc_count'] > 0 ? ' (' . (int)$u['doc_count'] . ')' : '' ?>
                </a>
              <?php endif; ?>
              <?php if ((int)$u['id'] === (int)$user['id']): ?>
                <span class="small muted">that's you</span>
              <?php else: ?>
                <button class="btn <?= $u['deleted_at'] ? 'btn-outline' : 'btn-danger' ?> btn-sm" type="button"
                        data-modal-open="modal-toggle-user" data-user-id="<?= $u['id'] ?>"
                        data-fill-name="<?= e($u['first_name'] . ' ' . $u['last_name']) ?>"
                        data-confirm-label="<?= $u['deleted_at'] ? 'Restore account' : 'Deactivate account' ?>"
                        data-confirm-class="btn <?= $u['deleted_at'] ? 'btn-outline' : 'btn-danger' ?> btn-sm">
                  <?= $u['deleted_at'] ? 'Restore' : 'Deactivate' ?>
                </button>
              <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($uCtl) ?>
  </div>
  <p class="small muted" style="margin-top:10px">Deactivation is a soft delete: listings, reports and messages of the account are preserved.</p>
</section>

<!-- ================= CRUD MODALS ======================== -->
<?= modal_open('modal-add-admin', 'Add administrator account') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_admin">
    <div class="form-grid">
      <div class="form-group">
        <label>First name</label>
        <input type="text" name="first_name" required maxlength="100" pattern="[^0-9]+" title="Name must not contain any numbers" placeholder="e.g. Maria">
      </div>
      <div class="form-group">
        <label>Last name</label>
        <input type="text" name="last_name" required maxlength="100" pattern="[^0-9]+" title="Name must not contain any numbers" placeholder="e.g. Santos">
      </div>
    </div>
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" required maxlength="60" placeholder="e.g. mariasantos">
      <div class="hint">3–60 characters, no @ sign. Used to sign in.</div>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required maxlength="190" placeholder="admin@example.com">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
      </div>
      <div class="form-group">
        <label>Confirm password</label>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password" placeholder="Repeat password">
      </div>
    </div>
    <p class="modal-note">
      The account receives full administrator access: price data, user management,
      buyer verification decisions and the help channel. Its email is marked verified,
      so the new admin can sign in immediately — share the credentials over a safe channel.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Create admin account</button>
    </div>
  </form>
<?= modal_close() ?>

<?= modal_open('modal-toggle-user', 'Confirm account change', 'sm') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle_user">
    <input type="hidden" name="user_id" value="">
    <p class="modal-note">
      You are about to change the status of <strong data-fill="name">this account</strong>.
      Deactivation is a soft delete — listings, reports and messages are preserved and
      the account can be restored at any time.
    </p>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-danger" type="submit" data-modal-confirm>Deactivate account</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
