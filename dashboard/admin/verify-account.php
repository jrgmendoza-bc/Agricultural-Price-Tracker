<?php
/**
 * dashboard/admin/verify-account.php - review one farmer/buyer account:
 * inspect the uploaded ID / permit documents, then Approve or Reject.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = Auth::requireRole('admin');

/* ------------------------------------------------------------------ */
/* POST: approve / reject                                              */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $targetId = (int)post('user_id');
    $decision = post('decision');
    $note     = trim(post('admin_note'));

    $target = Database::first(
        "SELECT * FROM users WHERE id = ? AND role IN ('farmer','buyer') AND deleted_at IS NULL",
        [$targetId]
    );
    if (!$target || !in_array($decision, ['approved', 'rejected'], true)) {
        flash('Invalid verification request.', 'error');
        redirect('/dashboard/admin/verification.php');
    }

    $status   = $decision === 'approved' ? 'verified' : 'rejected';
    $roleWord = $target['role'] === 'farmer' ? 'farmer' : 'buyer';
    $message  = $decision === 'approved'
        ? ($target['role'] === 'farmer'
            ? 'Your farmer account has been verified — your uploaded documents were approved.'
            : 'Your buyer account has been verified. You can now message farmers.')
        : 'Your verification was not approved. You can upload new documents from your Account status page, or use the Help channel to reach the administrators.';
    if ($note !== '') {
        $message .= ' Note from the administrator: ' . $note;
    }

    Database::execute(
        "UPDATE users SET verification_status = ?, verified_at = NOW(), verified_by = ? WHERE id = ?",
        [$status, $admin['id'], $targetId]
    );
    Database::execute(
        "INSERT INTO notifications (id, type, notifiable_id, data) VALUES (?,?,?,?)",
        [
            bin2hex(random_bytes(16)),
            'App\\Notifications\\AccountVerification',
            $targetId,
            json_encode(['message' => $message]),
        ]
    );
    flash(ucfirst($roleWord) . ' account for ' . e($target['first_name'] . ' ' . $target['last_name']) . " {$decision}.", 'success');
    redirect('/dashboard/admin/verification.php');
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */
$targetId = (int)get('user_id');
$target = $targetId > 0 ? Database::first(
    "SELECT * FROM users WHERE id = ? AND role IN ('farmer','buyer') AND deleted_at IS NULL",
    [$targetId]
) : null;

if (!$target) {
    flash('Account not found in the verification queue.', 'error');
    redirect('/dashboard/admin/verification.php');
}

$documents = VerificationDocs::listForUser((int)$target['id']);
$reviewer  = $target['verified_by']
    ? Database::first("SELECT first_name, last_name FROM users WHERE id = ?", [$target['verified_by']])
    : null;

$pageTitle     = 'Review account';
$activeSection = 'verification';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<section class="section" id="verify-account">
  <div class="section-head">
    <h2 class="section-title">🛡️ Review: <?= e(Auth::displayName($target)) ?></h2>
    <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/admin/verification.php') ?>">← Back to queue</a>
  </div>

  <div class="grid-2">
    <div class="card">
      <div class="card-head"><h3 class="card-title">Account</h3>
        <span class="badge badge-<?= $target['verification_status'] === 'verified' ? 'green' : ($target['verification_status'] === 'pending' ? 'amber' : 'red') ?>">
          <?= e($target['verification_status']) ?>
        </span>
      </div>
      <div class="card-body">
        <p class="small muted" style="margin:0">
          <strong><?= e(Auth::displayName($target)) ?></strong> (<?= e($target['role']) ?>)<br>
          <?= e($target['email']) ?><br>
          Username: <?= e($target['username']) ?><br>
          Registered <?= e(date('F j, Y \a\t g:i A', strtotime($target['created_at']))) ?>
          <?php if ($target['verified_at'] && $reviewer): ?>
            <br>Last reviewed <?= e(date('F j, Y', strtotime($target['verified_at']))) ?>
            by <?= e(trim($reviewer['first_name'] . ' ' . $reviewer['last_name'])) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">Decision</h3></div>
      <div class="card-body">
        <?php if ($target['verification_status'] === 'verified'): ?>
          <p class="small muted" style="margin:0">This account is already verified. Re-submitting a decision will update its status.</p>
        <?php endif; ?>
        <form method="post" action="<?= base_url('dashboard/admin/verify-account.php') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="verify_account">
          <input type="hidden" name="user_id" value="<?= (int)$target['id'] ?>">
          <div class="form-group">
            <label for="admin_note">Note to the account owner (optional)</label>
            <input type="text" id="admin_note" name="admin_note" maxlength="300"
                   placeholder="e.g. ID photo is blurry — please re-upload">
          </div>
          <div class="modal-actions" style="justify-content:flex-start">
            <button class="btn btn-danger" type="submit" name="decision" value="rejected"
                    data-confirm="<?= e(Auth::displayName($target)) ?> will be notified that the verification was not approved."
                    data-confirm-title="Reject this account?" data-confirm-label="Reject">Reject</button>
            <button class="btn btn-primary" type="submit" name="decision" value="approved"
                    data-confirm="<?= e(Auth::displayName($target)) ?> will be notified that the account is verified."
                    data-confirm-title="Approve this account?" data-confirm-label="Approve">Approve</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:20px">
    <div class="card-head"><h3 class="card-title">📄 Uploaded documents (<?= count($documents) ?>)</h3></div>
    <div class="card-body">
      <?php if (!$documents): ?>
        <p class="small muted" style="margin:0">This account has no uploaded documents. Ask the owner to upload them through the Help channel.</p>
      <?php else: ?>
        <div class="doc-grid">
          <?php foreach ($documents as $doc):
              $viewUrl = base_url('view-document.php?id=' . (int)$doc['id']);
              $isImage = str_starts_with((string)$doc['mime_type'], 'image/'); ?>
            <div class="doc-tile">
              <a class="doc-thumb" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener" title="Open full size">
                <?php if ($isImage): ?>
                  <img src="<?= e($viewUrl) ?>" alt="<?= e($doc['original_name']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="doc-pdf">📄</span>
                <?php endif; ?>
              </a>
              <div class="doc-meta">
                <span class="doc-type"><?= e(VerificationDocs::typeLabel((string)$doc['doc_type'])) ?></span>
                <span class="doc-name" title="<?= e($doc['original_name']) ?>"><?= e(truncate((string)$doc['original_name'], 28)) ?></span>
                <span class="small muted"><?= e(number_format(((int)$doc['file_size']) / 1024)) ?> KB · <?= e(date('M j, Y g:i A', strtotime($doc['uploaded_at']))) ?></span>
                <a class="small" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener">Open full size →</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
