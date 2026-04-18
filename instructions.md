# Project Overview

This is **FitnessPro System** — a WordPress plugin providing an RTL-compatible, WooCommerce-integrated fitness plan management system. It enables trainers and gym managers to create, assign, and sell fitness plans to clients, with full Persian/Arabic RTL UI support and WooCommerce product linking for payment-gated plan access.

---

# Technical Rules

- **Language:** PHP (OOP, PSR-4 namespacing) + Vanilla JavaScript (OOP, no frameworks)
- **JavaScript:** Vanilla JS only — **no React, no Vue, no jQuery UI widgets**
- **AJAX:** Native WordPress AJAX (`wp_ajax_` / `wp_ajax_nopriv_` hooks) — no REST API unless explicitly decided
- **Styling:** Plain CSS with RTL-first layout — no Tailwind, no Bootstrap
- **WooCommerce:** Integrated for plan purchase flow; treat WC as an optional dependency (check active before using)
- **Database:** WordPress Custom Tables via `$wpdb` + post meta / user meta where appropriate
- **No build tools** unless explicitly introduced (no webpack, vite, etc.)
- **PHP minimum:** 7.4+; WordPress minimum: 6.0+
- **All user-facing strings** must be wrapped in `__()` / `esc_html__()` for i18n

---

# Database Schema

> Living record — update this section whenever a table or meta key is added/modified.

## Custom Tables

| Table Name | Columns | Indexes | Status |
|---|---|---|---|
| `wp_fitness_user_plans` | id, user_id, coach_id, order_id, type ENUM(workout/meal), status ENUM(active/expired/pending), expiry_date, created_at | PK(id), idx_user_id, idx_coach_id, idx_status, idx_order_id | ✅ Created |
| `wp_fitness_active_content` | id, user_plan_id, content_json LONGTEXT, updated_at | PK(id), UNIQUE(user_plan_id) | ✅ Created |
| `wp_fitness_tickets` | id, sender_id, receiver_id, message LONGTEXT, attachment_url, status ENUM(open/closed/pending), created_at | PK(id), idx_sender_id, idx_receiver_id, idx_status | ✅ Created |
| `wp_fitness_daily_progress` | id, user_id, date, completed_json LONGTEXT | PK(id), UNIQUE(user_id+date), idx_user_id | ✅ Created |

## WP Options

| Option Key | Value | Description |
|---|---|---|
| `fitnesspro_db_version` | `1.0.0` | Tracks installed DB schema version |

## Post Meta Keys

| Meta Key | Post Type | Description | Status |
|---|---|---|---|
| `_tpl_data` | `workout_template` | JSON-encoded 7-day plan: `{ saturday: { exercises: [{name,sets,reps,note,media_id,media_url}] }, ... }` | ✅ Active |
| `_tpl_data` | `meal_template` | JSON-encoded 5-meal plan: `{ breakfast: {items,calories,protein,carbs,fat,note}, snack1, lunch, snack2, dinner }` | ✅ Active |

## User Meta Keys

| Meta Key | Description | Status |
|---|---|---|
| *(none yet)* | | |

---

# Task Log & Changelog

> Chronological record of every completed task. Update after each prompt.

## [2026-04-18] — Prompt 0: Project Initialization

- **Action:** Created `instructions.md` (this file) as the Single Source of Truth / memory tracker for the project.
- **Files Created:**
  - `instructions.md` — Project overview, technical rules, DB schema scaffold, task log, and next steps.
- **Notes:** Project directory was empty; this is the foundation commit.

## [2026-04-18] — Prompt 1: Plugin Foundation & Advanced DB Schema

