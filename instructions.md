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

## [2026-04-19] — Prompt 4 - Part 3: Combined Checkout AJAX Handler

- **Action:** Added single combined AJAX endpoint `fp_process_checkout` that the Pay button calls — performs nonce check, full profile sanitization + transient write, WC product resolution, cart add, and returns checkout URL in one round-trip.
- **Files Modified:**
  - `public/class-fitnesspro-checkout-ui.php` — Added `ajax_process_checkout()`: verifies nonce + login → sanitizes full JSON payload → `set_transient('fp_checkout_profile_{user_id}')` → resolves product ID from `fitnesspro_product_map` → `WC()->cart->empty_cart()` + `add_to_cart()` → returns `wc_get_checkout_url()`.
  - `assets/js/checkout-flow.js` — `_handlePay()` now calls `fp_process_checkout` with `profile: JSON.stringify(this._data)` (full collected state); shows info notice during processing, success notice before redirect (600ms grace), re-enables button on error; removed the intermediate `_doSaveProfile()` call from Step 5 → Next (no longer needed).
  - `includes/class-fitnesspro-core.php` — Wired `wp_ajax_fp_process_checkout`.
- **AJAX Endpoints — Complete Reference:**

  | Action | Handler | Access | Purpose |
  |---|---|---|---|
  | `fp_cof_auth` | `ajax_auth()` | nopriv + priv | Login / Register, returns fresh nonce |
  | `fp_cof_save_profile` | `ajax_save_profile()` | priv | Save profile to transient (intermediate) |
  | `fp_cof_add_to_cart` | `ajax_add_to_cart()` | priv | Add single product to WC cart (standalone) |
  | `fp_process_checkout` | `ajax_process_checkout()` | priv | **Pay button** — save profile + add to cart + return checkout URL |

- **Key Decisions:**
  - Single combined endpoint used by Pay button: reduces round-trips and guarantees transient is written atomically with the cart add in the same request.
  - `fp_cof_save_profile` and `fp_cof_add_to_cart` kept wired — they remain available for future standalone use (e.g., cart recovery flows).
  - 600ms redirect delay after success notice — gives user visual confirmation before page navigation.

## [2026-04-19] — Prompt 4 QA Audit: Four Bug Fixes

- **Action:** Audited Phase 4 CSS/JS/PHP against master spec and patched four gaps.
- **Bug 1 — Double CSS padding (`.fco-step`):** `.fco-steps-wrapper` already had `padding: 24px 20px 0`; `.fco-step` added another `padding: 0 20px` = 40px horizontal total on mobile. Fixed: removed `padding: 0 20px` from `.fco-step`.
- **Bug 2 — Email-only login:** Spec required phone OR email; login input was `type="email"`, PHP used only `get_user_by('email')`. Fixed: input changed to `type="text" id="fco-login-identifier"`, `do_login()` rewrites to try email first then `fp_phone` meta lookup, JS payload key changed from `email` to `identifier`.
- **Bug 3 — No client-side password validation:** Register form sent AJAX before checking password length; server caught it but wasted a round-trip. Fixed: JS guard checks `pass.length < 8` before `_doAuth('register')` call.
- **Bug 4 — No directional back animation:** `_showStep(n, forward)` accepted `forward` param but never used it — going back played the same `fco-fade-up` (upward) animation. Fixed: `_showStep()` sets `container.dataset.navDir = forward ? 'forward' : 'back'`; added `@keyframes fco-fade-down` (translateY -18px → 0) and CSS rule `[data-nav-dir="back"] .fco-step.is-active { animation: fco-fade-down }`.

## [2026-04-19] — Phase 5: WooCommerce Order Completion & AI Processing Page

