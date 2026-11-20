<?php
/**
 * mailer.php - minimal native SMTP client (Hostinger) + account email verification.
 */

/* Optional local override (not part of the submitted source): copy
 * includes/mail_config.local.example.php to includes/mail_config.local.php
 * and fill in the mailbox credentials. Environment variables also work. */
if (is_file(__DIR__ . '/mail_config.local.php')) {
    require __DIR__ . '/mail_config.local.php';
}

/* --- Hostinger SMTP settings: credentials come from the SMTP_USER / SMTP_PASS environment variables --- */
if (!defined('SMTP_HOST'))       define('SMTP_HOST', 'smtp.hostinger.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT', 465);        // 465 = SSL, 587 = STARTTLS
if (!defined('SMTP_SECURE'))     define('SMTP_SECURE', 'ssl');    // 'ssl' | 'tls' | 'none'
if (!defined('SMTP_USER'))       define('SMTP_USER', getenv('SMTP_USER') ?: '');         // full mailbox, set in server environment
if (!defined('SMTP_PASS'))       define('SMTP_PASS', getenv('SMTP_PASS') ?: '');         // mailbox password, set in server environment, never in source
if (!defined('MAIL_FROM'))       define('MAIL_FROM', '');         // empty = same as SMTP_USER
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME', 'AgriPriceTrack');

