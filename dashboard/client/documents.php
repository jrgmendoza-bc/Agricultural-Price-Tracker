<?php
/**
 * dashboard/client/documents.php - Farmer "My documents": view, update or
 * upload verification documents (ID, Business/DTI permit, others).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('farmer');

// Upload / remove posted from the card on this page.
VerificationDocs::handleAccountPost($user, '/dashboard/client/documents.php');

$docCount = VerificationDocs::countForUser((int)$user['id']);
$status   = (string)$user['verification_status'];
$badge    = $status === 'verified' ? 'green' : ($status === 'pending' ? 'amber' : 'red');

$pageTitle     = 'My documents';
$activeSection = 'documents';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- =================== MY DOCUMENTS ====================== -->
<section class="section" id="documents">
  <div class="section-head">
    <h2 class="section-title">📄 My documents</h2>
    <span class="badge badge-<?= $badge ?>"><?= e($status) ?></span>
  </div>

  <div class="banner <?= $status === 'verified' ? 'banner-verified' : ($status === 'pending' ? 'banner-pending' : 'banner-rejected') ?>" style="margin-bottom:18px">
    <span class="banner-ico">🛡️</span>
    <div class="banner-body">
      <strong>Verification documents (<?= $docCount ?>/<?= VerificationDocs::MAX_FILES ?> uploaded)</strong>
      <div class="small muted">
        <?php if ($status === 'verified'): ?>
          Your documents were approved<?= $user['verified_at'] ? ' on ' . e(date('F j, Y', strtotime($user['verified_at']))) : '' ?>.
          You can still view them below; uploading or removing a file returns your account to pending for re-review.
        <?php elseif ($status === 'pending'): ?>
          An administrator is reviewing your documents. You can still add, replace or remove files — every change is re-reviewed.
        <?php else: ?>
          Your verification was not approved. Upload clearer or corrected documents below so the administrator can review them again.
        <?php endif; ?>
      </div>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/client/account.php') ?>">Account status</a>
  </div>

  <?= VerificationDocs::cardHtml($user) ?>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
