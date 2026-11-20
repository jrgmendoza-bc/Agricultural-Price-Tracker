<?php
/**
 * uploads.php - verification document uploads.
 * Farmers and buyers upload photos of their ID, Business/DTI permit or any
 * supporting document during registration (or later from their account page).
 * An administrator reviews the files before approving the account.
 */

class VerificationDocs
{
    public const TYPES = [
        'id'             => 'Government-issued ID',
        'business_permit' => 'Business / DTI Permit',
        'other'          => 'Other supporting document',
    ];

    /** Allowed MIME types => canonical extension. */
    private const ALLOWED = [
        'image/jpeg'     => 'jpg',
        'image/png'      => 'png',
        'image/webp'     => 'webp',
        'application/pdf' => 'pdf',
    ];

    public const MAX_BYTES = 5242880;   // 5 MB per file
    public const MAX_FILES  = 6;        // documents per user

    /* ----------------------------------------------------------------
     * Storage paths
     * -------------------------------------------------------------- */

    public static function baseDir(): string
    {
        return dirname(__DIR__) . '/uploads/verification';
    }

    public static function userDir(int $userId): string
    {
        return self::baseDir() . '/' . $userId;
    }

    public static function absolutePath(array $doc): string
    {
        return self::userDir((int)$doc['user_id']) . '/' . basename((string)$doc['stored_name']);
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? 'Other supporting document';
    }

    /* ----------------------------------------------------------------
     * Validation (operates on one $_FILES entry)
     * -------------------------------------------------------------- */

