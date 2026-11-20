<?php
/**
 * verify-email.php - public landing page for the emailed verification link.
 * Consumes the one-time token and marks the address as verified.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$token  = (string)get('token');
$result = EmailVerification::verify($token);

$states = [
    'verified' => ['success', 'Email verified!', 'Your email address has been confirmed. You can now sign in to your account.'],
    'already'  => ['success', 'Already verified', 'This email address was already verified. Go ahead and sign in.'],
    'expired'  => ['error',   'Link expired', 'This verification link has expired (they are valid for ' . EmailVerification::HOURS . ' hours). Sign in with your credentials and press "Resend verification email" to get a fresh link.'],
    'invalid'  => ['error',   'Invalid link', 'This verification link is not valid — it may have already been used, or it was copied incompletely. Sign in with your credentials and press "Resend verification email" to get a fresh link.'],
];
[$type, $title, $body] = $states[$result['status']] ?? $states['invalid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body>
<div class="auth-wrap">

  <aside class="auth-side">
    <a class="brand-link" href="<?= base_url('index.php') ?>"><span class="brand-mark">🌾</span> <?= APP_NAME ?></a>
    <h1>One click closer to local price transparency.</h1>
    <p>Email verification keeps the platform free of fake accounts, so farmers and verified
       buyers can trade with confidence in Carmen, North Cotabato.</p>
    <ul class="auth-perks">
      <li><span class="tick">✓</span> Daily farm-gate prices for Carmen, North Cotabato</li>
      <li><span class="tick">✓</span> Buyer accounts reviewed by an administrator</li>
      <li><span class="tick">✓</span> Contact details never shown publicly</li>
    </ul>
  </aside>

  <main class="auth-main">
    <div class="auth-card glass-strong">
      <div class="alert alert-<?= e($type) ?>" style="margin-bottom:18px;">
        <strong><?= e($title) ?></strong><br><?= e($body) ?>
      </div>
      <button type="button" class="btn btn-primary btn-block"
              onclick="location.href='<?= base_url('login.php') ?>'">Go to sign in</button>
      <div class="auth-foot">
        New to <?= APP_NAME ?>? <a href="<?= base_url('register.php') ?>"><strong>Create an account</strong></a>
      </div>
    </div>
  </main>
</div>

<script src="<?= asset_url('js/main.js') ?>"></script>
</body>
</html>