- **Action:** Wired order completion to DB plan creation; built full-screen AI animation landing page with 10-second progress bar and Persian text.
- **Files Created:**
  - `includes/class-fitnesspro-wc-integration.php` — `FitnessPro_WC_Integration`: `on_order_completed()` guards with `_fp_plan_created` meta, calls `resolve_plan_type()`, INSERTs into `wp_fitness_user_plans` (status=`pending`, expiry=+30 days), deletes checkout transient; `redirect_to_ai_landing()` fires on `woocommerce_thankyou` — locates published page with `[fitness_ai_processing]` shortcode via `$wpdb` content query, injects `window.location.replace()` JS.
  - `public/class-fitnesspro-ai-landing.php` — `FitnessPro_AI_Landing`: shortcode `[fitness_ai_processing redirect="" seconds="10"]`; renders `#fp-ai-container` full-screen div with `data-redirect` + `data-seconds`; HTML includes animated background (grid + 8 particles + scanline sweep), glowing orb (3 rotating rings + core emoji 🧠 + conic scan), Persian headline, `.fp-ai-progress-bar` + `#fp-ai-fill` + `#fp-ai-pct` + `#fp-ai-eta`, 4-step checklist at `data-delay="1,3,5,8"`.
  - `assets/css/ai-landing.css` — Full-screen `position:fixed; inset:0; z-index:99999; background:#0a0a0a`; animated grid pan `@keyframes fp-grid-pan`; scanline sweep `@keyframes fp-scanline`; 8 floating particles `@keyframes fp-float`; 3-ring spinner `@keyframes fp-ring-spin`; core `@keyframes fp-core-pulse`; conic scan sweep; progress fill with glow + leading dot pseudo; step dot `@keyframes fp-dot-pulse`; `.is-active` and `.is-done` states with neon green color + box-shadow tick checkmark.
  - `assets/js/ai-landing.js` — `requestAnimationFrame` loop: percentage counter 0→100 over `seconds * 1000ms`; updates `fillEl.style.width`, `pctEl.textContent`, `barEl[aria-valuenow]`, `etaEl.textContent`; `setTimeout` at `data-delay * 1000ms` per step: removes `.is-active` from previous, adds `.is-active` to current; marks all `.is-done` at `totalMs - 500`; redirects via `window.location.href` after `totalMs + 600ms`.
- **Files Modified:**
  - `hanafit-app.php` — Added `require_once` for `public/class-fitnesspro-ai-landing.php` and `includes/class-fitnesspro-wc-integration.php`.
  - `includes/class-fitnesspro-core.php` — `define_public_hooks()`: instantiates `FitnessPro_AI_Landing` and wires `init → register_shortcode`, `wp_enqueue_scripts → maybe_enqueue_assets`; instantiates `FitnessPro_WC_Integration` and wires `woocommerce_order_status_completed → on_order_completed`, `woocommerce_thankyou → redirect_to_ai_landing`.
- **Key Decisions:**
  - `_fp_plan_created` order meta guards against duplicate plan rows on repeated `completed` → `processing` → `completed` status toggles.
  - AI landing page discovered at runtime via `$wpdb` content LIKE query — no hardcoded page ID required; admin just creates a page with `[fitness_ai_processing]`.
  - `woocommerce_thankyou` redirect uses `window.location.replace()` — removes the WC thank-you page from browser history so Back button doesn't loop back to it.
  - AI JS uses `requestAnimationFrame` for the percentage counter (smooth, battery-friendly) while step activations use `setTimeout` keyed to `data-delay` — two independent timelines that stay in sync with the fixed `seconds` duration.
  - All Persian strings wrapped in `esc_html_e()` / `__()` for i18n.

## [2026-04-20] — Phase 6: Coach Fulfillment Panel

