<?php
/**
 * helpers.php - shared helper functions.
 */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Application URL prefix (e.g. "" when installed at the doc root,
 * "/AgriPrice" when installed in a sub-folder).
 */
if (!defined('APP_BASE')) {
    define('APP_BASE', (static function (): string {
        $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        $appRoot = realpath(__DIR__ . '/..');
        $appRoot = $appRoot === false ? __DIR__ . '/..' : str_replace('\\', '/', $appRoot);
        if ($docRoot !== '' && strncasecmp($appRoot, $docRoot, strlen($docRoot)) === 0) {
            return rtrim(substr($appRoot, strlen($docRoot)), '/');
        }
        return '';
    })());
}

function redirect(string $url): never
{
    // If it's a root-relative path like /login.php, prefix with base if needed
    if (strpos($url, '://') === false && $url !== '' && $url[0] === '/') {
        $url = base_url(ltrim($url, '/'));
    }
    header('Location: ' . $url);
    exit;
}

function base_url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

/**
 * Absolute URL (scheme + host + base) for links that leave the current
 * page context — e.g. links inside emails, where the client cannot
 * resolve root-relative paths like /App/page.php.
 * APP_URL (e.g. 'https://example.com') overrides auto-detection when set.
 */
function absolute_url(string $path = ''): string
{
    $relative = base_url($path);
    if (strpos($relative, '://') !== false) {
        return $relative;                                  // APP_BASE already absolute
    }
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(APP_URL, '/') . $relative;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host . $relative;
}

function asset_url(string $path = ''): string
{
    $url = base_url('assets/' . ltrim($path, '/'));

    // Version stamp (?v=modified-time) so the URL changes whenever the file
    // does — browsers can never serve a stale cached stylesheet or script.
    $local = dirname(__DIR__) . '/assets/' . ltrim($path, '/');
    if ($path !== '' && is_file($local)) {
        $url .= '?v=' . filemtime($local);
    }

    return $url;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your session expired. Please go back and try again.');
    }
}

