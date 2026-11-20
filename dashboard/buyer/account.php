<?php
/**
 * dashboard/buyer/account.php - Account status / verification details
 * and management of the uploaded verification documents.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('buyer');

// Upload / remove documents (flashes + redirects back here).
VerificationDocs::handleAccountPost($user, '/dashboard/buyer/account.php');

$isVerified = $user['verification_status'] === 'verified';

$pageTitle     = 'Account status';
$activeSection = 'verification-status';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- =================== ACCOUNT STATUS ===================== -->
<section class="section" id="verification-status">
  <div class="section-head"><h2 class="section-title">🛡️ Account status</h2></div>
  <div class="grid-2">
    <div class="card">
      <div class="card-head"><h3 class="card-title">Verification</h3>
        <span class="badge badge-<?= $user['verification_status'] === 'verified' ? 'green' : ($user['verification_status'] === 'pending' ? 'amber' : 'red') ?>">
          <?= e($user['verification_status']) ?>
        </span>
      </div>
      <div class="card-body">
        <p class="small muted">
          <?php if ($user['verification_status'] === 'verified'): ?>
            Your account was reviewed<?= $user['verified_at'] ? ' on ' . e(date('F j, Y', strtotime($user['verified_at']))) : '' ?>.
            You can open conversations with farmers about any active listing.
          <?php elseif ($user['verification_status'] === 'pending'): ?>
            An administrator must review your uploaded documents before you can message farmers.
            Browsing listings and prices is available to everyone.
          <?php else: ?>
            Your verification was not approved. You can upload new documents below, or use the Help channel to reach the administrators.
          <?php endif; ?>
        </p>
        <ul class="small muted" style="padding-left:18px;margin:0">
          <li>Browse listings &amp; compare prices: <strong>always available</strong></li>
          <li>Message farmers: <strong><?= $isVerified ? 'unlocked' : 'locked' ?></strong></li>
          <li>Your contact details are never shown publicly</li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">Profile</h3></div>
      <div class="card-body">
        <p class="small muted" style="margin:0">
          <strong><?= e(Auth::displayName($user)) ?></strong><br>
          <?= e($user['email']) ?><br>
          Member since <?= e(date('F j, Y', strtotime($user['created_at']))) ?>
        </p>
      </div>
    </div>
  </div>

  <div style="margin-top:20px">
    <?= VerificationDocs::cardHtml($user) ?>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
