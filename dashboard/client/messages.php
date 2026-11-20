<?php
/**
 * dashboard/client/messages.php - Listing-anchored buyer conversations.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('farmer');

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    // Reply in a listing-anchored conversation
    if ($action === 'send_message') {
        $result = Messaging::send((int)post('conversation_id'), (int)$user['id'], (string)($_POST['body'] ?? ''));
        if (!$result['ok']) {
            flash($result['error'], 'error');
        }
        redirect('/dashboard/client/messages.php?thread=' . (int)post('conversation_id'));
    }

    redirect('/dashboard/client/messages.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$actionUrl = base_url('dashboard/client/messages.php');

$threads   = Messaging::threadsFor((int)$user['id']);
$activeThread = null;
$threadMessages = [];
$threadId = (int)get('thread');
if ($threadId > 0) {
    $activeThread = Messaging::thread($threadId, (int)$user['id']);
    if ($activeThread) {
        Messaging::markRead($threadId, (int)$user['id']);
        $threadMessages = Messaging::messages($threadId);
    }
}

$pageTitle     = 'Messages';
$activeSection = 'messages';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ======================= MESSAGES ======================= -->
<section class="section" id="messages">
  <div class="section-head">
    <h2 class="section-title">💬 Messages</h2>
    <span class="small muted">Every conversation is anchored to one listing.</span>
  </div>

  <div class="card">
    <?php if (!$threads): ?>
      <div class="empty-state"><span class="empty-icon">💬</span>No conversations yet. Verified buyers will contact you from your listings.</div>
    <?php else: ?>
      <div class="thread-layout">
        <div class="thread-list">
          <?php foreach ($threads as $t): ?>
            <a class="thread-item <?= $activeThread && (int)$activeThread['id'] === (int)$t['id'] ? 'active' : '' ?>"
               href="<?= base_url('dashboard/client/messages.php') ?>?thread=<?= $t['id'] ?>">
              <span class="t-name">
                <?= e($t['first_name'] . ' ' . $t['last_name']) ?>
                <?php if ((int)$t['unread'] > 0): ?><span class="nav-badge"><?= (int)$t['unread'] ?></span><?php endif; ?>
              </span>
              <span class="small" style="display:block"><?= e($t['product_name']) ?> · <?= e(stage_label($t['current_stage'] ?? 'available')) ?></span>
              <span class="small" style="display:block;opacity:.8"><?= e(truncate($t['last_body'] ?? '', 44)) ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="chat">
          <?php if (!$activeThread): ?>
            <div class="empty-state" style="margin:auto"><span class="empty-icon">👆</span>Select a conversation to read and reply.</div>
          <?php else: ?>
            <div class="chat-head flex-between">
              <div>
                <strong><?= e($activeThread['first_name'] . ' ' . $activeThread['last_name']) ?></strong>
                <div class="small muted">About: <?= e($activeThread['product_name']) ?> · listing #<?= (int)$activeThread['crop_id'] ?></div>
              </div>
              <span class="badge badge-green"><?= e($activeThread['crop_status']) ?></span>
            </div>
            <div class="chat-body">
              <?php foreach ($threadMessages as $m): ?>
                <div class="msg <?= (int)$m['sender_id'] === (int)$user['id'] ? 'mine' : '' ?>">
                  <div class="bubble"><?= nl2br(e($m['body'])) ?></div>
                  <div class="msg-meta"><?= e($m['first_name']) ?> · <?= e(time_ago($m['created_at'])) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <form class="chat-form" method="post" action="<?= $actionUrl ?>?thread=<?= (int)$activeThread['id'] ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="send_message">
              <input type="hidden" name="conversation_id" value="<?= (int)$activeThread['id'] ?>">
              <input type="text" name="body" placeholder="Write a reply…" required maxlength="2000">
              <button class="btn btn-primary" type="submit">Send</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