- **Action:** Built the complete coach dashboard — client health profile viewer, template browser, inline personalisation editor, plan publishing to `wp_fitness_active_content`, system + email notification, and admin coach-assignment from the orders table.
- **Files Created:**
  - `admin/class-fitnesspro-coach-panel.php` — `FitnessPro_Coach_Panel`: `render()` outputs RTL two-column layout (client list + sticky profile panel) + two-step template/editor modal; `ajax_get_templates()` returns all published `workout_template`/`meal_template` CPT posts with decoded `_tpl_data` JSON; `ajax_assign_plan()` verifies coach ownership, sanitizes + upserts into `wp_fitness_active_content`, activates plan (`status → active`), pushes notification; `ajax_assign_coach()` admin-only AJAX to set `coach_id` on any plan.
  - `assets/css/coach-panel.css` — Client list (4-col RTL grid), profile side panel (2-col field grid + chip tags), full modal overlay (2-step: template cards → plan editor), day tab switcher, exercise row grid (5-col: name/sets/reps/note/remove), meal cards (items textarea + 4-col macro inputs), `fp-assign-coach-modal` for orders table.
  - `assets/js/coach-panel.js` — `FitnessProCoachPanel` OOP class: delegated click on list → `_showProfile()` / `_openModal()`; modal template list AJAX; card select enables "Next"; `_buildWorkoutEditor()` (day tabs + exercise rows) / `_buildMealEditor()` (meal cards); `_collectPlanData()` reads all inputs; `_publish()` AJAX + updates row UI; `FitnessProCoachAssign` class handles inline orders-table coach assignment modal via existing `fp_assign_coach` endpoint.
- **Files Modified:**
  - `includes/class-fitnesspro-wc-integration.php` — `on_order_completed()`: reads transient before deletion, saves to `fp_health_profile` user meta (persistent) so coaches can read it after transient expiry.
  - `admin/class-fitnesspro-admin.php` — `render_coach_dashboard()` delegates to `FitnessPro_Coach_Panel::render()`; added `enqueue_coach_assets()` method; `render_orders_page()` now includes "Assign Coach" modal HTML with populated coach `<select>` + nonce hidden input.
  - `admin/class-fitnesspro-orders-table.php` — `column_default` case `coach`: admins see coach name + "تخصیص مربی" button (with `data-plan-id` / `data-coach-id`) that opens the assign modal; non-admins see read-only output.
  - `includes/class-fitnesspro-core.php` — `define_admin_hooks()`: instantiates `FitnessPro_Coach_Panel`, wires `admin_enqueue_scripts → enqueue_assets`, `wp_ajax_fp_get_coach_templates`, `wp_ajax_fp_assign_plan`, `wp_ajax_fp_assign_coach`.
  - `hanafit-app.php` — Added `require_once` for `admin/class-fitnesspro-coach-panel.php`.
- **User Meta Keys Added:**
  - `fp_health_profile` — JSON of checkout profile data, persisted on order completion (was transient-only before).
  - `fp_notifications` — JSON array of `{type, plan_type, message, created_at, read}` objects; appended to on plan publish.
- **AJAX Endpoints — Phase 6:**

  | Action | Handler | Access | Purpose |
  |---|---|---|---|
  | `fp_get_coach_templates` | `ajax_get_templates()` | coach + admin | Load published templates for plan type |
  | `fp_assign_plan` | `ajax_assign_plan()` | coach + admin | Upsert to wp_fitness_active_content + activate plan + notify |
  | `fp_assign_coach` | `ajax_assign_coach()` | admin only | Set coach_id on a plan from orders table |

- **Key Decisions:**
  - Coach visibility: `ajax_assign_plan()` checks `coach_id = get_current_user_id()` for coaches but bypasses for admins — allows admin to test/override without re-assigning themselves.
  - Profile data durable copy: moved from transient (7200s TTL) to `fp_health_profile` user meta on order completion — coaches can read it days/weeks later.
  - Inline plan editor reuses the same JSON structure as `_tpl_data` from Phase 2 meta boxes — no conversion layer needed between template storage and active content storage.
  - `wp_fitness_active_content` upsert uses `SELECT id` + conditional UPDATE/INSERT rather than `ON DUPLICATE KEY UPDATE` — avoids MySQL-specific syntax and keeps it compatible with `$wpdb` abstraction.
  - `wp_mail()` call is non-blocking (WP handles it via PHPMailer); if SMTP is not configured, it fails silently — `fp_notifications` user meta provides a reliable fallback notification channel for the frontend dashboard.

## [2026-04-21] — Phase 7: User Dashboard & Daily Progress

