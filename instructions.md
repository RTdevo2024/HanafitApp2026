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
| `fitnesspro_product_map` | `{ workout_product_id, meal_product_id }` | WooCommerce product ID mapping per plan type |
| `fitnesspro_field_diseases` | JSON array of strings | Dynamic list — بیماری‌ها |
| `fitnesspro_field_goals` | JSON array of strings | Dynamic list — اهداف |
| `fitnesspro_field_activity_levels` | JSON array of strings | Dynamic list — سطح فعالیت |
| `fitnesspro_field_eating_disorders` | JSON array of strings | Dynamic list — اختلالات تغذیه‌ای |

## Post Meta Keys

| Meta Key | Post Type | Description | Status |
|---|---|---|---|
| `_tpl_data` | `workout_template` | JSON-encoded 7-day plan: `{ saturday: { exercises: [{name,sets,reps,note,media_id,media_url}] }, ... }` | ✅ Active |
| `_tpl_data` | `meal_template` | JSON-encoded 5-meal plan: `{ breakfast: {items,calories,protein,carbs,fat,note}, snack1, lunch, snack2, dinner }` | ✅ Active |

## User Meta Keys

| Meta Key | Description | Status |
|---|---|---|
| `fp_phone` | Mobile phone number captured during checkout registration | ✅ Active |

## Transients

| Key Pattern | TTL | Description |
|---|---|---|
| `fp_checkout_profile_{user_id}` | 7200s (2h) | Sanitized profile data collected during checkout flow |

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

## [2026-04-18] — Prompt 3: Admin Dashboard & WooCommerce Product Mapping

- **Action:** Built the central admin dashboard (stats cards + quick links), WooCommerce product-to-plan mapping settings, four dynamic field lists with AJAX, and the FitnessPro orders WP_List_Table.
- **Files Created:**
  - `admin/class-fitnesspro-settings.php` — `FitnessPro_Settings`: renders settings page (2 tabs), handles `admin_post_fp_save_product_map`, and AJAX handlers `fp_add_field_item` / `fp_remove_field_item`. Stores data in 5 `wp_options` keys.
  - `admin/class-fitnesspro-orders-table.php` — `FitnessPro_Orders_Table` (extends WP_List_Table): queries `wp_fitness_user_plans` + `wp_users` JOIN; status filter tabs (`get_views`); sortable columns; RTL column renderers with status/type badges.
  - `assets/js/admin-settings.js` — `DynamicFieldManager` class: delegated click + Enter-key handling; AJAX add/remove; tag re-rendering; `_shake()` for empty-input feedback; inline error display.
  - `assets/css/admin-settings.css` — Stats grid (4-col responsive), quick links, settings nav tabs, form table RTL, field group cards grid (2-col), tag pills, add-item row, orders table overrides, type/status badges.
- **Files Modified:**
  - `hanafit-app.php` — Added `require_once` for settings and orders-table classes.
  - `admin/class-fitnesspro-admin.php` — Full rewrite: added `render_orders_page()`, `render_settings_page()`, `render_dashboard()` (with live DB stats), `enqueue_settings_assets()`; extended menu to داشبورد / سفارشات / تنظیمات / داشبورد مربی.
  - `includes/class-fitnesspro-core.php` — Added settings hooks: `admin_post_fp_save_product_map`, `wp_ajax_fp_add_field_item`, `wp_ajax_fp_remove_field_item`; wired `enqueue_settings_assets`.
- **Key Decisions:**
  - Dynamic field AJAX reuses `fitnesspro_admin_nonce` — avoids a second nonce creation; `check_ajax_referer` enforces the correct action string on backend.
  - `FitnessPro_Settings` instantiated fresh in `render_settings_page()` — keeps it stateless and avoids storing a reference on the admin object.
  - `get_views()` in the orders table runs a single `GROUP BY` query — one trip to the DB for all status counts.
  - Product map tab shows WC-inactive notice rather than broken selects when WooCommerce is not loaded.
  - Orders table `$orderby` and `$status_filter` are whitelist-validated before interpolation into SQL — prevents injection via query string.

---

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

## [2026-04-18] — Prompt 4 - Part 1: Checkout Flow UI Shell & CSS

- **Action:** Built the PHP shortcode shell class and full CSS for the 6-step mobile-app-style checkout flow. No JS or step HTML content yet.
- **Files Created:**
  - `public/class-fitnesspro-checkout-ui.php` — `FitnessPro_Checkout_UI`: shortcode `[fitness_checkout_flow]`; `maybe_enqueue_assets()` with `has_shortcode()` guard; `render()` outputs `#fitness-app-container` with all data-* attributes (nonce, ajax-url, logged-in, start-step, total-steps, plan-map, goals, activities, diseases, eating-dis); PHP fallback defaults for empty goals/activities lists.
  - `assets/css/checkout-flow.css` — Full dark mode glassmorphism CSS: CSS custom properties (--fco-neon `#00FF39`, --fco-bg `#121212`), step active/exiting transitions with `@keyframes fco-fade-up / fco-fade-out`, glassmorphism cards (backdrop-filter), form inputs, gender toggle, chip multi-select, activity level cards, plan selection cards, BMI panel + gauge, auth tabs (login/register), summary review rows, primary neon + ghost back navigation buttons, spinner, notice banners, mobile-first responsive breakpoints, desktop centred-card layout at 768px+.
