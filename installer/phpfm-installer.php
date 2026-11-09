<?php
/*
 * phpFileManager 1.9.0 - guided installer
 * Part of the hardened fork by Ivan Bertotto: https://github.com/b84an/phpfm
 * (original project by Fabricio Seger Kolling: https://github.com/dulldusk/phpfm)
 *
 * What it does
 *   Instead of copying index.php and configuring it by hand, upload this single file to the
 *   server, open it in the browser and fill in a form. The installer then:
 *     1. downloads phpFileManager (GitHub tag v1.9.0, with ivanbertotto.it as a mirror),
 *     2. checks its SHA-256 fingerprint against the value pinned below (PFM_SHA256),
 *     3. writes the configuration line (bcrypt password hash, per-install secret, root folder,
 *        language, timezone) into the downloaded file and saves it under the name you chose,
 *     4. deletes itself.
 *
 * Offline servers
 *   If the server cannot reach the Internet, put the file manager source next to this installer,
 *   named phpfilemanager-1-9-0-index.txt (it is index.php from the repository, renamed). It is
 *   used instead of the download, after the same fingerprint check, and deleted at the end.
 *
 * Safety measures
 *   - Works only for PFM_TTL seconds (1 hour) after being uploaded (file mtime): an installer
 *     forgotten on a server cannot be used later by someone else to install their own manager.
 *   - Refuses to overwrite an existing file; the default file name is random.
 *   - Session-bound form token, strict Content-Security-Policy, no output of user input without
 *     escaping.
 *
 * The interface is in English, or in Italian when the browser prefers Italian (?lang=it|en
 * forces it).
 *
 * License: GPL-2.0-or-later, LGPL-2.1-or-later or MPL-1.1-or-later, like phpFileManager.
 */

error_reporting(0);
@ini_set('display_errors', '0');

define('PFM_VERSION', '1.9.0');
// Download sources, tried in order. Both must serve exactly the file whose hash is PFM_SHA256.
define('PFM_URLS', 'https://raw.githubusercontent.com/b84an/phpfm/v1.9.0/index.php '
                 . 'https://www.ivanbertotto.it/s/uploads/documents/phpfilemanager-1-9-0-index.txt');
define('PFM_SHA256', 'db9684f56d0caea5ffb6fbdf1c876b145abd2fdd935e8bf88a73911f76b7294d');
define('PFM_LOCAL', __DIR__ . DIRECTORY_SEPARATOR . 'phpfilemanager-1-9-0-index.txt');
define('PFM_TTL', 3600);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

// ---------------------------------------------------------------------------------------------
// Interface language
// ---------------------------------------------------------------------------------------------
$L = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], array('it', 'en'), true)) $L = $_GET['lang'];
elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && stripos(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2), 'it') === 0) $L = 'it';