- **Action:** Scaffolded the full OOP plugin structure with WooCommerce check, 4 custom DB tables, and a restricted Coach role.
- **Files Created:**
  - `hanafit-app.php` — Plugin header, constants, require chain, activation/deactivation hooks, bootstrap call.
  - `includes/class-fitnesspro-loader.php` — Hook registry; collects `add_action` / `add_filter` calls and runs them all in `::run()`.
  - `includes/class-fitnesspro-database.php` — `::create_tables()` via `dbDelta`; `::drop_tables()` reserved for uninstall.
  - `includes/class-fitnesspro-roles.php` — Adds/removes `fitness_coach` role; `restrict_dashboard_access()` redirects coaches to their own page.
  - `includes/class-fitnesspro-activator.php` — Calls DB creation + role registration on plugin activation.
  - `includes/class-fitnesspro-deactivator.php` — Flushes rewrite rules only (no data loss on deactivation).
  - `includes/class-fitnesspro-core.php` — Orchestrator: WooCommerce notice, admin/public/role hook registration, textdomain load.
  - `admin/class-fitnesspro-admin.php` — Admin menu (فیتنس‌پرو + داشبورد مربی), conditional asset enqueuing with `wp_localize_script`.
  - `public/class-fitnesspro-public.php` — Frontend asset enqueuing with nonce + Persian strings.
  - `uninstall.php` — Safe full cleanup (tables + role) only on WP uninstall trigger.
  - `assets/css/admin-rtl.css` — RTL admin styles, status badges, button margin flips.
  - `assets/css/public-rtl.css` — RTL public styles, spinner, notice components.
  - `assets/js/admin.js` — Vanilla OOP `FitnessProAdmin` class; generic `ajax()` Promise helper.
  - `assets/js/public.js` — Vanilla OOP `FitnessProPublic` class; `ajax()`, `toggleSpinner()`, `showNotice()` helpers.
  - `languages/fitnesspro-fa_IR.po` — Seed .po file with all Persian strings from Phase 1.
- **Key Decisions:**
  - Tables are NOT dropped on plugin deactivation — only on full uninstall (prevents accidental data loss).
  - `content_json` and `completed_json` stored as `LONGTEXT` to support arbitrary plan structures without schema migrations.
  - Coach role uses `edit_posts` cap (standard WP) rather than a custom cap — avoids breaking the admin menu visibility system.
  - WooCommerce treated as soft dependency: admin notice fires if missing, but plugin stays functional.

## [2026-04-18] — Prompt 2: CPTs & Advanced Repeater Meta Boxes

- **Action:** Registered `workout_template` and `meal_template` CPTs; built 7-day workout repeater and 5-meal nutrition planner meta boxes with wp.media integration.
- **Files Created:**
  - `includes/class-fitnesspro-cpts.php` — Registers `workout_template` and `meal_template` CPTs (private, show_in_menu under fitnesspro-dashboard, map_meta_cap).
  - `includes/class-fitnesspro-meta-boxes.php` — `FitnessPro_Meta_Boxes`: renders workout (7-day tab UI + exercise repeater) and meal (5-slot cards with macros) meta boxes; sanitizes + saves all data as JSON into `_tpl_data`.
  - `assets/css/meta-boxes.css` — Full RTL meta-box stylesheet: tab pills, exercise row cards with drag handle, meal cards color-coded by slot, macro input row with units.
  - `assets/js/meta-boxes.js` — `WorkoutPlanner` class (tab switch, add/delete/reindex rows, wp.media frame); `MealPlanner` class (live macro totals bar).
- **Files Modified:**
  - `hanafit-app.php` — Added `require_once` for CPTs and Meta Boxes classes.
  - `includes/class-fitnesspro-core.php` — Added `define_content_hooks()` method wiring CPT `init` and `add_meta_boxes` / `save_post` hooks via Loader.
  - `admin/class-fitnesspro-admin.php` — Added `enqueue_metabox_assets()`: loads `wp_enqueue_media()`, `meta-boxes.css`, `meta-boxes.js`, and `fitnesspro_mb` JS config only on CPT edit screens.
- **Key Decisions:**
  - Both CPTs share the same meta key `_tpl_data` (differentiated by post_type at save time) — one query per template fetch.
  - Unnamed exercises are silently dropped during sanitization to keep JSON clean.
  - wp.media frame is created once and reused (expensive to instantiate); `_mediaTarget` pointer updated before each `open()` call.
  - Meal macro totals bar is injected above the planner by JS, not in PHP — keeps server markup clean and totals always in sync with live input.
  - Exercise row template uses `<script type="text/html">` with `{{DAY}}` / `{{INDEX}}` placeholders replaced by JS — avoids encoding issues and keeps PHP rendering logic DRY.

---

# Next Steps

1. **Plan Assignment** — Admin AJAX form: assign a `workout_template` or `meal_template` to a user → INSERT into `wp_fitness_user_plans` + copy template JSON into `wp_fitness_active_content`.
2. **WooCommerce Integration** — Hook `woocommerce_order_status_completed` to auto-activate plans when the linked `order_id` completes payment.
3. **Frontend Dashboard** — Shortcode `[fitnesspro_dashboard]` showing the logged-in user's active plans from `wp_fitness_active_content` with RTL layout.
4. **Daily Progress Tracker** — AJAX endpoint for users to mark exercises complete; writes to `wp_fitness_daily_progress`.
5. **Ticket System** — AJAX send/receive messages into `wp_fitness_tickets`; separate coach and client views.