- **Action:** Built the `[fitnesspro_dashboard]` shortcode — a full-screen dark mobile-app UI showing today's workout/meal checklist, SVG progress ring, and a pulsing renewal CTA when the plan expires in ≤ 3 days.
- **Files Created:**
  - `public/class-fitnesspro-user-dashboard.php` — `FitnessPro_User_Dashboard`: `render()` dispatches to `render_dashboard()` / `render_preparing()` / `render_no_plan()` / `render_login_prompt()` based on user + plan state. `render_dashboard()` queries `wp_fitness_user_plans` INNER JOINed with `wp_fitness_active_content` (only plans with published content), renders one pane per active plan with: SVG progress ring (r=50, circumference=314.16, PHP-computed initial `stroke-dashoffset`), renewal banner (≤3 days) or expiry pill (>3 days), day title, workout exercise list or meal slot list with neon checkboxes. `ajax_save_progress()` verifies plan ownership, reads/updates/upserts `wp_fitness_daily_progress` using a nested `{plan_id: {key: bool}}` JSON structure so multiple plans share one record per user per day.
  - `assets/css/user-dashboard.css` — Dark theme (`#0d0d0d`), CSS custom properties, Vazirmatn font, glassmorphism task cards (`backdrop-filter: blur`), custom neon checkbox (hidden `<input>`, `.fp-checkmark` square with `:checked` neon fill + `::after` tick), SVG ring with `transition: stroke-dashoffset 1s`, `.fp-ring--complete` pulsing glow animation, renewal banner with orange gradient + `fp-renewal-glow` pulse keyframe, chip badges (green for sets/reps, orange for kcal), full-height state screens for login/no-plan/preparing.
  - `assets/js/user-dashboard.js` — `FitnessProUserDashboard` class: `_animateRings()` uses double-rAF to trigger CSS transition from 0 → target `stroke-dashoffset` on page load; `_bindTabs()` switches `.is-active` between plan panes; `_bindCheckboxes()` toggles `.is-done` on task item, calls `_updateRing(planId)` (recomputes offset + percentage + shows all-done), and `_persist()` (400ms debounce before AJAX `fp_save_progress`).
- **Files Modified:**
  - `hanafit-app.php` — Added `require_once` for `public/class-fitnesspro-user-dashboard.php`.
  - `includes/class-fitnesspro-core.php` — `define_public_hooks()`: instantiates `FitnessPro_User_Dashboard`, wires `init → register_shortcode`, `wp_enqueue_scripts → maybe_enqueue_assets`, `wp_ajax_fp_save_progress → ajax_save_progress`.
- **AJAX Endpoints — Phase 7:**

  | Action | Handler | Access | Purpose |
  |---|---|---|---|
  | `fp_save_progress` | `ajax_save_progress()` | logged-in only | Toggle checklist item in `wp_fitness_daily_progress` |

- **Key Decisions:**
  - `render_dashboard()` only shows plans that have published content (INNER JOIN with `wp_fitness_active_content`) — users with only pending plans see the "preparing" screen, not a broken checklist.
  - Progress ring rendered server-side at the correct value (no flash-of-zero) but always starts at `stroke-dashoffset = circumference` in HTML; JS uses double-`requestAnimationFrame` to trigger the CSS transition from 0 → target on first paint.
  - Renewal check uses `days_until_expiry()` with end-of-day (`23:59:59`) so the plan remains valid for the full expiry date, not until midnight.
  - Progress stored as `{plan_id: {key: bool}}` JSON — single `wp_fitness_daily_progress` row per user per day regardless of how many concurrent plans they have; nested by plan ID to prevent key collisions between workout exercise indices and meal slot names.
  - AJAX save is debounced 400 ms per checkbox — rapid toggling doesn't flood the server; the UI updates instantly and the final state is persisted.
  - Renewal CTA button (`fp-renewal-btn`) links to the `[fitness_checkout_flow]` page (found via DB query at render time) — no hardcoded URL, works regardless of page slug.
  - `wp_add_inline_script()` with `'before'` position injects AJAX config before `user-dashboard.js` runs — called inside the shortcode renderer (which executes during `the_content` filter, before `wp_footer`), so the script tag order in the DOM is correct.

---

# Next Steps

1. **Ticket System** — AJAX messaging into `wp_fitness_tickets`; coach and client views with attachment upload.