$T = array(
 'en' => array(
  'title' => 'Install phpFileManager', 'sub' => 'Guided setup: one file, one password, done.',
  'c_php' => 'PHP %s', 'c_php_h' => 'PHP 7.3 or later is required',
  'c_wr' => 'Writable folder', 'c_wr_h' => 'the web server must be able to write into %s',
  'c_bc' => 'Strong password hashing (bcrypt)', 'c_bc_h' => 'password_hash() is missing',
  'c_mb' => 'mbstring extension', 'c_mb_h' => 'phpFileManager needs mbstring: enable it in the hosting PHP settings',
  'c_dl' => 'Download possible', 'c_dl_h' => 'neither cURL nor allow_url_fopen: upload phpfilemanager-1-9-0-index.txt next to the installer',
  'left' => 'The installer stays active for %d more minutes.',
  'http' => 'You are on plain HTTP: the password would travel unencrypted. If the hosting offers HTTPS, open this page with <code>https://</code>.',
  'fix' => 'Fix the items marked in red and reload the page.',
  'expired' => 'This installer has been on the server for more than one hour and, for safety, no longer works. Delete it and upload it again if you want to install.',
  'f_name' => 'File name', 'f_name_h' => 'A hard-to-guess name is an extra layer of protection. It will be created in %s.',
  'f_pw' => 'Password', 'f_pw2' => 'Repeat the password', 'f_pw_h' => 'At least 8 characters. Stored only as a bcrypt hash.',
  'f_root' => 'Root folder', 'f_root_h' => 'The file manager will not go outside this folder. Leave empty for the whole server (not recommended). Document root: %s',
  'f_lang' => 'Language', 'f_tz' => 'Timezone', 'f_btn' => 'Install',
  'e_tok' => 'Session expired: reload the page and try again.',
  'e_name' => 'The file name may contain only letters, digits, dot, dash and underscore, and must end with .php.',
  'e_self' => 'Choose a name different from the installer\'s.',
  'e_exists' => 'A file named %s already exists: choose another name.',
  'e_short' => 'The password must be at least 8 characters long.', 'e_long' => 'The password can be at most 72 characters long.',
  'e_match' => 'The two passwords do not match.', 'e_root' => 'The root folder does not exist on the server.',
  'e_tz' => 'Invalid timezone.',
  'e_dl' => 'Could not download phpFileManager. Download index.php from https://github.com/b84an/phpfm, rename it phpfilemanager-1-9-0-index.txt and upload it next to the installer.',
  'e_hash' => 'The downloaded file does not match the expected fingerprint: installation cancelled for safety.',
  'e_fmt' => 'Unexpected file format.', 'e_write' => 'Cannot write the file into %s. Check the permissions.',
  'ok' => 'Installed. The file manager is %s (source: %s, SHA-256 fingerprint verified).',
  'src_local' => 'local file', 'src_dl' => 'download from %s',
  'ok_del' => 'The installer deleted itself. Write down the address: the file name is part of the protection.',
  'ok_nodel' => '<strong>Delete %s from the server now</strong>: I could not do it myself.',
  'open' => 'Open phpFileManager', 'tip' => 'Tip: use it only over HTTPS and remove it when you no longer need it.',
 ),
 'it' => array(
  'title' => 'Installa phpFileManager', 'sub' => 'Installazione guidata: un file, una password, ed è pronto.',
  'c_php' => 'PHP %s', 'c_php_h' => 'serve PHP 7.3 o successivo',
  'c_wr' => 'Cartella scrivibile', 'c_wr_h' => 'il web server deve poter scrivere in %s',
  'c_bc' => 'Password sicure (bcrypt)', 'c_bc_h' => 'manca password_hash()',
  'c_mb' => 'Modulo mbstring', 'c_mb_h' => 'phpFileManager richiede l\'estensione mbstring: attivala dal pannello PHP dell\'hosting',
  'c_dl' => 'Download possibile', 'c_dl_h' => 'né cURL né allow_url_fopen: carica accanto il file phpfilemanager-1-9-0-index.txt',
  'left' => 'L\'installer resta attivo ancora %d minuti.',
  'http' => 'Stai usando HTTP: la password viaggerebbe in chiaro. Se l\'hosting offre HTTPS, apri questa pagina con <code>https://</code>.',
  'fix' => 'Risolvi i punti segnati in rosso e ricarica la pagina.',
  'expired' => 'Questo installer è sul server da più di un\'ora e per sicurezza non funziona più. Cancellalo e caricalo di nuovo se vuoi installare.',
  'f_name' => 'Nome del file', 'f_name_h' => 'Un nome difficile da indovinare è una protezione in più. Verrà creato in %s.',
  'f_pw' => 'Password', 'f_pw2' => 'Ripeti la password', 'f_pw_h' => 'Almeno 8 caratteri. Viene salvata solo come hash bcrypt.',
  'f_root' => 'Cartella radice', 'f_root_h' => 'Il file manager non uscirà da questa cartella. Lascia vuoto per l\'intero server (sconsigliato). Document root: %s',
  'f_lang' => 'Lingua', 'f_tz' => 'Fuso orario', 'f_btn' => 'Installa',
  'e_tok' => 'Sessione scaduta: ricarica la pagina e riprova.',
  'e_name' => 'Il nome del file può contenere solo lettere, numeri, punto, trattino e trattino basso, e deve finire con .php.',
  'e_self' => 'Scegli un nome diverso da quello dell\'installer.',
  'e_exists' => 'Esiste già un file chiamato %s: scegli un altro nome.',
  'e_short' => 'La password deve avere almeno 8 caratteri.', 'e_long' => 'La password può avere al massimo 72 caratteri.',
  'e_match' => 'Le due password non coincidono.', 'e_root' => 'La cartella radice non esiste sul server.',
  'e_tz' => 'Fuso orario non valido.',
  'e_dl' => 'Non riesco a scaricare phpFileManager. Scarica index.php da https://github.com/b84an/phpfm, rinominalo phpfilemanager-1-9-0-index.txt e caricalo accanto all\'installer.',
  'e_hash' => 'Il file scaricato non corrisponde all\'impronta attesa: installazione annullata per sicurezza.',
  'e_fmt' => 'Formato del file inatteso.', 'e_write' => 'Non riesco a scrivere il file nella cartella %s. Controlla i permessi.',
  'ok' => 'Installato. Il file manager è %s (fonte: %s, impronta SHA-256 verificata).',
  'src_local' => 'file locale', 'src_dl' => 'download da %s',
  'ok_del' => 'L\'installer si è cancellato da solo. Segnati l\'indirizzo: il nome del file fa parte della protezione.',
  'ok_nodel' => '<strong>Cancella subito %s</strong> dal server: non sono riuscito a farlo da solo.',
  'open' => 'Apri phpFileManager', 'tip' => 'Consiglio: usalo solo su HTTPS e rimuovilo quando non ti serve più.',
 ),
);

