# Changelog

All versions after 1.8.1 belong to the hardened fork maintained by Ivan Bertotto
(<https://github.com/b84an/phpfm>). Earlier versions are by Fabricio Seger Kolling
(<https://github.com/dulldusk/phpfm>).

## 1.9.0 — November 2026 (fork)

### Security
- bcrypt password hashing; automatic migration of 1.8.x MD5 passwords at first login.
- Session cookie is an HMAC token with a per-install secret (`HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS); the password hash and the plain-text password are no longer stored in cookies.
- CSRF token on every state-changing request (403 without it).
- Login throttling: 5 failed attempts lock the login for 5 minutes; minimum password length 8.
- Upload extension filter fixed (it was always empty, so the CVE-2026-72592 fix had no effect).
- Root folder enforced as a real boundary; file names validated; `../` rejected.
- `escapeshellarg()` on every shell argument (`du`, execute, `cd`, `icacls`).
- Systematic output escaping (`html_encode`, `js_str`); inline-viewed files served with `Content-Security-Policy: sandbox`.
- Register-globals emulation restricted (scalars only, blocklist of internal variables).
- Local About panel instead of an iframe from an external domain.
- Security headers; sanitized `Content-Disposition`; JSON-RPC errors without source lines.
- jQuery 1.11.1 → 3.7.1.
- Atomic configuration save.

### Interface
- New application layout, resizable/collapsible sidebar, breadcrumb.
- Light and dark theme (automatic or manual), also for the editor and the terminal.
- Lucide SVG icons, sticky-header file list with instant filter and per-row actions, contextual selection toolbar.
- Drag & drop upload with progress bar, modal windows, redesigned login, tabbed configuration.
- Responsive layout; new strings in English and Italian.

### Fixes
- PHP 8 fatal errors (`explode('')`, `falze`, assignment in condition) and dozens of notices.
- Translation table cached instead of being rebuilt at every call.

### New
- `installer/phpfm-installer.php`: guided single-file installer (downloads `index.php`, verifies its SHA-256, writes the configuration, deletes itself, expires one hour after upload).

## 1.8.1 and earlier

See the upstream project: <https://github.com/dulldusk/phpfm>.
