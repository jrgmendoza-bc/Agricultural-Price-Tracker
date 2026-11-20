<?php
/**
 * dashboard/admin/help.php - Help channel inbox for administrators: one
 * aggregated list of inquiries from both farmers and buyers, with reply
 * and close actions (the user side lives in dashboard/client/help.php and
 * dashboard/buyer/help.php).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user    = Auth::requireRole('admin');
$adminId = (int)$user['id'];

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $inqId  = (int)post('inquiry_id');

    if ($action === 'reply_inquiry') {
        $result = HelpDesk::reply($inqId, $adminId, (string)($_POST['body'] ?? ''));
        flash($result['ok'] ? 'Reply sent.' : $result['error'], $result['ok'] ? 'success' : 'error');
        redirect('/dashboard/admin/help.php?inquiry=' . $inqId . '#help-thread');
    }
    if ($action === 'close_inquiry') {
        $closed = HelpDesk::close($inqId, $adminId);
        flash($closed ? 'Inquiry closed.' : 'Inquiry not found.', $closed ? 'success' : 'error');
        redirect('/dashboard/admin/help.php?inquiry=' . $inqId . '#help-thread');
    }

    redirect('/dashboard/admin/help.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/admin/help.php');

$roleFilter   = get('role');
$statusFilter = get('status');
if (!in_array($roleFilter, ['farmer', 'buyer'], true)) {
    $roleFilter = '';
}
if (!in_array($statusFilter, ['open', 'answered', 'closed'], true)) {
    $statusFilter = '';
}

/* Active thread — opened first so markRead() clears the badge before the
 * list below is counted. */
$inquiryId = (int)get('inquiry');
$activeInquiry = $inquiryId > 0 ? HelpDesk::find($inquiryId) : null;
$inquiryMessages = [];
if ($activeInquiry) {
    HelpDesk::markRead($inquiryId, $adminId);
    $inquiryMessages = HelpDesk::messages($inquiryId);
}

/* Filter counters (unfiltered totals). */
$roleCounts   = ['farmer' => 0, 'buyer' => 0];
$statusCounts = ['open' => 0, 'answered' => 0, 'closed' => 0];
foreach (Database::select(
    "SELECT u.role, i.status, COUNT(*) AS n
       FROM support_inquiries i
       JOIN users u ON u.id = i.user_id
      GROUP BY u.role, i.status"
) as $c) {
    if (isset($roleCounts[$c['role']])) { $roleCounts[$c['role']] += (int)$c['n']; }
    if (isset($statusCounts[$c['status']])) { $statusCounts[$c['status']] += (int)$c['n']; }
}
$totalAll = array_sum($statusCounts);

$hCtl = table_controls([
    'prefix'        => 'h',
    'action'        => $actionUrl,
    'default_limit' => 10,
    'keep'          => ['role' => $roleFilter, 'status' => $statusFilter],
]);

$hWhere  = '1=1';
$hParams = [];
if ($roleFilter !== '') {
    $hWhere .= ' AND u.role = ?';
    $hParams[] = $roleFilter;
}
if ($statusFilter !== '') {
    $hWhere .= ' AND i.status = ?';
    $hParams[] = $statusFilter;
}
if ($hCtl['q'] !== '') {
    $hWhere .= ' AND (i.subject LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = '%' . $hCtl['q'] . '%';
    array_push($hParams, $like, $like, $like, $like);
}

$hCtl = table_paginate($hCtl, (int)Database::scalar(
    "SELECT COUNT(*) FROM support_inquiries i
       JOIN users u ON u.id = i.user_id
      WHERE {$hWhere}",
    $hParams
));
$inquiries = Database::select(
    "SELECT i.id, i.subject, i.status, i.last_message_at, i.created_at,
            u.first_name, u.last_name, u.username, u.email, u.role AS requester_role,
            (SELECT COUNT(*) FROM support_messages m WHERE m.support_inquiry_id = i.id
               AND m.read_at IS NULL AND m.sender_id <> ?) AS unread
       FROM support_inquiries i
       JOIN users u ON u.id = i.user_id
      WHERE {$hWhere}
      ORDER BY COALESCE(i.last_message_at, i.created_at) DESC
      LIMIT {$hCtl['limit']} OFFSET {$hCtl['offset']}",
    array_merge([$adminId], $hParams)
);