// Translated string; extra arguments are passed to sprintf(). Callers escape the arguments.
function t($k) {
    global $T, $L;
    $a = func_get_args(); array_shift($a);
    return $a ? vsprintf($T[$L][$k], $a) : $T[$L][$k];
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Same generator used by phpFileManager for its own secret.
function rand_hex($n) {
    if (function_exists('random_bytes')) { try { return bin2hex(random_bytes($n)); } catch (Exception $e) {} }
    if (function_exists('openssl_random_pseudo_bytes')) return bin2hex(openssl_random_pseudo_bytes($n));
    $o = ''; for ($i = 0; $i < $n * 2; $i++) $o .= dechex(mt_rand(0, 15)); return $o;
}

// HTTP GET with cURL (TLS verification on) or, failing that, with allow_url_fopen.
function download($url) {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 90, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'phpfm-installer/' . PFM_VERSION,
        ));
        $d = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if (is_string($d) && $code == 200) return $d;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array('http' => array('timeout' => 90, 'user_agent' => 'phpfm-installer/' . PFM_VERSION)));
        $d = @file_get_contents($url, false, $ctx);
        if (is_string($d) && strlen($d)) return $d;
    }
    return false;
}

// ---------------------------------------------------------------------------------------------
// Environment checks
// ---------------------------------------------------------------------------------------------
$dir = __DIR__;
$age = time() - (int)@filemtime(__FILE__);
$expired = $age > PFM_TTL;
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
      || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// Each check: label, passed?, hint shown when it fails.
$checks = array(
    array(t('c_php', PHP_VERSION), version_compare(PHP_VERSION, '7.3.0', '>='), t('c_php_h')),
    array(t('c_wr'), is_writable($dir), t('c_wr_h', $dir)),
    array(t('c_bc'), function_exists('password_hash'), t('c_bc_h')),
    array(t('c_mb'), extension_loaded('mbstring'), t('c_mb_h')),
    array(t('c_dl'), function_exists('curl_init') || ini_get('allow_url_fopen') || is_file(PFM_LOCAL), t('c_dl_h')),
);
$all_ok = !$expired;
foreach ($checks as $c) if (!$c[1]) $all_ok = false;

