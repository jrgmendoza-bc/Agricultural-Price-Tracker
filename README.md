# AgriPriceTrack (plain PHP / MySQL build)

1. Create a database named `agriprice_db` and import `database/agriprice_db.sql`.
   Local demo sign-ins only, not for production (password is `admin` for admin, farmer, buyer and pedrotrader; `admin123` for roytapz27).
2. Set the database login in `includes/db.php` if it is not the XAMPP default (root, no password).
3. Email (account verification): copy `includes/mail_config.local.example.php` to
   `includes/mail_config.local.php` and enter the mailbox credentials, or set the
   `SMTP_USER` / `SMTP_PASS` environment variables. Without them, registration cannot send its
   verification email and is rolled back.
4. PHP and MySQL should use the same time zone (the app uses Asia/Manila), otherwise the
   15-minute login lockout window is computed against the wrong clock.
5. Passwords are stored with `password_hash()`. A database that still has plain-text passwords
   can be converted once with `php database/hash_existing_passwords.php`.

## Before deploying

The demo accounts and their passwords are for local testing only. Delete or change them on any public server.
Credentials (`includes/mail_config.local.php`) and uploaded verification documents are git-ignored; keep it that way.
