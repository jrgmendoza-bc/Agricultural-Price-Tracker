<?php
/**
 * login.php - dedicated secure authentication portal.
 * Session management, login throttling
 * and role-based routing to the three dashboards.
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Login-success interstitial: set for one request after a successful POST so
// the success modal can play before the browser is forwarded to the dashboard.
$welcome = null;
if (get('welcome') === '1' && !empty($_SESSION['login_success'])) {
    $welcome = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

if ($welcome === null && Auth::check()) {
    redirect(Auth::dashboardUrl(Auth::role()));
}

$error = null;
$unverified = false;
$identifier = remember_get();          // pre-fill from the remember-me cookie (if any)
$rememberChecked = $identifier !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // "Resend verification email" button (posted from the blocked-login state).
    if (post('action') === 'send_verification') {
        $resendId = (string)post('identifier');
        if ($resendId !== '') {
            $last = (int)($_SESSION['verify_resend_at'] ?? 0);
            if (time() - $last < 60) {
                flash('Please wait a minute before requesting another verification email.', 'error');
            } else {
                $u = Database::first(
                    'SELECT id, email_verified_at FROM users WHERE (email = ? OR username = ?) AND deleted_at IS NULL',
                    [$resendId, $resendId]
                );
                if ($u !== null && $u['email_verified_at'] === null) {
                    $r = EmailVerification::send((int)$u['id']);
                    flash($r['ok'] ? 'A new verification link has been sent to your email address.'
                        : 'Could not send the verification email: ' . $r['error'],
                        $r['ok'] ? 'success' : 'error');
                } else {
                    flash('If that account is awaiting verification, a new link has been sent.', 'success');
                }
                $_SESSION['verify_resend_at'] = time();
            }
        }
        redirect('/login.php');
    }

    $identifier = post('identifier');
    $password   = post('password');
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rememberChecked = !empty($_POST['remember_me']);

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username or email and your password.';
    } elseif (LoginThrottle::isLocked($identifier, $ip)) {
        $mins = (int)ceil(LoginThrottle::secondsRemaining($identifier, $ip) / 60);
        $error = "Too many failed attempts. Try again in {$mins} minute(s).";
    } else {
        $user = Auth::attempt($identifier, $password);

        if ($user === null) {
            LoginThrottle::recordFailure($identifier, $ip);
            // Generic message: never reveal which credential was wrong.
            $error = 'Invalid credentials. Please check your details and try again.';
        } else {
            LoginThrottle::clear($identifier, $ip);
            if (empty($user['email_verified_at'])) {
                // Password was correct, but the email link was never clicked.
                $unverified = true;
                $error = 'Your email address has not been verified yet. Check your inbox for the verification link (and your spam folder).';
            } else {
            // Remember the current username exactly as typed in the field
            // (never the password); ticking off clears any stored cookie.
            if ($rememberChecked) {
                remember_set($identifier);
            } else {
                remember_clear();
            }
            Auth::login($user);
            flash('Welcome back, ' . Auth::displayName($user) . '!', 'success');
            // Show the success modal first (login.php?welcome=1), then forward
            // to the dashboard once the progress animation completes. The URL
            // must include APP_BASE: JS/meta-refresh don't get redirect()'s prefix.
            // Pending accounts are NOT auto-forwarded — the interstitial holds
            // them on the pending/upload notice until they choose to continue.
            $docCount = 0;
            try {
                $docCount = VerificationDocs::countForUser((int)$user['id']);
            } catch (Throwable $e) {
                $docCount = 0;
            }
            $_SESSION['login_success'] = [
                'name'   => Auth::displayName($user),
                'url'    => base_url(ltrim(Auth::dashboardUrl($user['role']), '/')),
                'status' => (string)($user['verification_status'] ?? 'verified'),
                'role'   => (string)$user['role'],
                'docs'   => $docCount,
            ];
            redirect('/login.php?welcome=1');
            }
        }
    }
}

$flashes = pull_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — <?= APP_NAME ?></title>
<?php if ($welcome && ($welcome['status'] ?? 'verified') !== 'pending'): ?>
<meta http-equiv="refresh" content="3;url=<?= e($welcome['url']) ?>">
<?php endif ?>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body>
<div class="auth-wrap">

  <!-- Brand / information panel -->
  <aside class="auth-side">
    <a class="brand-link" href="<?= base_url('index.php') ?>"><span class="brand-mark">🌾</span> <?= APP_NAME ?></a>
    <h1>Sign in to your price transparency dashboard.</h1>
    <p>Localized agricultural prices for Carmen, North Cotabato — aggregated, unit-normalized,
       and connected directly to verified buyers.</p>
    <ul class="auth-perks">
      <li><span class="tick">✓</span> Daily farm-gate prices for Carmen, North Cotabato</li>
      <li><span class="tick">✓</span> Unusual price spikes flagged automatically</li>
      <li><span class="tick">✓</span> Role-based dashboards: Farmer, Buyer, Admin</li>
      <li><span class="tick">✓</span> ID &amp; permits reviewed by an administrator</li>
    </ul>
  
  </aside>

  <!-- Login form -->
  <main class="auth-main">
    <div class="auth-card glass-strong">
      <h2>Welcome back</h2>
      <p class="sub">Enter your username or email to continue.</p>

      <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" data-auto-hide><?= e($flash['message']) ?></div>
      <?php endforeach; ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php if ($unverified): ?>
          <form method="post" action="<?= base_url('login.php') ?>" style="margin-top:-6px;margin-bottom:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_verification">
            <input type="hidden" name="identifier" value="<?= e($identifier) ?>">
            <button type="submit" class="btn btn-outline btn-block">Resend verification email</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" action="<?= base_url('login.php') ?>" autocomplete="on">
        <?= csrf_field() ?>

        <div class="form-group">
          <label for="identifier">Username or email</label>
          <input type="text" id="identifier" name="identifier"
                 value="<?= e($identifier) ?>" placeholder="e.g. farmer@agriprice.local"
                 required autofocus autocomplete="username">
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="pw-toggle" data-pw-toggle="password"
                    aria-pressed="false" aria-label="Show password" title="Show password">
              <svg class="pw-eye pw-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="pw-eye pw-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="check-row">
            <input type="checkbox" name="remember_me" value="1" <?= $rememberChecked ? 'checked' : '' ?>>
            Remember my username on this device
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
      </form>

      <div class="auth-foot">
        New to <?= APP_NAME ?>? <a href="<?= base_url('register.php') ?>"><strong>Create an account</strong></a><br>
        <span class="small">Forgot your password? Contact the administrator through the Help channel.</span>
      </div>
    </div>
  </main>
</div>

<?php if ($welcome):
  $wStatus  = (string)($welcome['status'] ?? 'verified');
  $wRole    = (string)($welcome['role'] ?? '');
  $wDocs    = (int)($welcome['docs'] ?? 0);
  $wPending = $wStatus === 'pending';
  $wAccountUrl = $wRole === 'farmer'
      ? base_url('dashboard/client/account.php')
      : base_url('dashboard/buyer/account.php');
?>
<!-- Success interstitial: ring + progress bar play, then main.js forwards to the
     dashboard. Pending accounts are held here (data-hold) with the verification
     notice and an upload-documents shortcut instead of being auto-forwarded. -->
<div class="login-success" data-login-success data-redirect="<?= e($welcome['url']) ?>"
     <?= $wPending ? 'data-hold' : '' ?> role="status" aria-live="polite">
  <div class="ls-card glass-strong">
    <svg class="ls-ring" viewBox="0 0 100 100" aria-hidden="true">
      <circle class="ls-track" cx="50" cy="50" r="44"></circle>
      <circle class="ls-progress" cx="50" cy="50" r="44"></circle>
      <polyline class="ls-check" points="33,52 45,64 68,39"></polyline>
    </svg>
    <h2 class="ls-title">Welcome back, <?= e($welcome['name']) ?>!</h2>
    <p class="ls-sub">Signed in successfully<?php if (!$wPending): ?> &mdash; taking you to your dashboard&hellip;<?php endif; ?></p>
    <?php if ($wPending): ?>
      <p class="ls-note ls-note-pending"><strong>Your account is pending admin verification.</strong>
         <?php if ($wRole === 'buyer'): ?>
           An administrator must review your account and uploaded documents before you can
           message farmers &mdash; browsing listings and prices is always available.
         <?php else: ?>
           An administrator must review your uploaded documents before your account is approved.
         <?php endif; ?>
      </p>
      <?php if ($wDocs === 0): ?>
        <p class="ls-note ls-note-pending">You have not uploaded any verification documents yet.
           Please upload your ID (and Business/DTI permit, if you have one) so the
           administrator can verify your account.</p>
      <?php endif; ?>
      <div class="ls-actions">
        <a class="btn btn-primary btn-sm" href="<?= e($wAccountUrl) ?>">Upload documents</a>
        <a class="btn btn-outline btn-sm" href="<?= e($welcome['url']) ?>">Go to my dashboard</a>
      </div>
    <?php elseif ($wStatus === 'rejected'): ?>
      <p class="ls-note ls-note-rejected">Your verification was not approved. Please use the
         Help channel to reach the administrators, or upload new documents from your Account status page.</p>
      <div class="ls-bar"><span></span></div>
    <?php else: ?>
      <div class="ls-bar"><span></span></div>
    <?php endif ?>
  </div>
</div>
<?php endif ?>

<script src="<?= asset_url('js/main.js') ?>"></script>
</body>
</html>