function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function get(string $key, string $default = ''): string
{
    return trim((string)($_GET[$key] ?? $default));
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------------------------------------------------------------------
 * Remember-me cookie.
 * Stores the USERNAME ONLY — never a password — so the login form can
 * pre-fill the identifier on this device after a logout. Cleared when
 * the user signs back in without ticking the checkbox.
 * ------------------------------------------------------------------- */
function remember_get(): string
{
    return isset($_COOKIE['agriprice_remember']) ? trim((string)$_COOKIE['agriprice_remember']) : '';
}

function remember_set(string $username): void
{
    setcookie('agriprice_remember', $username, [
        'expires'  => time() + 30 * 86400,   // valid for 30 days
        'path'     => '/',
        'httponly' => true,                  // unreadable from JavaScript
        'samesite' => 'Lax',
        'secure'   => false,                 // local HTTP development
    ]);
}

function remember_clear(): void
{
    setcookie('agriprice_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function money($amount): string
{
    return '₱' . number_format((float)$amount, 2);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function stage_label(string $stage): string
{
    return ucwords(str_replace('_', ' ', $stage));
}

function truncate(string $text, int $length = 60): string
{
    $text = trim($text);
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/* ---------------------------------------------------------------------
 * Table controls: search box, rows-per-page selector and pagination.
 * Each table owns a parameter prefix (e.g. "u" -> uq / ulimit / upage)
 * so several tables can live on one page without clashing.
 * ------------------------------------------------------------------ */

function table_controls(array $opts = []): array
{
    $pre    = (string)($opts['prefix'] ?? '');
    $q      = trim((string)($_GET[$pre . 'q'] ?? ''));
    $sizes  = [10, 25, 50, 100];
    $dflt   = (int)($opts['default_limit'] ?? 10);
    $limit  = (int)($_GET[$pre . 'limit'] ?? $dflt);
    if (!in_array($limit, $sizes, true)) {
        $limit = in_array($dflt, $sizes, true) ? $dflt : 10;
    }
    $keep = [];
    foreach ((array)($opts['keep'] ?? []) as $k => $v) {
        if ($v !== null && $v !== '') {
            $keep[(string)$k] = (string)$v;
        }
    }

    return [
        'prefix' => $pre,
        'action' => (string)($opts['action'] ?? ''),
        'q'      => $q,
        'limit'  => $limit,
        'page'   => max(1, (int)($_GET[$pre . 'page'] ?? 1)),
        'pages'  => 1,
        'total'  => 0,
        'offset' => 0,
        'keep'   => $keep,
    ];
}

function table_paginate(array $controls, int $total): array
{
    $controls['total']  = max(0, $total);
    $controls['pages']  = max(1, (int)ceil($controls['total'] / $controls['limit']));
    if ($controls['page'] > $controls['pages']) {
        $controls['page'] = $controls['pages'];
    }
    $controls['offset'] = ($controls['page'] - 1) * $controls['limit'];
    return $controls;
}

function table_url(array $controls, int $page): string
{
    $pre   = $controls['prefix'];
    $query = $controls['keep'];
    if ($controls['q'] !== '') {
        $query[$pre . 'q'] = $controls['q'];
    }
    $query[$pre . 'limit'] = $controls['limit'];
    if ($page > 1) {
        $query[$pre . 'page'] = $page;
    }
    $qs = http_build_query($query);
    return $controls['action'] . ($qs === '' ? '' : '?' . $qs);
}

function table_search_bar(array $controls, string $placeholder = 'Search…'): string
{
    $pre   = $controls['prefix'];
    $html  = '<form class="table-bar" method="get" action="' . e($controls['action']) . '">';
    $html .= '<input class="table-search" type="search" name="' . $pre . 'q" value="'
           . e($controls['q']) . '" placeholder="' . e($placeholder) . '" aria-label="Search">';
    $html .= '<span class="table-rows">Rows <select name="' . $pre . 'limit" data-autosubmit aria-label="Rows per page">';
    foreach ([10, 25, 50, 100] as $n) {
        $html .= '<option value="' . $n . '"' . ($n === $controls['limit'] ? ' selected' : '') . '>' . $n . '</option>';
    }
    $html .= '</select></span>';
    foreach ($controls['keep'] as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $html .= '<button class="btn btn-outline btn-sm" type="submit">Search</button>';
    $html .= '<span class="table-count">' . $controls['total'] . ' row' . ($controls['total'] === 1 ? '' : 's') . '</span>';
    $html .= '</form>';
    return $html;
}

function table_pager(array $controls): string
{
    if ($controls['pages'] <= 1) {
        return '';
    }
    $page  = $controls['page'];
    $pages = $controls['pages'];
    $href  = static function (int $p) use ($controls): string {
        return e(table_url($controls, $p));
    };

    $links  = $page > 1
        ? '<a href="' . $href($page - 1) . '" title="Previous page">&lsaquo;</a>'
        : '<span class="disabled">&lsaquo;</span>';
    $start  = max(1, $page - 2);
    $end    = min($pages, $page + 2);
    if ($start > 1) {
        $links .= '<a href="' . $href(1) . '">1</a>';
        if ($start > 2) {
            $links .= '<span class="disabled">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $links .= $i === $page
            ? '<span class="current">' . $i . '</span>'
            : '<a href="' . $href($i) . '">' . $i . '</a>';
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $links .= '<span class="disabled">…</span>';
        }
        $links .= '<a href="' . $href($pages) . '">' . $pages . '</a>';
    }
    $links .= $page < $pages
        ? '<a href="' . $href($page + 1) . '" title="Next page">&rsaquo;</a>'
        : '<span class="disabled">&rsaquo;</span>';

    return '<div class="table-pager">'
         . '<span class="table-count">Page ' . $page . ' of ' . $pages . ' · ' . $controls['total'] . ' rows</span>'
         . '<div class="pager-links">' . $links . '</div>'
         . '</div>';
}

function table_empty(string $icon, string $text, int $cols): string
{
    return '<tr><td colspan="' . $cols . '"><div class="empty-state"><span class="empty-icon">'
         . $icon . '</span>' . e($text) . '</div></td></tr>';
}

/* ---------------------------------------------------------------------
 * Modal shells: modal_open() prints the dialog chrome, the page prints
 * its <form>, and modal_close() shuts the wrappers.
 * ------------------------------------------------------------------ */

function modal_open(string $id, string $title, string $size = ''): string
{
    $cls = $size === 'sm' ? ' modal-sm' : '';
    return '<div class="modal' . $cls . '" id="' . e($id) . '" role="dialog" aria-modal="true" aria-label="' . e($title) . '">'
         . '<div class="modal-card">'
         . '<div class="modal-head">'
         .   '<h3 class="modal-title">' . e($title) . '</h3>'
         .   '<button class="modal-close" type="button" data-modal-close aria-label="Close dialog">✕</button>'
         . '</div>'
         . '<div class="modal-body">';
}

function modal_close(): string
{
    return '</div></div></div>';
}
