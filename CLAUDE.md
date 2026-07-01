# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Chromaesthetics IT Asset Management System — a native PHP + MySQL multi-page app that tracks the lifecycle of hardware assets and software licenses, employee assignments, and an audit trail of asset movements. No framework, no Composer, no npm, no build step. Front-end libraries (Bootstrap 5.3.3, Bootstrap Icons, Chart.js, Select2, jQuery) are all loaded from CDNs.

## Running it

Requires PHP 7.4+ and MySQL/MariaDB (designed for XAMPP). There is no test suite, linter, or build.

```bash
php -S localhost:8123 -t .        # dev server (matches .claude/launch.json)
```

Database setup — **two files must both be applied**, in order:
1. Import `it_inventory_assets.sql` (creates `assets`, `employees`, `transmittals` + seed data).
2. Run `adding sql.sql` — it `ALTER`s the enums the dump is missing: `assets.status` needs `'Repairing'` and `transmittals.transaction_type` needs `'Repair'`. Without this, transmittal repairs and asset repair status fail.

    DB credentials live in `includes/config.php` (defaults: host `localhost`, db `it_inventory_assets`, user `root`, empty password).d
## Architecture

**Page pattern.** Every user-facing feature is a single self-contained `.php` file at the repo root. Each file has the same shape:
1. `require_once 'includes/config.php';` → gives you `$pdo` (PDO, exceptions on, assoc fetch, real prepared statements).
2. All request handling (add/edit/delete/search/sort) runs at the top of the file, branching on `$_SERVER['REQUEST_METHOD']` and a hidden action field like `$_POST['add_asset']`. Results go into a `$message` string of pre-rendered Bootstrap alert HTML.
3. Set `$page_title`, `$active_page`, optional `$extra_head` / `$extra_scripts`, then render via the shared shell.

**Shared shell** (`includes/`): `head.php` (doctype, CDN CSS, theme bootstrap, `.bg-aurora`), `sidebar.php` (nav + topbar; `$nav_items` array is the source of truth for navigation and highlights via `$active_page`), `footer.php` (CDN JS + `app.js`). A page renders with `include 'includes/head.php'; include 'includes/sidebar.php';` ... markup ... `include 'includes/footer.php';`. `$extra_scripts` runs after Bootstrap but before `app.js` — put per-page jQuery/Select2/Chart.js init there. head/footer cache-bust `theme.css`/`app.js` with `filemtime`.

**Data model** (3 tables): `assets` (FK `current_user_id` → `employees`, `status` enum), `employees`, `transmittals` (the movement/audit log; FK `asset_id` → `assets`, plus `from_id`/`to_id` employee refs and base64 `signature_data`).

**Core business rule — transmittals own assignment state.** An asset's `current_user_id` and `status` must only change through `transmittal.php`, which atomically updates the `assets` row **and** inserts a `transmittals` audit row in the same request. The three transaction types: **OUT/Issue** (set `In Use` + `current_user_id`), **IN/Return** (set `Available`, null the user), **Repair** (set `Repairing`, null the user). Consequently `inventory.php` refuses to edit the status of an asset that still has a `current_user_id` — it tells the user to go through a transmittal instead. Preserve this invariant in any change touching asset state.

**Front-end** (`assets/`): `theme.css` is a glassmorphism theme driven by `data-theme="light|dark"` on `<html>`, toggled and persisted to `localStorage` (the inline script in `head.php` applies it pre-paint to avoid flash). `app.js` is dependency-free and defensive (every init no-ops if its target element is absent), handling the theme toggle, responsive sidebar drawer, scroll-reveal (`.reveal`), animated counters (`.stat-card .h5-number`, `.mini-card .h5`), and button ripples. It honors `prefers-reduced-motion`.

## Conventions

- **Input:** `filter_input(INPUT_POST/GET, ..., FILTER_SANITIZE_SPECIAL_CHARS)` (or `FILTER_SANITIZE_NUMBER_INT` for ids), `trim()`, then validate — including whitelisting enum-like values server-side (e.g. `in_array($status, $allowed_statuses, true)`).
- **SQL:** always `$pdo->prepare()` + `execute([...])` with `?` placeholders. Catch `PDOException` and check `$e->getCode() == 23000` to turn unique-constraint violations into friendly messages.
- **Output:** `htmlspecialchars()` any dynamic value echoed into markup.
- **No auth layer exists.** There is no login/session/role check; every page is publicly reachable. Don't assume a current-user or permission context.

## Gotchas / drift to be aware of

- **Duplicate config:** `config.php` (root) and `includes/config.php` are identical, but pages include the `includes/` one. Edit `includes/config.php`.
- **Missing software schema:** `software_inventory.php` / `software_assignment.php` query `software_items` and `software_assignments` tables that are **not** in `it_inventory_assets.sql`. Those tables must be created separately for the software features to work.
- **Legacy/superseded pages** not linked from the sidebar: `employee.php`, `employee_detail.php`, `software.php`, `software_allocation.php` are older versions of `employees.php` / `employee_details.php` / `software_inventory.php` / `software_assignment.php`. `employee_details.php` is the live per-employee profile view (linked from `employees.php`, not the sidebar). Confirm which page is actually wired in before editing.
- **`print_slip.php`** is described in `readme.md` but does not exist in the repo.
- **Branches:** many version branches exist (`v2`…`v11.0.0`, `V8`…`V9.1.1`); `main` is the PR base. Active work is on the highest version branch.
