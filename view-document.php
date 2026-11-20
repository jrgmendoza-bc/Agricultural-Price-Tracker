<?php
/**
 * view-document.php - streams one verification document to its owner
 * or to an administrator. Uploaded files live outside direct web access
 * (uploads/.htaccess denies everything); this script is the only way in.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = Auth::requireLogin();

$docId = (int)get('id');
$doc = $docId > 0 ? Database::first(
    "SELECT * FROM verification_documents WHERE id = ?",
    [$docId]
) : null;

$isAdmin = $user['role'] === 'admin';
$isOwner = $doc !== null && (int)$doc['user_id'] === (int)$user['id'];

if (!$doc || (!$isAdmin && !$isOwner)) {
    http_response_code(404);
    exit('Document not found.');
}

$path = VerificationDocs::absolutePath($doc);
if (!is_file($path)) {
    http_response_code(404);
    exit('The document file is missing from storage.');
}

$mime = (string)$doc['mime_type'];
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

// Inline in the browser where the type is renderable; PDFs get a safe filename.
$disposition = str_starts_with($mime, 'image/') ? 'inline' : 'inline';
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$doc['original_name'])) ?: 'document';
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');

readfile($path);