class Mailer
{
    /** Send one HTML email. Returns ['ok' => bool, 'error' => ?string]. */
    public static function send(string $to, string $subject, string $html): array
    {
        if (SMTP_USER === '' || SMTP_PASS === '') {
            return ['ok' => false, 'error' => 'SMTP is not configured yet (set the SMTP_USER and SMTP_PASS environment variables).'];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient address.'];
        }

        $from = MAIL_FROM !== '' ? MAIL_FROM : SMTP_USER;
        $fp = null;

        try {
            $fp = self::connect();

            $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
            self::command($fp, $ehlo, [250]);

            if (SMTP_SECURE === 'tls') {
                self::command($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS negotiation failed.');
                }
                self::command($fp, $ehlo, [250]);
            }

            if (SMTP_USER !== '') {
                self::command($fp, 'AUTH LOGIN', [334]);
                self::command($fp, base64_encode(SMTP_USER), [334]);
                self::command($fp, base64_encode(SMTP_PASS), [235]);
            }

            self::command($fp, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($fp, 'DATA', [354]);

            $message = self::buildMessage($to, $from, $subject, $html);
            if (!str_ends_with($message, "\r\n")) {
                $message .= "\r\n";
            }
            // command() appends the final CRLF, so the wire ends with \r\n.\r\n —
            // the SMTP end-of-data marker (added after dot-stuffing, never stuffed).
            self::command($fp, $message . "\r\n.", [250]);

            @fwrite($fp, "QUIT\r\n");
            fclose($fp);

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            if (is_resource($fp)) {
                fclose($fp);
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function connect()
    {
        $remote = (SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$fp) {
            throw new RuntimeException("Could not connect to SMTP server: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 15);
        self::expect($fp, [220]);
        return $fp;
    }

    /** Send one SMTP command (dot-stuffed / CRLF-normalized for DATA) and expect a reply code. */
    private static function command($fp, string $cmd, array $codes): int
    {
        if (fwrite($fp, $cmd . "\r\n") === false) {
            throw new RuntimeException('SMTP write failed.');
        }
        return self::expect($fp, $codes);
    }

    /** Read an SMTP reply (handles multiline "NNN-" continuations) and validate its code. */
    private static function expect($fp, array $codes): int
    {
        $buf = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $buf .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break; // final line of the reply
            }
            if (strlen($line) < 4) {
                break;
            }
        }
        if ($buf === '') {
            $meta = stream_get_meta_data($fp);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'SMTP server timed out.' : 'SMTP connection closed by server.');
        }
        $code = (int)substr($buf, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error ' . trim(preg_replace('/\r|\n/', ' ', $buf)) . '.');
        }
        return $code;
    }

    /** Assemble RFC-ish message headers + body, CRLF-normalized and dot-stuffed. */
    private static function buildMessage(string $to, string $from, string $subject, string $html): string
    {
        $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
        $headers  = 'Date: ' . date('r') . "\r\n";
        $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . $from . '>' . "\r\n";
        $headers .= 'To: <' . $to . '>' . "\r\n";
        $headers .= 'Subject: ' . $subject . "\r\n";
        $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>' . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";

        $message = $headers . "\r\n" . $html;
        // Single-pass normalization: a sequential str_replace would turn each
        // existing "\r\n" into "\r\n\r\n" (blank lines that break header parsing).
        $message = preg_replace('~\r\n|\r|\n~', "\r\n", $message);
        return preg_replace('/^\./m', '..', $message);
    }
}

class EmailVerification
{
    /** Verification links stay valid for this many hours. */
    public const HOURS = 24;

    /** Issue a fresh token for the user; returns the raw token (only ever seen in the email). */
    public static function issue(int $userId): ?string
    {
        $raw = bin2hex(random_bytes(32));
        $n = Database::execute(
            'UPDATE users SET email_token_hash = ?, email_token_expires_at = DATE_ADD(NOW(), INTERVAL '
            . self::HOURS . ' HOUR) WHERE id = ?',
            [hash('sha256', $raw), $userId]
        );
        return $n > 0 ? $raw : null;
    }

    /** Issue a fresh token and email the verification link. Returns ['ok' => bool, 'error' => ?string]. */
    public static function send(int $userId): array
    {
        $user = Database::first(
            'SELECT id, first_name, email, email_verified_at, deleted_at FROM users WHERE id = ?',
            [$userId]
        );
        if ($user === null || $user['deleted_at'] !== null) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        if ($user['email_verified_at'] !== null) {
            return ['ok' => false, 'error' => 'This email address is already verified.'];
        }

        $raw = self::issue($userId);
        if ($raw === null) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }

        $url = absolute_url('verify-email.php') . '?token=' . rawurlencode($raw);
        $subject = 'Verify your email address - ' . APP_NAME;
        return Mailer::send($user['email'], $subject, self::template((string)$user['first_name'], $url));
    }

    /**
     * Check a raw token and, when valid, mark the address verified.
     * The hash is kept after success so re-clicking the link yields the
     * friendlier 'already verified' state instead of 'invalid'.
     * Returns ['ok' => bool, 'status' => 'verified'|'invalid'|'expired'|'already'].
     */
    public static function verify(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['ok' => false, 'status' => 'invalid'];
        }

        $user = Database::first(
            'SELECT id, email_verified_at, email_token_expires_at FROM users WHERE email_token_hash = ? AND deleted_at IS NULL',
            [hash('sha256', $token)]
        );
        if ($user === null) {
            return ['ok' => false, 'status' => 'invalid'];
        }

        if ($user['email_verified_at'] !== null) {
            return ['ok' => false, 'status' => 'already'];
        }

        if ($user['email_token_expires_at'] === null || strtotime((string)$user['email_token_expires_at']) < time()) {
            return ['ok' => false, 'status' => 'expired'];
        }

        Database::execute('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$user['id']]);
        return ['ok' => true, 'status' => 'verified'];
    }

    private static function template(string $name, string $url): string
    {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $app = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $hours = self::HOURS;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#f2f5ee;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr><td align="center" style="padding:32px 16px;">
      <table width="520" cellpadding="0" cellspacing="0" role="presentation" style="max-width:520px;background:#ffffff;border-radius:12px;border:1px solid #e2e8d8;">
        <tr><td style="padding:32px;">
          <div style="font-size:22px;font-weight:bold;color:#2f6b2f;">&#127806; {$app}</div>
          <h1 style="margin:24px 0 12px;font-size:20px;color:#1f2937;">Verify your email address</h1>
          <p style="color:#4b5563;line-height:1.6;margin:0 0 12px;">Hi {$name},</p>
          <p style="color:#4b5563;line-height:1.6;margin:0 0 12px;">Welcome to {$app}! Please confirm your email address so we know the account really belongs to you.</p>
          <p style="margin:28px 0;">
            <a href="{$safeUrl}" style="background:#2f6b2f;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;display:inline-block;font-weight:bold;">Verify my email</a>
          </p>
          <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0 0 12px;">This link expires in {$hours} hours. If the button does not work, copy this link into your browser:<br>
            <span style="word-break:break-all;color:#2f6b2f;">{$safeUrl}</span>
          </p>
          <p style="color:#9ca3af;font-size:12px;line-height:1.6;margin:24px 0 0;">If you did not create a {$app} account, you can safely ignore this email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
