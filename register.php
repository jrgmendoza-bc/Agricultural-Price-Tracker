<?php
/**
 * register.php - account registration with role selection.
 * Farmers and buyers upload photos of their ID, Business/DTI permit or any
 * supporting document; an administrator reviews the documents before the
 * account is verified. Either way, the email address must be confirmed
 * through the verification link before the account can sign in.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect(Auth::dashboardUrl(Auth::role()));
}

$error = null;
$old = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'role' => 'farmer'];
$docs = ['ok' => true, 'entries' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($old as $key => $default) {
        $old[$key] = post($key, $default);
    }
    $password  = post('password');
    $confirm   = post('password_confirm');
    if ($old['first_name'] === '' || $old['last_name'] === '' || $old['username'] === '' || $old['email'] === '') {
        $error = 'Please complete all required fields.';
    } elseif (!preg_match('/^[^0-9]+$/', $old['first_name']) || !preg_match('/^[^0-9]+$/', $old['last_name'])) {
        $error = 'First and last name must not contain any numbers.';
    } elseif (!in_array($old['role'], ['farmer', 'buyer'], true)) {
        $error = 'Please choose a valid account role.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($old['username']) < 3 || strlen($old['username']) > 60 || str_contains($old['username'], '@')) {
        $error = 'Username must be 3–60 characters and cannot contain an @ sign.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Password confirmation does not match.';
    } elseif (($docs = VerificationDocs::validateRegistration()) && isset($docs['error'])) {
        $error = $docs['error'];
    } else {
        $exists = Database::scalar(
            "SELECT COUNT(*) FROM users WHERE email = ? OR username = ?",
            [$old['email'], $old['username']]
        );
        if ((int)$exists > 0) {
            $error = 'An account with that email or username already exists.';
        } else {
            Database::execute(
                "INSERT INTO users (first_name, last_name, username, email, password, role,
                                    email_verified_at, verification_status, verified_at)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [
                    $old['first_name'], $old['last_name'], $old['username'], $old['email'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $old['role'],
                    null,
                    'pending',                              // admin reviews the uploaded documents
                    null,
                ]
            );
            $newId = (int)Database::getConnection()->lastInsertId();
            if (!VerificationDocs::persist($newId, $docs['entries'])) {
                Database::execute('DELETE FROM users WHERE id = ?', [$newId]);
                $error = 'Your account could not be created right now — the uploaded documents could not be saved. Please try again.';
            } else {
                $v = EmailVerification::send($newId);
                if ($v['ok']) {
                    flash('Account created! Check your email for your verification link, then sign in.'
                        . ' Your documents will be reviewed by an administrator afterwards.', 'success');
                    redirect('/login.php');
                }
                // Email could not be sent: roll the account (and documents) back
                // so no unverifiable account is left behind.
                VerificationDocs::deleteAllForUser($newId);
                Database::execute('DELETE FROM users WHERE id = ?', [$newId]);
                $error = 'Your account could not be created right now — the verification email failed to send ('
                    . $v['error'] . '). Please try again later.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body>
<div class="auth-wrap">

  <aside class="auth-side">
    <a class="brand-link" href="<?= base_url('index.php') ?>"><span class="brand-mark">🌾</span> <?= APP_NAME ?></a>
    <h1>Join the local price transparency platform.</h1>
    <p>Farmers list produce and track growth stages. Buyers browse listings. Both roles upload
       ID and permit documents that an administrator reviews before verification.</p>
    <ul class="auth-perks">
      <li><span class="tick">✓</span> Free for farmers in Carmen, North Cotabato</li>
      <li><span class="tick">✓</span> ID &amp; permits reviewed by an administrator</li>
      <li><span class="tick">✓</span> Contact details never shown publicly</li>
      <li><span class="tick">✓</span> Track crop and livestock growth stages</li>
      <li><span class="tick">✓</span> Messages and help channel after sign-up</li>
    </ul>
   
  </aside>

  <main class="auth-main">
    <div class="auth-card glass-strong">
      <h2>Create your account</h2>
      <p class="sub">Fields marked * are required.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= base_url('register.php') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-grid">
          <div class="form-group">
            <label for="first_name">First name *</label>
            <input type="text" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" pattern="[^0-9]+" title="Name must not contain any numbers" required>
          </div>
          <div class="form-group">
            <label for="last_name">Last name *</label>
            <input type="text" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" pattern="[^0-9]+" title="Name must not contain any numbers" required>
          </div>
        </div>

        <div class="form-group">
          <label for="username">Username *</label>
          <input type="text" id="username" name="username" value="<?= e($old['username']) ?>"
                 placeholder="3–60 characters, no @ sign" required>
        </div>

        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
        </div>

        <div class="form-group">
          <label for="role">I am a… *</label>
          <select id="role" name="role" required>
            <option value="farmer" <?= $old['role'] === 'farmer' ? 'selected' : '' ?>>Farmer (seller / client)</option>
            <option value="buyer" <?= $old['role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option>
          </select>
          <div class="hint">Both farmers and buyers are reviewed by an administrator before their account is verified.</div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="password">Password *</label>
            <div class="pw-wrap">
              <input type="password" id="password" name="password" minlength="8" required>
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
            <div class="hint">At least 8 characters.</div>
          </div>
          <div class="form-group">
            <label for="password_confirm">Confirm password *</label>
            <div class="pw-wrap">
              <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
              <button type="button" class="pw-toggle" data-pw-toggle="password_confirm"
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
        </div>

        <div class="reg-docs">
          <h3 class="reg-docs-title">📄 Verification documents</h3>
          <p class="hint" style="margin-top:0">
            Upload a photo or scan of your government-issued ID, plus your Business or DTI permit
            if you have one. An administrator reviews these before your account is verified.
            JPG, PNG, WebP or PDF · max 5 MB each.
          </p>
          <div class="form-grid">
            <div class="form-group">
              <label for="doc_id">Government-issued ID *</label>
              <input type="file" id="doc_id" name="doc_id" required
                     accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
              <div class="hint">Driver's license, passport, voter's ID, UMID, etc.</div>
            </div>
            <div class="form-group">
              <label for="doc_permit">Business / DTI Permit (optional)</label>
              <input type="file" id="doc_permit" name="doc_permit"
                     accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
              <div class="hint">Business permit, DTI / SEC registration, barangay clearance.</div>
            </div>
          </div>
          <div class="form-group">
            <label for="doc_other">Other documents (optional)</label>
            <input type="file" id="doc_other" name="doc_other[]" multiple
                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
            <div class="hint">Any other supporting document — you may select several files at once.</div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Create account</button>
      </form>

      <div class="auth-foot">
        Already have an account? <a href="<?= base_url('login.php') ?>"><strong>Sign in</strong></a>
      </div>
    </div>
  </main>
</div>

<script src="<?= asset_url('js/main.js') ?>"></script>
</body>
</html>
