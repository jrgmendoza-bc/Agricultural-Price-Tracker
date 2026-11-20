<?php
/**
 * sidebar.php - fixed glass sidebar: brand + current page, quick actions,
 * grouped role-based menu (with collapsible submenus), notification feed and
 * account menu (all former topbar controls).
 * Expects: $activeSection (string) set by the including dashboard page,
 *          $pageTitle, $authUser, $roleLabel, $notifCount, $notifItems
 *          and notif_text() coming from dashboard_header.php.
 */
$role      = Auth::role();
$active    = $activeSection ?? '';
$uid       = Auth::id();
$unreadMsg = 0;
$pendingBuyersBadge = 0;
$myListings = 0;
$openInquiries = 0;

try {
    $unreadMsg = (int)Database::scalar(
        "SELECT COUNT(*) FROM messages m
           JOIN conversations c ON c.id = m.conversation_id
          WHERE m.read_at IS NULL AND m.sender_id <> ?
            AND (c.farmer_id = ? OR c.buyer_id = ?)",
        [$uid, $uid, $uid]
    );
    if ($role === 'admin') {
        $pendingBuyersBadge = (int)Database::scalar(
            "SELECT COUNT(*) FROM users
              WHERE role IN ('buyer','farmer') AND verification_status = 'pending' AND deleted_at IS NULL"
        );
        $openInquiries = (int)Database::scalar(
            "SELECT COUNT(*) FROM support_inquiries WHERE status = 'open'"
        );
    }
    if ($role === 'farmer') {
        $myListings = (int)Database::scalar(
            "SELECT COUNT(*) FROM crops WHERE farmer_id = ? AND deleted_at IS NULL",
            [$uid]
        );
    }
} catch (Throwable $e) {
    // Widgets degrade gracefully if a table is unavailable.
}

// Grouped navigation: items may carry 'children' => [ ... ] to form a submenu.
// Every leaf points at its own page (one file per menu item).
$menus = [
    'admin' => [
        ['id' => 'overview', 'label' => 'Overview', 'ico' => '📊', 'href' => base_url('dashboard/admin/overview.php')],
        ['id' => 'price-data', 'label' => 'Price data', 'ico' => '📈', 'children' => [
            ['id' => 'reports',         'label' => 'Price reports',    'href' => base_url('dashboard/admin/reports.php')],
            ['id' => 'compare',         'label' => 'Compare prices',   'href' => base_url('dashboard/admin/compare.php')],
            ['id' => 'farmer-listings', 'label' => 'Farmer Listings',  'href' => base_url('dashboard/admin/farmer-listings.php')],
        ]],
        ['id' => 'management', 'label' => 'Management', 'ico' => '🗂️', 'children' => [
            ['id' => 'verification', 'label' => 'Account verification', 'href' => base_url('dashboard/admin/verification.php'), 'badge' => $pendingBuyersBadge],
            ['id' => 'users',        'label' => 'User management',    'href' => base_url('dashboard/admin/users.php')],
            ['id' => 'catalog',      'label' => 'Catalog',            'href' => base_url('dashboard/admin/catalog.php')],
            ['id' => 'categories',   'label' => 'Categories',         'href' => base_url('dashboard/admin/categories.php')],
            ['id' => 'markets',      'label' => 'Markets',            'href' => base_url('dashboard/admin/markets.php')],
        ]],
        ['id' => 'help', 'label' => 'Help channel', 'ico' => '🆘', 'href' => base_url('dashboard/admin/help.php'), 'badge' => $openInquiries],
    ],
    'farmer' => [
        ['id' => 'overview', 'label' => 'Overview', 'ico' => '📊', 'href' => base_url('dashboard/client/overview.php')],
        ['id' => 'listings-group', 'label' => 'Listings', 'ico' => '🌱', 'children' => [
            ['id' => 'listings',    'label' => 'My listings', 'href' => base_url('dashboard/client/listings.php'), 'badge' => $myListings],
            ['id' => 'new-listing', 'label' => 'New listing', 'href' => base_url('dashboard/client/new-listing.php')],
        ]],
        ['id' => 'compare', 'label' => 'Compare prices', 'ico' => '⚖️', 'href' => base_url('dashboard/client/compare.php')],
        ['id' => 'messages', 'label' => 'Messages', 'ico' => '💬', 'href' => base_url('dashboard/client/messages.php'), 'badge' => $unreadMsg],
        ['id' => 'documents', 'label' => 'My documents', 'ico' => '📄', 'href' => base_url('dashboard/client/documents.php')],
        ['id' => 'support', 'label' => 'Support', 'ico' => '🆘', 'children' => [
            ['id' => 'verification-status', 'label' => 'Account status', 'href' => base_url('dashboard/client/account.php')],
            ['id' => 'help',                'label' => 'Help channel',   'href' => base_url('dashboard/client/help.php')],
        ]],
    ],
    'buyer' => [
        ['id' => 'overview', 'label' => 'Overview', 'ico' => '📊', 'href' => base_url('dashboard/buyer/overview.php')],
        ['id' => 'marketplace', 'label' => 'Marketplace', 'ico' => '🛒', 'href' => base_url('dashboard/buyer/marketplace.php')],
        ['id' => 'compare', 'label' => 'Compare prices', 'ico' => '⚖️', 'href' => base_url('dashboard/buyer/compare.php')],
        ['id' => 'messages', 'label' => 'Messages', 'ico' => '💬', 'href' => base_url('dashboard/buyer/messages.php'), 'badge' => $unreadMsg],
        ['id' => 'documents', 'label' => 'My documents', 'ico' => '📄', 'href' => base_url('dashboard/buyer/documents.php')],
        ['id' => 'support', 'label' => 'Support', 'ico' => '🆘', 'children' => [
            ['id' => 'verification-status', 'label' => 'Account status', 'href' => base_url('dashboard/buyer/account.php')],
            ['id' => 'help',                'label' => 'Help channel',   'href' => base_url('dashboard/buyer/help.php')],
        ]],
    ],
];