// Languages supported by phpFileManager (code => native name).
$languages = array('it' => 'Italiano', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español',
    'pt' => 'Português', 'nl' => 'Nederlands', 'pl' => 'Polski', 'ca' => 'Català', 'sq' => 'Shqip', 'sr' => 'Srpski',
    'tr' => 'Türkçe', 'ru' => 'Русский', 'ua' => 'Українська', 'cn' => '中文', 'ja' => '日本語', 'ko' => '한국어',
    'id' => 'Bahasa Indonesia', 'fa' => 'فارسی', 'ur' => 'اردو');

// Form token bound to the PHP session (stops other sites from submitting the form).
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
if (empty($_SESSION['pfm_inst_token'])) $_SESSION['pfm_inst_token'] = rand_hex(16);
$token = $_SESSION['pfm_inst_token'];

$tz_default = @date_default_timezone_get();
$v = array(
    'name' => 'fm-' . rand_hex(4) . '.php',
    'root' => $dir,
    'lang' => $L,
    'tz' => ($tz_default && $tz_default !== 'UTC') ? $tz_default : ($L === 'it' ? 'Europe/Rome' : 'UTC'),
);
$errors = array();
$done = null;

// ---------------------------------------------------------------------------------------------
// Installation
// ---------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $all_ok) {
    foreach (array('name', 'root', 'lang', 'tz') as $k) if (isset($_POST[$k]) && is_string($_POST[$k])) $v[$k] = trim($_POST[$k]);
    $pw = isset($_POST['pw']) && is_string($_POST['pw']) ? $_POST['pw'] : '';
    $pw2 = isset($_POST['pw2']) && is_string($_POST['pw2']) ? $_POST['pw2'] : '';

    // 1. Validate the form
    if (!isset($_POST['t']) || !is_string($_POST['t']) || !hash_equals($token, $_POST['t'])) $errors[] = t('e_tok');
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}\.php$/', $v['name'])) $errors[] = t('e_name');
    elseif (strtolower($v['name']) === strtolower(basename(__FILE__))) $errors[] = t('e_self');
    elseif (file_exists($dir . DIRECTORY_SEPARATOR . $v['name'])) $errors[] = t('e_exists', $v['name']);
    if (strlen($pw) < 8) $errors[] = t('e_short');
    elseif ($pw !== $pw2) $errors[] = t('e_match');
    if (strlen($pw) > 72) $errors[] = t('e_long'); // bcrypt ignores everything after 72 bytes
    $root = $v['root'];
    if ($root !== '') {
        // a PHP closing tag in the path would end the comment that holds the configuration line
        if (strpos($root, '?>') !== false || strpos($root, "\0") !== false || !is_dir($root)) $errors[] = t('e_root');
        else $root = rtrim(realpath($root), '/\\') . DIRECTORY_SEPARATOR;
    }
    if (!isset($languages[$v['lang']])) $v['lang'] = 'en';
    if (!in_array($v['tz'], DateTimeZone::listIdentifiers(), true)) $errors[] = t('e_tz');

    if (!$errors) {
        // 2. Get the source: local copy first (offline servers), then the download URLs
        $src = is_file(PFM_LOCAL) ? @file_get_contents(PFM_LOCAL) : false;
        $origin = t('src_local');
        if (!is_string($src) || !hash_equals(PFM_SHA256, hash('sha256', $src))) {
            $src = false;
            foreach (explode(' ', PFM_URLS) as $url) {
                $d = download($url);
                if (is_string($d) && hash_equals(PFM_SHA256, hash('sha256', $d))) {
                    $src = $d; $origin = t('src_dl', parse_url($url, PHP_URL_HOST)); break;
                }
                if (is_string($d)) $src = $d; // keep it, to report a fingerprint mismatch
            }
        }
        // 3. Verify the fingerprint
        if (!is_string($src)) {
            $errors[] = t('e_dl');
        } elseif (!hash_equals(PFM_SHA256, hash('sha256', $src))) {
            $errors[] = t('e_hash');
        } else {
            // 4. Replace line 2 (the "//{...}" JSON config read by phpFileManager's config class)
            $lines = preg_split('/(?<=\n)/', $src, 3);
            if (count($lines) < 3 || strpos($lines[0], '<?php') !== 0 || strpos($lines[1], '//{"') !== 0) {
                $errors[] = t('e_fmt');
            } else {
                $cfg = json_decode(trim(substr($lines[1], 2)), true);
                if (!is_array($cfg)) $cfg = array();          // keeps defaults such as the upload filter
                $cfg['fm_lang'] = $v['lang'];
                $cfg['fm_root'] = $root;
                $cfg['fm_timezone'] = $v['tz'];
                $cfg['fm_pass_md5'] = '';
                $cfg['fm_secret'] = rand_hex(32);
                $cfg['fm_pass_hash'] = password_hash($pw, PASSWORD_DEFAULT);
                // JSON_HEX_TAG: no "<" or ">" can appear, so the line can never close the PHP block
                $line = '//' . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP) . "\r\n";
                // 5. Atomic write: temporary file + rename
                $dest = $dir . DIRECTORY_SEPARATOR . $v['name'];
                $tmp = $dest . '.' . rand_hex(4) . '.tmp';
                $ok = @file_put_contents($tmp, $lines[0] . $line . $lines[2], LOCK_EX) !== false;
                if ($ok) { @chmod($tmp, 0644); $ok = @rename($tmp, $dest); }
                if (!$ok) {
                    @unlink($tmp);
                    $errors[] = t('e_write', $dir);
                } else {
                    // 6. Clean up: the installer and the local source copy remove themselves
                    $deleted = @unlink(__FILE__);
                    if (is_file(PFM_LOCAL)) @unlink(PFM_LOCAL);
                    $_SESSION = array();
                    $done = array('name' => $v['name'], 'deleted' => $deleted, 'origin' => $origin);
                }
            }
        }
    }
}
?><!doctype html>
<html lang="<?php echo $L; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo h(t('title')) . ' ' . PFM_VERSION; ?></title>
<style>
:root { --bg1:#061a3a; --bg2:#0b3d91; --glass:rgba(255,255,255,.09); --brd:rgba(255,255,255,.22); --txt:#eef4ff; --mut:#a9bfe6; --acc:#5fb4ff; --ok:#4ade80; --ko:#fb7185; }
* { box-sizing:border-box; }
body { margin:0; min-height:100vh; font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--txt);
  background:radial-gradient(circle at 15% 10%, #1e6fd9 0, transparent 45%), radial-gradient(circle at 85% 90%, #0ea5e9 0, transparent 40%), linear-gradient(160deg,var(--bg1),var(--bg2));
  display:flex; align-items:center; justify-content:center; padding:24px 16px; }
.card { width:100%; max-width:560px; background:var(--glass); border:1px solid var(--brd); border-radius:20px; padding:28px;
  backdrop-filter:blur(18px) saturate(140%); -webkit-backdrop-filter:blur(18px) saturate(140%); box-shadow:0 20px 60px rgba(0,0,0,.35); }
h1 { margin:0 0 4px; font-size:1.5rem; } .sub { margin:0 0 20px; color:var(--mut); font-size:.95rem; }
ul.chk { list-style:none; padding:0; margin:0 0 20px; font-size:.92rem; } ul.chk li { padding:4px 0; }
.y { color:var(--ok); } .n { color:var(--ko); } .hint { color:var(--mut); font-size:.85rem; }
label { display:block; font-weight:600; margin:14px 0 4px; font-size:.92rem; }
input, select { width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--brd); background:rgba(0,0,0,.25); color:var(--txt); font:inherit; }
input:focus, select:focus { outline:2px solid var(--acc); outline-offset:1px; }
select option { color:#111; }
.row { display:grid; grid-template-columns:1fr 1fr; gap:12px; } @media (max-width:520px){ .row { grid-template-columns:1fr; } }
button, .btn { display:inline-block; margin-top:22px; width:100%; padding:12px; border:0; border-radius:12px; font:inherit; font-weight:700; cursor:pointer;
  background:linear-gradient(135deg,#3b82f6,#06b6d4); color:#fff; text-align:center; text-decoration:none; }
.box { border-radius:12px; padding:12px 14px; margin:0 0 16px; font-size:.92rem; }
.err { background:rgba(251,113,133,.15); border:1px solid rgba(251,113,133,.45); }
.warn { background:rgba(250,204,21,.12); border:1px solid rgba(250,204,21,.4); }
.okb { background:rgba(74,222,128,.13); border:1px solid rgba(74,222,128,.4); }
code { background:rgba(0,0,0,.3); padding:1px 6px; border-radius:6px; font-size:.88em; word-break:break-all; }
a { color:var(--acc); } .foot { margin-top:18px; text-align:center; }
</style>
</head>
<body>
<main class="card">
<h1>phpFileManager <?php echo PFM_VERSION; ?></h1>
<p class="sub"><?php echo h(t('sub')); ?></p>

<?php if ($done): ?>
  <div class="box okb"><?php echo t('ok', '<code>' . h($done['name']) . '</code>', h($done['origin'])); ?></div>
  <?php if ($done['deleted']): ?>
    <p><?php echo h(t('ok_del')); ?></p>
  <?php else: ?>
    <div class="box err"><?php echo t('ok_nodel', '<code>' . h(basename(__FILE__)) . '</code>'); ?></div>
  <?php endif; ?>
  <a class="btn" href="<?php echo h(rawurlencode($done['name'])); ?>"><?php echo h(t('open')); ?></a>
  <p class="hint"><?php echo h(t('tip')); ?></p>

<?php elseif ($expired): ?>
  <div class="box err"><?php echo h(t('expired')); ?></div>

<?php else: ?>
  <ul class="chk">
  <?php foreach ($checks as $c): ?>
    <li><span class="<?php echo $c[1] ? 'y' : 'n'; ?>"><?php echo $c[1] ? '&#10003;' : '&#10007;'; ?></span> <?php echo h($c[0]); ?><?php if (!$c[1]) echo ' <span class="hint">(' . h($c[2]) . ')</span>'; ?></li>
  <?php endforeach; ?>
    <li class="hint"><?php echo h(t('left', max(0, (int)ceil((PFM_TTL - $age) / 60)))); ?></li>
  </ul>
  <?php if (!$https): ?><div class="box warn"><?php echo t('http'); ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="box err"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

  <?php if ($all_ok): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="t" value="<?php echo h($token); ?>">
    <label for="name"><?php echo h(t('f_name')); ?></label>
    <input id="name" name="name" value="<?php echo h($v['name']); ?>" required>
    <div class="hint"><?php echo t('f_name_h', '<code>' . h($dir) . '</code>'); ?></div>

    <div class="row">
      <div><label for="pw"><?php echo h(t('f_pw')); ?></label><input id="pw" name="pw" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
      <div><label for="pw2"><?php echo h(t('f_pw2')); ?></label><input id="pw2" name="pw2" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
    </div>
    <div class="hint"><?php echo h(t('f_pw_h')); ?></div>

    <label for="root"><?php echo h(t('f_root')); ?></label>
    <input id="root" name="root" value="<?php echo h($v['root']); ?>">
    <div class="hint"><?php echo t('f_root_h', '<code>' . h(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '</code>'); ?></div>

    <div class="row">
      <div><label for="lang"><?php echo h(t('f_lang')); ?></label>
        <select id="lang" name="lang"><?php foreach ($languages as $k => $l) echo '<option value="' . h($k) . '"' . ($k === $v['lang'] ? ' selected' : '') . '>' . h($l) . '</option>'; ?></select></div>
      <div><label for="tz"><?php echo h(t('f_tz')); ?></label>
        <select id="tz" name="tz"><?php foreach (DateTimeZone::listIdentifiers() as $tz) echo '<option' . ($tz === $v['tz'] ? ' selected' : '') . '>' . h($tz) . '</option>'; ?></select></div>
    </div>

    <button type="submit"><?php echo h(t('f_btn')); ?></button>
  </form>
  <?php else: ?>
    <div class="box err"><?php echo h(t('fix')); ?></div>
  <?php endif; ?>
<?php endif; ?>
<p class="hint foot"><a href="https://github.com/b84an/phpfm" rel="noopener">github.com/b84an/phpfm</a></p>
</main>
</body>
</html>
