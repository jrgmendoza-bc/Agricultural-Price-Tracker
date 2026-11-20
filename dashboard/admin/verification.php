<?php
/**
 * dashboard/admin/verification.php - verification queue for farmers and buyers.
 * Documents are reviewed on verify-account.php?user_id=… (Approve / Reject).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/admin/verification.php');
$pending    = (int)Database::scalar(
    "SELECT COUNT(*) FROM users
      WHERE role IN ('buyer','farmer') AND verification_status = 'pending' AND deleted_at IS NULL"
);

/* --- Verification queue (search: vq · rows: vlimit · page: vpage) --- */
$vCtl   = table_controls(['prefix' => 'v', 'action' => $actionUrl, 'default_limit' => 10]);
$vWhere = "u.role IN ('buyer','farmer') AND u.verification_status = 'pending' AND u.deleted_at IS NULL";
$vParams = [];
if ($vCtl['q'] !== '') {
    $vWhere .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $vCtl['q'] . '%';
    array_push($vParams, $like, $like, $like);
}
$vCtl = table_paginate($vCtl, (int)Database::scalar("SELECT COUNT(*) FROM users u WHERE {$vWhere}", $vParams));
$pendingUsers = Database::select(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.created_at,
            (SELECT COUNT(*) FROM verification_documents d WHERE d.user_id = u.id) AS doc_count
       FROM users u
      WHERE {$vWhere}
      ORDER BY u.created_at ASC
      LIMIT {$vCtl['limit']} OFFSET {$vCtl['offset']}",
    $vParams
);

$pageTitle     = 'Account verification';
$activeSection = 'verification';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ================= VERIFICATION QUEUE =================== -->
<section class="section" id="verification">
  <div class="section-head">
    <h2 class="section-title">🛡️ Account verification queue</h2>
    <span class="badge badge-amber"><?= $pending ?> awaiting review</span>
  </div>
  <div class="table-card">
    <?= table_search_bar($vCtl, 'Search name or email…') ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Role</th><th>Email</th><th>Documents</th><th>Registered</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php if (!$pendingUsers): ?>
          <tr><td colspan="6"><div class="empty-state"><span class="empty-icon">✅</span>No farmer or buyer accounts are waiting for verification.</div></td></tr>
        <?php else: foreach ($pendingUsers as $b): ?>
          <tr>
            <td class="bold"><?= e($b['first_name'] . ' ' . $b['last_name']) ?></td>
            <td><span class="badge badge-<?= $b['role'] === 'farmer' ? 'green' : 'slate' ?>"><?= e($b['role']) ?></span></td>
            <td><?= e($b['email']) ?></td>
            <td>
              <?php if ((int)$b['doc_count'] > 0): ?>
                <span class="badge badge-green"><?= (int)$b['doc_count'] ?> file<?= (int)$b['doc_count'] === 1 ? '' : 's' ?></span>
              <?php else: ?>
                <span class="badge badge-red">none</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e(date('M j, Y', strtotime($b['created_at']))) ?></td>
            <td>
              <div class="row-actions">
                <a class="btn btn-primary btn-sm" href="<?= base_url('dashboard/admin/verify-account.php?user_id=' . (int)$b['id']) ?>">Review</a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($vCtl) ?>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