    /** Returns a normalized [ext, mime] pair, or an error string. */
    private static function validateFile(array $file): array
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'No file was selected.'];
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['error' => 'The file is larger than the server limit (5 MB allowed).'];
        }
        if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return ['error' => 'The upload failed. Please try again.'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            return ['error' => 'The selected file is empty.'];
        }
        if ($size > self::MAX_BYTES) {
            return ['error' => 'Each document must be 5 MB or smaller.'];
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            return ['error' => 'Only JPG, PNG, WebP images or PDF documents are allowed.'];
        }
        return ['ok' => true, 'mime' => $mime, 'ext' => self::ALLOWED[$mime]];
    }

    /**
     * Validate a batch of files (array of $_FILES-style entries).
     * Returns ['ok' => true, 'files' => [...]] or ['error' => '...'].
     */
    private static function validateBatch(array $entries): array
    {
        $files = [];
        foreach ($entries as $entry) {
            if ((int)($entry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;                       // optional slot left empty
            }
            $check = self::validateFile($entry);
            if (isset($check['error'])) {
                return ['error' => $check['error']];
            }
            $files[] = ['file' => $entry, 'mime' => $check['mime'], 'ext' => $check['ext']];
        }
        return ['ok' => true, 'files' => $files];
    }

    /* ----------------------------------------------------------------
     * Registration flow: doc_id (required), doc_permit, doc_other[]
     * Files are fully validated BEFORE the user row is inserted, then
     * persist() moves them once the account exists.
     * -------------------------------------------------------------- */

    public static function validateRegistration(): array
    {
        $idFile = $_FILES['doc_id'] ?? null;
        if ($idFile === null || (int)($idFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'Please upload a photo or scan of your government-issued ID.'];
        }
        $idCheck = self::validateFile($idFile);
        if (isset($idCheck['error'])) {
            return ['error' => 'ID document: ' . $idCheck['error']];
        }

        $entries = [
            ['type' => 'id', 'file' => $idFile, 'mime' => $idCheck['mime'], 'ext' => $idCheck['ext']],
        ];

        $permit = $_FILES['doc_permit'] ?? null;
        if ($permit !== null && (int)($permit['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $pCheck = self::validateFile($permit);
            if (isset($pCheck['error'])) {
                return ['error' => 'Business / DTI permit: ' . $pCheck['error']];
            }
            $entries[] = ['type' => 'business_permit', 'file' => $permit, 'mime' => $pCheck['mime'], 'ext' => $pCheck['ext']];
        }

        $others = $_FILES['doc_other'] ?? null;
        if (is_array($others ?? null) && isset($others['name']) && is_array($others['name'])) {
            foreach ($others['name'] as $i => $name) {
                if ((int)($others['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $single = [
                    'name' => $name,
                    'type' => $others['type'][$i] ?? '',
                    'tmp_name' => $others['tmp_name'][$i] ?? '',
                    'error' => $others['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $others['size'][$i] ?? 0,
                ];
                $oCheck = self::validateFile($single);
                if (isset($oCheck['error'])) {
                    return ['error' => 'Other document: ' . $oCheck['error']];
                }
                $entries[] = ['type' => 'other', 'file' => $single, 'mime' => $oCheck['mime'], 'ext' => $oCheck['ext']];
            }
        }

        if (count($entries) > self::MAX_FILES) {
            return ['error' => 'A maximum of ' . self::MAX_FILES . ' documents may be uploaded.'];
        }
        return ['ok' => true, 'entries' => $entries];
    }

    /** Account-page upload field: docs[] (multiple) + doc_type select. */
    public static function validateAccountUpload(): array
    {
        $type = (string)($_POST['doc_type'] ?? 'other');
        if (!isset(self::TYPES[$type])) {
            $type = 'other';
        }
        $batch = $_FILES['docs'] ?? null;
        if ($batch === null) {
            return ['error' => 'Please choose at least one file to upload.'];
        }
        // Support both a single file input and a multiple="true" one.
        $entries = [];
        if (is_array($batch['name'] ?? null)) {
            foreach ($batch['name'] as $i => $name) {
                $entries[] = [
                    'name' => $name,
                    'type' => $batch['type'][$i] ?? '',
                    'tmp_name' => $batch['tmp_name'][$i] ?? '',
                    'error' => $batch['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $batch['size'][$i] ?? 0,
                ];
            }
        } else {
            $entries[] = $batch;
        }
        $check = self::validateBatch($entries);
        if (isset($check['error'])) {
            return ['error' => $check['error']];
        }
        if (!$check['files']) {
            return ['error' => 'Please choose at least one file to upload.'];
        }
        $out = [];
        foreach ($check['files'] as $f) {
            $out[] = ['type' => $type, 'file' => $f['file'], 'mime' => $f['mime'], 'ext' => $f['ext']];
        }
        return ['ok' => true, 'entries' => $out];
    }

    /* ----------------------------------------------------------------
     * Persistence
     * -------------------------------------------------------------- */

    /**
     * Move validated files to disk and insert their rows.
     * Returns true on success; on failure any half-saved state is cleaned up.
     */
    public static function persist(int $userId, array $entries): bool
    {
        if (!$entries) {
            return true;
        }
        $dir = self::userDir($userId);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        $saved = [];
        foreach ($entries as $e) {
            $stored = bin2hex(random_bytes(16)) . '.' . $e['ext'];
            $dest   = $dir . '/' . $stored;
            if (!move_uploaded_file($e['file']['tmp_name'], $dest)) {
                self::forgetFiles($saved);
                return false;
            }
            $saved[] = $dest;
            Database::execute(
                "INSERT INTO verification_documents (user_id, doc_type, original_name, stored_name, mime_type, file_size)
                 VALUES (?,?,?,?,?,?)",
                [
                    $userId,
                    $e['type'],
                    mb_substr(basename((string)$e['file']['name']), 0, 255),
                    $stored,
                    $e['mime'],
                    (int)$e['file']['size'],
                ]
            );
        }
        return true;
    }

    private static function forgetFiles(array $paths): void
    {
        foreach ($paths as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }

    /* ----------------------------------------------------------------
     * Queries / maintenance
     * -------------------------------------------------------------- */

    public static function listForUser(int $userId): array
    {
        return Database::select(
            "SELECT * FROM verification_documents WHERE user_id = ? ORDER BY doc_type, id",
            [$userId]
        );
    }

    public static function countForUser(int $userId): int
    {
        return (int)Database::scalar(
            "SELECT COUNT(*) FROM verification_documents WHERE user_id = ?",
            [$userId]
        );
    }

    /** Delete one document row + file. $ownerId (when given) restricts access. */
    public static function delete(int $docId, ?int $ownerId = null): bool
    {
        $sql = "SELECT * FROM verification_documents WHERE id = ?" . ($ownerId !== null ? " AND user_id = ?" : "");
        $doc = Database::first($sql, $ownerId !== null ? [$docId, $ownerId] : [$docId]);
        if (!$doc) {
            return false;
        }
        Database::execute("DELETE FROM verification_documents WHERE id = ?", [$docId]);
        $path = self::absolutePath($doc);
        if (is_file($path)) {
            @unlink($path);
        }
        return true;
    }

    /** Remove every document of a user (used when rolling back a failed registration). */
    public static function deleteAllForUser(int $userId): void
    {
        foreach (self::listForUser($userId) as $doc) {
            $path = self::absolutePath($doc);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        Database::execute("DELETE FROM verification_documents WHERE user_id = ?", [$userId]);
        $dir = self::userDir($userId);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }

    /* ----------------------------------------------------------------
     * Account-page POST handling (buyer & farmer account pages)
     * -------------------------------------------------------------- */

    /**
     * Handles the upload_documents / delete_document POST actions.
     * Call after csrf_verify() on the documents / account / overview pages;
     * redirects back to $redirectPath when done. Any change re-submits the
     * account for admin review (status returns to pending).
     */
    public static function handleAccountPost(array $user, string $redirectPath): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $action = post('action');
        if ($action === 'upload_documents') {
            $wasVerified = $user['verification_status'] === 'verified';
            $check = self::validateAccountUpload();
            if (isset($check['error'])) {
                flash($check['error'], 'error');
                redirect($redirectPath);
            }
            $free = self::MAX_FILES - self::countForUser((int)$user['id']);
            if (count($check['entries']) > $free) {
                flash('You can only upload ' . self::MAX_FILES . ' documents in total'
                    . ($free > 0 ? ' — ' . $free . ' slot(s) remaining.' : ' — you have reached the limit.'), 'error');
                redirect($redirectPath);
            }
            if (!self::persist((int)$user['id'], $check['entries'])) {
                flash('The documents could not be saved. Please try again.', 'error');
                redirect($redirectPath);
            }
            // Any change re-submits the account for admin review.
            Database::execute(
                "UPDATE users SET verification_status = 'pending', verified_at = NULL WHERE id = ?",
                [$user['id']]
            );
            flash(count($check['entries']) . ' document(s) uploaded. '
                . ($wasVerified
                    ? 'Your account was returned to pending — an administrator will re-review your documents.'
                    : 'An administrator will review them shortly.'), 'success');
            redirect($redirectPath);
        }

        if ($action === 'delete_document') {
            $wasVerified = $user['verification_status'] === 'verified';
            $docId = (int)post('doc_id');
            if ($docId > 0 && self::delete($docId, (int)$user['id'])) {
                if ($wasVerified) {
                    Database::execute(
                        "UPDATE users SET verification_status = 'pending', verified_at = NULL WHERE id = ?",
                        [$user['id']]
                    );
                    flash('Document removed. Your account was returned to pending for re-review.', 'success');
                } else {
                    flash('Document removed.', 'success');
                }
            } else {
                flash('Document not found.', 'error');
            }
            redirect($redirectPath);
        }
    }

    /* ----------------------------------------------------------------
     * Shared rendering: the "Verification documents" card
     * -------------------------------------------------------------- */

    public static function cardHtml(array $user): string
    {
        $docs    = self::listForUser((int)$user['id']);
        $status  = (string)$user['verification_status'];
        $h = '';

        $badge = $status === 'verified' ? 'green' : ($status === 'pending' ? 'amber' : 'red');
        $h .= '<div class="card">'
           .  '<div class="card-head"><h3 class="card-title">📄 Verification documents</h3>'
           .  '<span class="badge badge-' . $badge . '">' . e($status) . '</span></div>'
           .  '<div class="card-body">';

        if ($docs) {
            $h .= '<div class="doc-grid">';
            foreach ($docs as $doc) {
                $viewUrl = base_url('view-document.php?id=' . (int)$doc['id']);
                $isImage = str_starts_with((string)$doc['mime_type'], 'image/');
                $thumb = $isImage
                    ? '<img src="' . e($viewUrl) . '" alt="' . e($doc['original_name']) . '" loading="lazy">'
                    : '<span class="doc-pdf">📄</span>';
                $h .= '<div class="doc-tile">'
                   .  '<a class="doc-thumb" href="' . e($viewUrl) . '" target="_blank" rel="noopener" title="Open full size">' . $thumb . '</a>'
                   .  '<div class="doc-meta">'
                   .    '<span class="doc-type">' . e(self::typeLabel((string)$doc['doc_type'])) . '</span>'
                   .    '<span class="doc-name" title="' . e($doc['original_name']) . '">' . e(truncate((string)$doc['original_name'], 28)) . '</span>'
                   .    '<span class="small muted">' . e(number_format(((int)$doc['file_size']) / 1024)) . ' KB · ' . e(date('M j, Y', strtotime($doc['uploaded_at']))) . '</span>'
                   .  '</div>'
                   .  '<form method="post" class="doc-remove" data-confirm="This document will be permanently deleted."'
                   .  ' data-confirm-title="Remove document?" data-confirm-label="Remove">'
                   .  csrf_field()
                   .  '<input type="hidden" name="action" value="delete_document">'
                   .  '<input type="hidden" name="doc_id" value="' . (int)$doc['id'] . '">'
                   .  '<button class="btn btn-ghost btn-sm" type="submit" title="Remove document">✕</button>'
                   .  '</form>'
                   .  '</div>';
            }
            $h .= '</div>';
        } else {
            $h .= '<p class="small muted" style="margin:0 0 12px">No documents uploaded yet — upload your ID and permits below.</p>';
        }

        $slots = self::MAX_FILES - count($docs);
        if ($slots > 0) {
            $h .= '<form method="post" enctype="multipart/form-data" class="doc-upload-form" style="margin-top:14px">'
               .  csrf_field()
               .  '<input type="hidden" name="action" value="upload_documents">'
               .  '<div class="form-grid">'
               .    '<div class="form-group">'
               .      '<label for="doc_type">Document type</label>'
               .      '<select id="doc_type" name="doc_type">';
            foreach (self::TYPES as $val => $label) {
                $h .= '<option value="' . e($val) . '">' . e($label) . '</option>';
            }
            $h .=    '</select>'
               .    '</div>'
               .    '<div class="form-group">'
               .      '<label for="docs">Files (' . $slots . ' slot' . ($slots === 1 ? '' : 's') . ' left)</label>'
               .      '<input type="file" id="docs" name="docs[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">'
               .    '</div>'
               .  '</div>'
               .  '<div class="hint">JPG, PNG, WebP or PDF · max 5 MB each · up to ' . self::MAX_FILES . ' documents in total.</div>'
               .  '<div style="margin-top:10px"><button class="btn btn-primary btn-sm" type="submit">Upload documents</button></div>'
               .  '</form>';
        } else {
            $h .= '<div class="hint" style="margin-top:12px">You have reached the maximum of '
               .  self::MAX_FILES . ' documents. Remove one to upload a replacement.</div>';
        }

        if ($status === 'verified') {
            $h .= '<div class="hint" style="margin-top:10px">These documents were reviewed and approved'
               .  ($user['verified_at'] ? ' on ' . e(date('F j, Y', strtotime($user['verified_at']))) : '') . '.'
               .  ' Uploading or removing a document returns your account to <strong>pending</strong> for re-review.</div>';
        } elseif ($status === 'pending' && $docs) {
            $h .= '<div class="hint" style="margin-top:10px">Your documents are queued for review by an administrator.</div>';
        }

        $h .= '</div></div>';
        return $h;
    }
}