// Quick action buttons pinned under the brand.
$quickActions = [
    'admin' => [
        'primary'   => ['label' => 'Price reports', 'ico' => '📈', 'href' => base_url('dashboard/admin/reports.php')],
        'secondary' => [
            ['label' => 'Verification', 'ico' => '🛡️', 'href' => base_url('dashboard/admin/verification.php'), 'badge' => $pendingBuyersBadge],
            ['label' => 'Users',        'ico' => '👥', 'href' => base_url('dashboard/admin/users.php')],
            ['label' => 'Help',         'ico' => '🆘', 'href' => base_url('dashboard/admin/help.php'), 'badge' => $openInquiries],
        ],
    ],
    'farmer' => [
        'primary'   => ['label' => 'New listing', 'ico' => '➕', 'href' => base_url('dashboard/client/new-listing.php')],
        'secondary' => [
            ['label' => 'Messages', 'ico' => '💬', 'href' => base_url('dashboard/client/messages.php'), 'badge' => $unreadMsg],
            ['label' => 'Help',     'ico' => '🆘', 'href' => base_url('dashboard/client/help.php')],
        ],
    ],
    'buyer' => [
        'primary'   => ['label' => 'Browse marketplace', 'ico' => '🛒', 'href' => base_url('dashboard/buyer/marketplace.php')],
        'secondary' => [
            ['label' => 'Messages', 'ico' => '💬', 'href' => base_url('dashboard/buyer/messages.php'), 'badge' => $unreadMsg],
            ['label' => 'Help',     'ico' => '🆘', 'href' => base_url('dashboard/buyer/help.php')],
        ],
    ],
];

