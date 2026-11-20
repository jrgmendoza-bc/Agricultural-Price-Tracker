<?php
/**
 * dashboard/buyer/help.php - Help channel (inquiries to administrators).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('buyer');

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    if ($action === 'new_inquiry') {
        $result = HelpDesk::create((int)$user['id'], post('subject'), (string)($_POST['body'] ?? ''));
        flash($result['ok'] ? 'Inquiry sent to the administrators.' : $result['error'], $result['ok'] ? 'success' : 'error');
        redirect('/dashboard/buyer/help.php');
    }
    if ($action === 'reply_inquiry') {
        $result = HelpDesk::reply((int)post('inquiry_id'), (int)$user['id'], (string)($_POST['body'] ?? ''));
        flash($result['ok'] ? 'Reply sent.' : $result['error'], $result['ok'] ? 'success' : 'error');
        redirect('/dashboard/buyer/help.php?inquiry=' . (int)post('inquiry_id'));
    }

    redirect('/dashboard/buyer/help.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/buyer/help.php');

$inquiryId = (int)get('inquiry');
if ($inquiryId > 0) {
    HelpDesk::markRead($inquiryId, (int)$user['id']);   // clears the unread badge
}
$inquiries = HelpDesk::inquiriesFor((int)$user['id']);
$activeInquiry = null;
$inquiryMessages = [];
if ($inquiryId > 0) {
    foreach ($inquiries as $inq) {
        if ((int)$inq['id'] === $inquiryId) {
            $activeInquiry = $inq;
            $inquiryMessages = HelpDesk::messages($inquiryId);
        }
    }
}

$pageTitle     = 'Help channel';
$activeSection = 'help';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ======================= HELP CHANNEL =================== -->
<section class="section" id="help">
  <div class="section-head"><h2 class="section-title">🆘 Help channel</h2></div>
  <div class="grid-2">
    <div class="card">
      <div class="card-head"><h3 class="card-title">My inquiries</h3></div>
      <div class="table-wrap">
        <table class="data-table" style="min-width:360px">
          <thead><tr><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody>
          <?php if (!$inquiries): ?>
            <tr><td colspan="3"><div class="empty-state">No inquiries yet.</div></td></tr>
          <?php else: foreach ($inquiries as $inq): ?>
            <tr style="cursor:pointer" onclick="location.href='<?= base_url('dashboard/buyer/help.php') ?>?inquiry=<?= $inq['id'] ?><?= $activeInquiry && (int)$activeInquiry['id'] === (int)$inq['id'] ? '#help-thread' : '' ?>'">
              <td><?= e($inq['subject']) ?> <?php if ((int)$inq['unread'] > 0): ?><span class="nav-badge"><?= (int)$inq['unread'] ?></span><?php endif; ?></td>
              <td><span class="badge badge-<?= $inq['status'] === 'open' ? 'amber' : ($inq['status'] === 'answered' ? 'green' : 'slate') ?>"><?= e($inq['status']) ?></span></td>
              <td class="small"><?= e(time_ago($inq['last_message_at'] ?? $inq['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($activeInquiry): ?>
        <div class="chat" style="min-height:auto" id="help-thread">
          <div class="chat-body" style="max-height:240px">
            <?php foreach ($inquiryMessages as $m): ?>
              <div class="msg <?= (int)$m['sender_id'] === (int)$user['id'] ? 'mine' : '' ?>">
                <div class="bubble"><?= nl2br(e($m['body'])) ?></div>
                <div class="msg-meta"><?= e($m['first_name']) ?> (<?= e($m['sender_role']) ?>) · <?= e(time_ago($m['created_at'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($activeInquiry['status'] !== 'closed'): ?>
          <form class="chat-form" method="post" action="<?= $actionUrl ?>?inquiry=<?= (int)$activeInquiry['id'] ?>#help-thread">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reply_inquiry">
            <input type="hidden" name="inquiry_id" value="<?= (int)$activeInquiry['id'] ?>">
            <input type="text" name="body" placeholder="Reply…" required maxlength="2000">
            <button class="btn btn-primary" type="submit">Send</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">Send a new inquiry</h3></div>
      <div class="card-body">
        <p class="small muted" style="margin-top:0">
          Ask the administrators about verification, listings or pricing. Replies appear
          in the list on the left.
        </p>
        <button class="btn btn-primary" type="button" data-modal-open="modal-new-inquiry">✍️ New inquiry</button>
      </div>
    </div>
  </div>
</section>

<!-- ======================= CRUD MODALS ==================== -->
<?= modal_open('modal-new-inquiry', 'New help inquiry') ?>
  <form method="post" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="new_inquiry">
    <div class="form-group">
      <label>Subject</label>
      <input type="text" name="subject" required maxlength="190" placeholder="e.g. Verification question">
    </div>
    <div class="form-group">
      <label>Message</label>
      <textarea name="body" required maxlength="2000" placeholder="How can the administrators help you?"></textarea>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Send to administrators</button>
    </div>
  </form>
<?= modal_close() ?>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
