<?php
/**
 * One-time migration for a database that still holds plain-text passwords:
 *   php database/hash_existing_passwords.php
 * Every password that is not already a password_hash() value is replaced by its hash.
 * Safe to run more than once.
 */
if (PHP_SAPI !== 'cli') { exit("Run this from the command line.\n"); }
require_once __DIR__ . '/../includes/db.php';
$pdo = getDBConnection();
$rows = $pdo->query("SELECT id, password FROM users WHERE password NOT LIKE '\$2y\$%'")->fetchAll(PDO::FETCH_ASSOC);
$upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
foreach ($rows as $r) {
    $upd->execute([password_hash($r['password'], PASSWORD_DEFAULT), $r['id']]);
}
echo count($rows) . " password(s) hashed.\n";