$menu = $menus[$role] ?? [];
$quick = $quickActions[$role] ?? null;
$roleLabel = $roleLabel ?? (['admin' => 'Administrator', 'farmer' => 'Client · Farmer', 'buyer' => 'Buyer'][$role] ?? $role);
?>
<aside class="sidebar" id="sidebar">
  <!-- Brand + page title (was the topbar title) -->
  <div class="sidebar-brand">
    <span class="brand-mark">🌾</span>
    <div class="brand-meta">
      <div class="brand-name">AgriPriceTrack</div>
      <div class="brand-sub"><?= e($pageTitle ?? 'Dashboard') ?></div>
    </div>
    <button class="sidebar-close" aria-label="Close menu">✕</button>
  </div>

  <!-- Section: quick actions -->
  <?php if ($quick): ?>
    <div class="sidebar-actions">
      <?php $q = $quick['primary']; ?>
      <a class="btn btn-primary btn-sm side-quick" href="<?= e($q['href']) ?>">
        <span><?= $q['ico'] ?></span> <?= e($q['label']) ?>
      </a>
      <?php if (!empty($quick['secondary'])): ?>
        <div class="side-quick-row">
          <?php foreach ($quick['secondary'] as $s): ?>
            <a class="side-quick-sm" href="<?= e($s['href']) ?>">
              <span><?= $s['ico'] ?></span> <?= e($s['label']) ?>
              <?php if (!empty($s['badge'])): ?>
                <span class="nav-badge"><?= (int)$s['badge'] ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Section: navigation (groups open into submenus) -->
  <nav class="sidebar-nav">
    <div class="nav-section-title">Menu</div>
    <?php foreach ($menu as $item): ?>
      <?php if (!empty($item['children'])): ?>
        <?php
          $groupOpen = false;
          foreach ($item['children'] as $child) {
              if (($child['id'] ?? '') === $active) { $groupOpen = true; break; }
          }
        ?>
        <div class="nav-group <?= $groupOpen ? 'open' : '' ?>">
          <button type="button" class="nav-link nav-parent" data-nav-toggle
                  aria-expanded="<?= $groupOpen ? 'true' : 'false' ?>">
            <span class="nav-ico"><?= $item['ico'] ?></span>
            <?= e($item['label']) ?>
            <span class="nav-caret">▾</span>
          </button>
          <div class="nav-sub">
            <?php foreach ($item['children'] as $child): ?>
              <a class="nav-link <?= ($child['id'] ?? '') === $active ? 'active' : '' ?>" href="<?= e($child['href']) ?>">
                <?= e($child['label']) ?>
                <?php if (!empty($child['badge'])): ?>
                  <span class="nav-badge"><?= (int)$child['badge'] ?></span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <a class="nav-link <?= $active === $item['id'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
          <span class="nav-ico"><?= $item['ico'] ?></span>
          <?= e($item['label']) ?>
          <?php if (!empty($item['badge'])): ?>
            <span class="nav-badge"><?= (int)$item['badge'] ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Section: alerts + account (was the topbar actions) -->
  <div class="sidebar-tools">
    <div class="side-block-head">
      <span class="side-block-title">Notifications</span>
      <div class="dropdown">
        <button class="icon-btn" data-dropdown="notifMenu" aria-label="Notifications">
          🔔
          <?php if ($notifCount > 0): ?>
            <span class="notif-dot"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
          <?php endif; ?>
        </button>
        <div class="dropdown-menu" id="notifMenu">
          <div class="dropdown-head">
            <span>Notifications</span>
            <?php if ($notifCount > 0): ?>
              <form method="post" style="margin:0"
                    data-confirm="Every unread notification will be marked as read. This cannot be undone."
                    data-confirm-title="Mark all as read?"
                    data-confirm-label="Mark all read"
                    data-confirm-class="btn btn-primary btn-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_notifications_read">
                <button class="btn btn-ghost btn-sm" type="submit">Mark all read</button>
              </form>
            <?php endif; ?>
          </div>
          <?php if (!$notifItems): ?>
            <div class="dropdown-item muted">No notifications yet.</div>
          <?php else: foreach ($notifItems as $n): ?>
            <div class="dropdown-item <?= $n['read_at'] === null ? 'unread' : '' ?>">
              <span><?= $n['read_at'] === null ? '🟢' : '⚪' ?></span>
              <span>
                <?= e(notif_text($n)) ?>
                <span class="small muted" style="display:block"><?= e(time_ago($n['created_at'])) ?></span>
              </span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="dropdown">
      <div class="user-chip" data-dropdown="userMenu">
        <span class="avatar"><?= e(Auth::initials($authUser)) ?></span>
        <span class="u-meta">
          <span class="u-name"><?= e(Auth::displayName($authUser)) ?></span>
          <span class="u-role"><?= e($roleLabel) ?></span>
        </span>
        <span class="chip-caret">▴</span>
      </div>
      <div class="dropdown-menu" id="userMenu">
        <div class="dropdown-head"><?= e($authUser['email']) ?></div>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= base_url('index.php') ?>">🏠 Public home page</a>
        <?php if ($authUser['role'] === 'farmer'): ?>
          <a class="dropdown-item" href="<?= base_url('dashboard/client/new-listing.php') ?>">➕ New listing</a>
        <?php elseif ($authUser['role'] === 'buyer'): ?>
          <a class="dropdown-item" href="<?= base_url('dashboard/buyer/marketplace.php') ?>">🛒 Browse marketplace</a>
        <?php else: ?>
          <a class="dropdown-item" href="<?= base_url('dashboard/admin/reports.php') ?>">📈 Price reports</a>
        <?php endif; ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= base_url('logout.php') ?>"
           data-confirm="You will be signed out of AgriPriceTrack and returned to the sign-in page. Any unsaved work will be lost."
           data-confirm-title="Sign out?"
           data-confirm-label="Sign out">🚪 Sign out</a>
      </div>
    </div>
  </div>

  <div class="sidebar-foot">
    <strong>Carmen, North Cotabato</strong><br>
    Localized price aggregation system
  </div>
</aside>