/* Chip / row links keep the active filters and search. */
$qsFor = static function (array $extra = []) use ($actionUrl, $hCtl, $roleFilter, $statusFilter): string {
    $qs = [];
    if ($hCtl['q'] !== '') { $qs['hq'] = $hCtl['q']; }
    if ($hCtl['limit'] !== 10) { $qs['hlimit'] = $hCtl['limit']; }
    if ($roleFilter !== '') { $qs['role'] = $roleFilter; }
    if ($statusFilter !== '') { $qs['status'] = $statusFilter; }
    foreach ($extra as $k => $v) { $qs[$k] = $v; }
    return $actionUrl . ($qs ? '?' . http_build_query($qs) : '');
};

$pageTitle     = 'Help channel';
$activeSection = 'help';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ======================= HELP CHANNEL =================== -->
<section class="section" id="help">
  <div class="section-head">
    <h2 class="section-title">🆘 Help channel</h2>
    <span class="small muted">
      Inquiries from farmers and buyers · <?= (int)$statusCounts['open'] ?> open · <?= $totalAll ?> total
    </span>
  </div>

  <!-- Filter chips: requester role + status -->
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
    <a class="btn btn-sm <?= $roleFilter === '' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($qsFor(['role' => null, 'status' => null])) ?>">All <span class="nav-badge"><?= $totalAll ?></span></a>
    <a class="btn btn-sm <?= $roleFilter === 'farmer' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($qsFor(['role' => 'farmer', 'status' => null])) ?>">🌾 Farmers <span class="nav-badge"><?= (int)$roleCounts['farmer'] ?></span></a>
    <a class="btn btn-sm <?= $roleFilter === 'buyer' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($qsFor(['role' => 'buyer', 'status' => null])) ?>">🛒 Buyers <span class="nav-badge"><?= (int)$roleCounts['buyer'] ?></span></a>
    <span style="width:1px;background:var(--line,rgba(148,163,184,.35));margin:2px 4px"></span>
    <a class="btn btn-sm <?= $statusFilter === 'open' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($qsFor(['role' => null, 'status' => 'open'])) ?>">🟡 Open <span class="nav-badge"><?= (int)$statusCounts['open'] ?></span></a>
    <a class="btn btn-sm <?= $statusFilter === 'answered' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($qsFor(['role' => null, 'status' => 'answered'])) ?>">🟢 Answered <span class="nav-badge"><?= (int)$statusCounts['answered'] ?></span></a>
    <a class="btn btn-sm <?= $statusFilter === 'closed' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($qsFor(['role' => null, 'status' => 'closed'])) ?>">⚪ Closed <span class="nav-badge"><?= (int)$statusCounts['closed'] ?></span></a>
  </div>

  <div class="table-card">
    <?= table_search_bar($hCtl, 'Search subject, name, username or email…') ?>
    <div class="table-wrap">
      <table class="data-table" style="min-width:820px">
        <thead>
          <tr><th>Subject</th><th>Requester</th><th>Role</th><th>Status</th><th>Updated</th></tr>
        </thead>
        <tbody>
        <?php if (!$inquiries): ?>
          <tr><td colspan="5"><div class="empty-state"><span class="empty-icon">🆘</span>No inquiries match this filter.</div></td></tr>
        <?php else: foreach ($inquiries as $inq): ?>
          <tr style="cursor:pointer" onclick="location.href='<?= e($qsFor(['inquiry' => (int)$inq['id']])) ?><?= $activeInquiry && (int)$activeInquiry['id'] === (int)$inq['id'] ? '#help-thread' : '' ?>'">
            <td class="bold"><?= e($inq['subject']) ?>
              <?php if ((int)$inq['unread'] > 0): ?><span class="nav-badge"><?= (int)$inq['unread'] ?></span><?php endif; ?></td>
            <td><?= e(trim(($inq['first_name'] ?? '') . ' ' . ($inq['last_name'] ?? '')) ?: $inq['username']) ?>
              <div class="small muted"><?= e($inq['email']) ?></div></td>
            <td><span class="badge badge-<?= $inq['requester_role'] === 'farmer' ? 'green' : 'slate' ?>"><?= $inq['requester_role'] === 'farmer' ? '🌾' : '🛒' ?> <?= e($inq['requester_role']) ?></span></td>
            <td><span class="badge badge-<?= $inq['status'] === 'open' ? 'amber' : ($inq['status'] === 'answered' ? 'green' : 'slate') ?>"><?= e($inq['status']) ?></span></td>
            <td class="small"><?= e(time_ago($inq['last_message_at'] ?? $inq['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= table_pager($hCtl) ?>
  </div>

  <?php if ($activeInquiry): ?>
    <div class="card" style="margin-top:16px" id="help-thread">
      <div class="card-head">
        <h3 class="card-title">
          💬 <?= e($activeInquiry['subject']) ?>
          <span class="badge badge-<?= $activeInquiry['requester_role'] === 'farmer' ? 'green' : 'slate' ?>">
            <?= $activeInquiry['requester_role'] === 'farmer' ? '🌾' : '🛒' ?> <?= e($activeInquiry['requester_role']) ?>
          </span>
          <span class="badge badge-<?= $activeInquiry['status'] === 'open' ? 'amber' : ($activeInquiry['status'] === 'answered' ? 'green' : 'slate') ?>"><?= e($activeInquiry['status']) ?></span>
        </h3>
        <div style="display:flex;gap:8px;align-items:center">
          <span class="small muted">
            <?= e(trim(($activeInquiry['first_name'] ?? '') . ' ' . ($activeInquiry['last_name'] ?? '')) ?: $activeInquiry['username']) ?>
            · opened <?= e(date('M j, Y', strtotime($activeInquiry['created_at']))) ?>
          </span>
          <?php if ($activeInquiry['status'] !== 'closed'): ?>
            <form method="post" action="<?= $actionUrl ?>?inquiry=<?= (int)$activeInquiry['id'] ?>#help-thread" style="margin:0"
                  data-confirm="The requester will be notified that this inquiry is closed. They can still read the thread, and you can reply to reopen it."
                  data-confirm-title="Close inquiry?"
                  data-confirm-label="Close inquiry">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="close_inquiry">
              <input type="hidden" name="inquiry_id" value="<?= (int)$activeInquiry['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit">📁 Close</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="chat" style="min-height:auto">
        <div class="chat-body" style="max-height:340px">
          <?php foreach ($inquiryMessages as $m): ?>
            <div class="msg <?= (int)$m['sender_id'] === $adminId ? 'mine' : '' ?>">
              <div class="bubble"><?= nl2br(e($m['body'])) ?></div>
              <div class="msg-meta"><?= e($m['first_name']) ?> (<?= e($m['sender_role']) ?>) · <?= e(time_ago($m['created_at'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <form class="chat-form" method="post" action="<?= $actionUrl ?>?inquiry=<?= (int)$activeInquiry['id'] ?>#help-thread">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reply_inquiry">
          <input type="hidden" name="inquiry_id" value="<?= (int)$activeInquiry['id'] ?>">
          <input type="text" name="body" placeholder="<?= $activeInquiry['status'] === 'closed' ? 'Reply to reopen this inquiry…' : 'Reply to the requester…' ?>" required maxlength="2000">
          <button class="btn btn-primary" type="submit">Send</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
