# phpFileManager 1.8.1 → 1.9.0 — Security review and new interface

Fork maintained by Ivan Bertotto — <https://github.com/b84an/phpfm>
Upstream project by Fabricio Seger Kolling — <https://github.com/dulldusk/phpfm>

Review date: November 2026. File analysed: `index.php` 1.8.1 (1.0 MB, about 8,700 lines of PHP/JS/CSS plus 26 base64-embedded assets). The untouched upstream file is in the git history of this repository (the commits before the fork).

The new `index.php` is a single self-contained file like the original: copy it to the server and it works (PHP ≥ 7.3; tested with PHP 8.4 and 8.5).

> **Context.** phpFileManager is by nature an *authorised* web shell: an authenticated user can read and write any file reachable by the web server and run commands (Shell, Execute). The review therefore focused on everything that lets an **unauthenticated user** or a **third-party site** reach those functions, and on whatever makes the administrator's session stealable.

---

## 1. Vulnerabilities found and fixed

| # | Severity | Problem in 1.8.1 | Fix in 1.9.0 |
|---|---|---|---|
| 1 | **Critical** | **The anti-webshell upload filter did not work.** `save_upload()` read `$cfg->fm_upload_ext_filter`, a property that does not exist (the value lives in `$cfg->data[...]`). The list was always empty and any `.php` file was accepted: the CVE-2026-72592 fix was ineffective in practice. | The value is read from the configuration correctly. Verified by test: `shell.php` was accepted before, it is now refused ("Invalid extension"). |
| 2 | **Critical** | **No CSRF protection.** Every action (delete via GET, rename, file execution, JSON-RPC shell, upload, password/config change, `du`) could be triggered with just the cookie. A link or an external page visited by the logged-in administrator was enough to run commands on the server. | Per-session HMAC token (`fm_csrf`) required on every state-changing request (hidden form fields, JS URLs, AJAX header). Request without token → **403**. `SameSite=Strict` cookie as a second barrier. |
| 3 | **Critical** | **Weak authentication.** Password stored as unsalted MD5; the session cookie contained *the MD5 hash itself* (stolen cookie = permanent access, pass-the-hash); the `fm_pass_config` cookie contained the **plain-text password**, printed back into the configuration form; comparison with `==` (type juggling on `0e…` hashes); no `HttpOnly`/`Secure`/`SameSite` flags; no limit on attempts. | **bcrypt** hash (`password_hash`), session cookie = HMAC-SHA256 with a random per-install secret, `hash_equals` comparisons, `HttpOnly` + `SameSite=Strict` + `Secure` (on HTTPS) cookies, minimum password length 8, the password is never sent back to the browser, **lockout** after 5 failed attempts (5 minutes) plus a delay on failures. Existing MD5 installs are **migrated automatically** at first login. |
| 4 | **High** | **Command injection through file/folder names** in `du -sb $path` (action 14, via GET), in file execution (`cd $dir && $file`), in `icacls "$file"` (Windows) and in the shell `cd`. | `escapeshellarg()` on every argument. |
| 5 | **High** | **Multiple reflected/stored XSS**: `alert('$file')` with unescaped file name (download/execute), file names inserted in JS attributes with `addslashes` (insufficient), `rawurldecode` instead of `rawurlencode` in sort links and in the viewer, `ace_mode` from cookie/GET injected into the editor JS, `$_SERVER` and `ini_get_all()` printed unescaped in "Server info", port scan output, upload results, error messages containing command/output. | `html_encode()` / `js_str()` (JSON with hex escaping) on every user-controlled value, whitelist for the editor mode, `X-Content-Type-Options: nosniff`. Files viewed inline (HTML/SVG…) are served with `Content-Security-Policy: sandbox`: they can no longer run scripts in the file manager's origin. |
| 6 | **High** | **`fm_root` was not a real boundary.** The tree (`tree_fs`) only logged "path is not inside base"; `filename`, `cmd_arg`, `old_name/new_name`, `dirname`, `dir_dest`, `fm_current_dir` accepted `../`. | Names validated (no separators, no `.`/`..`/NUL), current directory normalised and confined to the root, copy/move/link destination confined, tree confined. |
| 7 | **High** | **Register globals**: any GET/POST/cookie parameter became a global variable (including `fm_current_root`, `cfg`, `doc_root`, arrays…). | Blocklist for configuration/environment/authentication variables, scalar values only, valid names only; request parameters always initialised. |
| 8 | Medium | The "About" window loaded, at first access, an iframe from an **external domain** (`dulldusk.com`), passing the installed version: information leak and third-party dependency inside the admin console. | Local About panel (action 13); no external calls. |
| 9 | Medium | The shell JSON-RPC error handler returned the file path and the **source line**. | Returns the message only. |
| 10 | Medium | No security headers. | `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `Content-Security-Policy: frame-ancestors 'self'`. |
| 11 | Medium | `Content-Disposition` built with the raw file name (quotes, CR/LF). | Sanitised name + `filename*=UTF-8''…`. |
| 12 | Medium | **jQuery 1.11.1 (2014)** embedded, with known CVEs (CVE-2015-9251, CVE-2019-11358, CVE-2020-11022/11023). | Replaced with **jQuery 3.7.1** (SHA-256 verified). jsTree 3.3.7, jquery.terminal 2.2.0 and Ace 1.4.2 are compatible and were kept. |
| 13 | Medium | **Non-atomic** configuration save: `fopen("w")` on the script itself; an error halfway through left a truncated `index.php` (unusable manager). | Write to a temporary file + `rename`, with fallback to the old method. |

### Non-security bugs fixed (PHP 8.x)

- `explode('', …)` in the `apache_request_headers()` polyfill → fatal `ValueError` on Nginx and other servers.
- `falze` (typo) in the UDP port scan → fatal error; `if ($code = 101)` assignment instead of comparison.
- `$fm_ext_filter` (non-existent variable) in the configuration form → empty filter field.
- BOM constants redefined at every call, deprecated `FILE_BINARY`, dozens of `Undefined variable` / `strlen(null)`: a test copy with `E_ALL` now emits no warnings.
- `et()` rebuilt the whole translation table (~2,300 entries) at every call, about 100 times per page → static cache.

---

## 2. New interface

- **Application layout** instead of the `<frameset>` look: top bar with logo, version, HTTP/HTTPS indicator, theme and logout; **resizable sidebar** (drag the divider, width remembered) that can be collapsed.
- **Light/dark theme** with CSS variables, automatic detection of the OS preference and a manual switch; the editor (Ace) and the terminal follow the theme.
- **SVG icons** (Lucide, ISC license) through CSS masks instead of the PNG sprite: sharp on HiDPI screens, coloured by file type.
- **File list**: modern table with sticky header, sort indicator, hover, file-type badge, per-row icon actions (view, edit, download, extract, execute, rename, delete), **instant filter** by name.
- **Contextual selection bar**: appears only when something is selected (count, copy, move, compress, ZIP, symlink, hardlink, permissions, delete) — no more double row of ten always-visible buttons.
- Clickable **breadcrumb** with an "up" button and display of symlink targets.
- **Modal windows** with blurred backdrop, animation, close with `Esc` or click outside.
- **Upload** with drag & drop area, file list with sizes and a **progress bar** (XHR).
- **Login** as a centred card with show/hide password and clear messages (first access, password too short, lockout with countdown).
- **Configuration** organised in tabs (full timezone list from `DateTimeZone`, date format preview, editable upload filter, separate password change).
- Permissions, port scan, about, editor and shell redesigned with the same style system; responsive layout (secondary columns disappear below 760 px).
- New strings translated into **English and Italian** (other languages fall back to English).

Before/after screenshots are in [`screenshots/`](screenshots/).

---

## 3. How it was verified

- Local PHP 8.5 server with two copies: a "normal" one and one with `error_reporting(E_ALL)`.
- Automated `curl` suite (run on both): security headers, first access and short password, login/`HttpOnly` cookie, missing/wrong CSRF token → 403 on delete/upload/shell, path traversal on download/rename/directory, upload of `shell.php` blocked and of `.txt` accepted, rename/delete/mkdir with token, every window (config, server info, permissions, shell, upload, port scan, about, editor, viewer with CSP sandbox, `du`, favicon, execute), shell JSON-RPC with and without token, file and configuration save, **lockout** after 5 failures, logout, **migration from legacy MD5 password**.
- Headless Chrome screenshots (light and dark theme, every window).
- Browser test on a production shared hosting (PHP 8.4): no JavaScript errors, server info / shell / port scan / create directory working, shell commands executed.
- `php -l` on the final file.

---

## 4. Residual risks and advice

1. **It is still an extremely powerful tool**: publish it only over **HTTPS**, possibly behind a VPN or an IP allowlist (e.g. `.htaccess`), with an unpredictable file name, and set `fm_root` in the configuration to limit the browsable tree.
2. The file must be **writable by the web server** to save password/configuration (as in the original). If it is not, the first access cannot save the password: check the permissions.
3. **Symlinks** pointing outside `fm_root` are still followed (original behaviour, kept for compatibility).
4. The lockout is per IP address (`REMOTE_ADDR`): behind a shared proxy it can lock out several users for 5 minutes; behind a reverse proxy, adapt `fm_login_throttle_file()` if you want the real client IP.
5. "Server info" shows `phpinfo()` and `$_SERVER` (to the authenticated administrator only).
6. Removing the password from the configuration is no longer possible (empty = keep the current one): in 1.8.1 "empty password" meant open access for anyone.
7. Windows could not be tested (the `mklink` / `icacls` / drive-letter branches were reviewed at code level only).
8. The session cookie expires when the browser is closed; there is no server-side idle timeout yet.

---

## 5. Compatibility

- The configuration line format (line 2 of the file) is unchanged; the keys `fm_pass_hash` and `fm_secret` were added. A 1.8.x configuration is read and migrated without intervention.
- Every original feature is kept (tree, list, multiple upload, Ace editor, shell, port scan, chmod, compression/extraction, symlink/hardlink, ZIP download, UID/GID resolution, 20 languages).
