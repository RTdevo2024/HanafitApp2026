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

| Table Name | Description | Status |
|---|---|---|
| *(none yet)* | | |

## Post Meta Keys

| Meta Key | Post Type | Description | Status |
|---|---|---|---|
| *(none yet)* | | | |

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

---

# Next Steps

1. Scaffold the main plugin file (`hanafit-app.php`) with plugin header, autoloader bootstrap, and activation/deactivation hooks.
2. Define the core directory structure: `includes/`, `admin/`, `public/`, `assets/js/`, `assets/css/`, `templates/`.
3. Design and register the first custom database table(s) — likely `fitness_plans` and `plan_exercises`.
4. Create the base OOP class structure: `Plugin`, `Loader`, `Admin`, `Public_Facing`.
5. Integrate WooCommerce dependency check on plugin activation.
