<?php
/**
 * dashboard/buyer/overview.php - Buyer overview (verification banner + stats).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('buyer');

// Document upload / remove posted from the card on this page.
VerificationDocs::handleAccountPost($user, '/dashboard/buyer/overview.php');

$isVerified = $user['verification_status'] === 'verified';

$stats = [
    'listings' => (int)Database::scalar("SELECT COUNT(*) FROM crops WHERE deleted_at IS NULL"),
    'available' => (int)Database::scalar("SELECT COUNT(*) FROM crops WHERE status = 'available' AND deleted_at IS NULL"),
    'farmers' => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND deleted_at IS NULL"),
    'threads' => (int)Database::scalar("SELECT COUNT(*) FROM conversations WHERE buyer_id = ?", [$user['id']]),
];

$pageTitle     = 'Overview';
$activeSection = 'overview';
require __DIR__ . '/../../includes/dashboard_header.php';
?>

<!-- ======================= OVERVIEW ======================= -->
<section class="section" id="overview">
  <?php if ($user['verification_status'] === 'pending'): ?>
    <div class="banner banner-pending">
      <span class="banner-ico">🛡️</span>
      <div class="banner-body">
        <strong>Account awaiting administrator verification.</strong>
        <div class="small muted">You can browse all listings and prices, but messaging farmers stays locked until an admin reviews your account and uploaded documents.</div>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/buyer/account.php') ?>">Account status</a>
    </div>
  <?php elseif ($user['verification_status'] === 'rejected'): ?>
    <div class="banner banner-rejected">
      <span class="banner-ico">⛔</span>
      <div class="banner-body">
        <strong>Verification was not approved.</strong>
        <div class="small muted">You may still browse listings and prices. Contact the administrators through the Help channel for clarification.</div>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/buyer/help.php') ?>">Open inquiry</a>
    </div>
  <?php else: ?>
    <div class="banner banner-verified">
      <span class="banner-ico">✅</span>
      <div class="banner-body">
        <strong>Verified buyer account.</strong>
        <div class="small muted">You can open a conversation with any farmer about a specific listing — your contact details are never shown publicly.</div>
      </div>
      <a class="btn btn-primary btn-sm" href="<?= base_url('dashboard/buyer/marketplace.php') ?>">Browse listings</a>
    </div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">🛒</span><div><div class="stat-value"><?= $stats['listings'] ?></div><div class="stat-label">Listings tracked</div></div></div>
    <div class="stat-card"><span class="stat-icon">✅</span><div><div class="stat-value"><?= $stats['available'] ?></div><div class="stat-label">Available now</div></div></div>
    <div class="stat-card"><span class="stat-icon">🌾</span><div><div class="stat-value"><?= $stats['farmers'] ?></div><div class="stat-label">Farmers on board</div></div></div>
    <div class="stat-card"><span class="stat-icon">💬</span><div><div class="stat-value"><?= $stats['threads'] ?></div><div class="stat-label">My conversations</div></div></div>
  </div>

  <div class="grid-2" style="margin-top:20px">
    <div class="card">
      <div class="card-head"><h3 class="card-title">🛒 Marketplace</h3></div>
      <div class="card-body flex-between" style="gap:14px;flex-wrap:wrap">
        <p class="small muted" style="margin:0">Compare normalized asking prices against current market rates.</p>
        <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/buyer/marketplace.php') ?>">Open marketplace</a>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3 class="card-title">💬 Messages &amp; help</h3></div>
      <div class="card-body flex-between" style="gap:14px;flex-wrap:wrap">
        <p class="small muted" style="margin:0">Reply to farmers or ask the administrators a question.</p>
        <span style="display:flex;gap:8px">
          <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/buyer/messages.php') ?>">Messages</a>
          <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard/buyer/help.php') ?>">Help</a>
        </span>
      </div>
    </div>
  </div>

  <!-- Uploaded verification documents: view, open full size, replace or remove -->
  <div style="margin-top:20px">
    <?= VerificationDocs::cardHtml($user) ?>
  </div>
</section>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