- **Files Modified:**
  - `hanafit-app.php` — Added `require_once` for `public/class-fitnesspro-checkout-ui.php`.
  - `includes/class-fitnesspro-core.php` — `define_public_hooks()` now instantiates `FitnessPro_Checkout_UI` and wires `init → register_shortcode` and `wp_enqueue_scripts → maybe_enqueue_assets`.
- **Key Decisions:**
  - JS (`fitnesspro-checkout-flow` script) intentionally left commented-out in `do_enqueue()` — added in Part 2 alongside the HTML step content.
  - Step visibility managed with CSS classes only (`.fco-step`, `.is-active`, `.is-exiting`) — no `display:none` toggling from JS avoids FOUC.
  - Desktop breakpoint wraps `.fco-inner` in a glassmorphism card (max-width 480px, border, border-radius) — mobile gets full-screen treatment.
  - All step HTML and the `FitnessProCheckoutFlow` JS controller deferred to Part 2.

## [2026-04-19] — Prompt 4 - Part 2: Full Checkout Flow (JS + AJAX + Step HTML)

- **Action:** Completed the 6-step AJAX purchase flow — all step HTML server-rendered, Vanilla JS OOP controller, 3 AJAX handlers, BMI SVG arc gauge, WooCommerce cart redirect.
- **Files Modified:**
  - `public/class-fitnesspro-checkout-ui.php` — Complete rewrite: added `ajax_auth()` (dispatches `do_login()` / `do_register()`), `ajax_save_profile()` (transient TTL 2h), `ajax_add_to_cart()` (WC cart empty + add + redirect URL); `render()` now outputs full server-rendered HTML for all 6 steps; `do_enqueue()` now enqueues `checkout-flow.js`.
  - `assets/js/checkout-flow.js` — New file: `FitnessProCheckoutFlow` OOP class; event delegation for next/back/pay/plan-card/chip/activity-card/gender/auth-tab/password-toggle; `_validate()` per-step; `_collect()` per-step; `_doAuth()` / `_doSaveProfile()` / `_doAddToCart()` async fetch helpers; `_renderBMI()` with SVG arc animation (arc length π×90≈282.74, range BMI 15–40); `_renderSummary()` fills summary card; `_showNotice()` / `_setBtnLoading()` / `_showStep()` / `_updateProgress()` UI helpers.
  - `assets/css/checkout-flow.css` — Appended: `.fco-bmi-svg`, `.fco-bmi-arc-fill` transition, `.fco-bmi-stats` grid, `.fco-bmi-stat` / `__key` / `__val`, `.fco-section-label`, `.fco-plan-card--disabled`, `.fco-plan-card__unavailable`, `.fco-btn-pay`.
  - `includes/class-fitnesspro-core.php` — Added 4 AJAX hooks in `define_public_hooks()`: `wp_ajax_nopriv_fp_cof_auth`, `wp_ajax_fp_cof_auth`, `wp_ajax_fp_cof_save_profile`, `wp_ajax_fp_cof_add_to_cart`.
- **User Meta Keys Added:**
  - `fp_phone` — stored on register; `set_transient('fp_checkout_profile_{user_id}', $clean, 7200)` — profile data TTL 2h.
- **Key Decisions:**
  - Step HTML is server-rendered (PHP), not JS-injected — avoids FOUC and keeps content available for screen readers.
  - Auth step (step 1) rendered only for guests; `data-start-step` / `data-total-steps` drive JS progress math so logged-in users start at step 2 with correct 5-step progress.
  - Nonce refreshed after AJAX auth: server calls `wp_set_current_user()` before `wp_create_nonce()`, JS updates `this._nonce` so subsequent calls use the fresh session nonce.
  - `WC()->cart->empty_cart()` before `add_to_cart()` — ensures only the fitness plan product is checked out.
  - BMI arc: semicircle path center (110,120) radius 90; arc length = π×90 ≈ 282.74; progress = clamp((bmi-15)/25, 0, 1); colour: `<18.5` → `#4DA6FF`, `18.5–25` → `#00FF39`, `25–30` → `#FFB800`, `≥30` → `#FF4D4D`.
  - `_doSaveProfile()` is non-fatal — network error is swallowed so user still proceeds to step 6.

---

# Next Steps

1. **Plan Assignment** — Admin form to assign `workout_template` / `meal_template` to a user from the orders screen → INSERT `wp_fitness_user_plans` + copy template JSON to `wp_fitness_active_content`; include coach selector using the coach role.
2. **WooCommerce Integration** — Hook `woocommerce_order_status_completed` → check `fitnesspro_product_map` → auto-create pending plan row in `wp_fitness_user_plans` linked to `order_id`.
3. **Frontend Dashboard** — Shortcode `[fitnesspro_dashboard]` showing active plans from `wp_fitness_active_content` with RTL day/meal layout.
4. **Daily Progress Tracker** — AJAX endpoint for users to mark exercises complete; writes `completed_json` to `wp_fitness_daily_progress`.
5. **Ticket System** — AJAX messaging into `wp_fitness_tickets`; coach and client views with attachment upload.
