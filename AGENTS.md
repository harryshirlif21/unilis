# UNILIS AI Agent Instructions

## Purpose
This repository contains a legacy PHP-based learning management system plus a nested SmartLab module. Use this file to help AI coding agents understand the repo structure, avoid unsafe refactors, and focus on the correct subsystem.

## Key areas
- `config/`, `includes/`, `admin/`, `student/`, `lecturer/`, `api/`, `assets/` and root PHP files form the legacy UNILIS LMS.
- `smart-lab/` is a separate lab management module with its own MVC-like structure, Docker deployment, and user-facing smart lab workflows.
- `vendor/` and `composer.json` show composer is available, but most code is procedural PHP with custom templates and manual database access.
- `docker-compose.yml` and `database.sql` are important for local setup and schema reference.

## Important conventions
- Prefer minimal, targeted changes. Do not rewrite the entire legacy application unless the user specifically asks for a broad refactor.
- Preserve existing database schema and authentication flows; this repo is database-driven and fragile to schema-breaking changes.
- Root app uses `mysqli`/PDO in `config/db.php` and custom session-based auth patterns.
- `smart-lab/` uses a separate routing and controller structure; treat it as its own module unless the task explicitly spans both the root app and SmartLab.
- `COMPREHENSIVE_SYSTEM_ANALYSIS.md` is the best single architecture reference for this repo.

## Setup and run guidance
- Root app is typically run under XAMPP / Apache with MySQL and imported SQL from `database.sql` or `database_setup/` scripts.
- `smart-lab/` also includes its own docs: `smart-lab/README.md`, `smart-lab/DEPLOYMENT.md`, and `smart-lab/PROJECT_SUMMARY.md`.
- If code touches deployment or environment configuration, note that `smart-lab/DEPLOYMENT.md` describes Docker Compose and GitHub Actions deployment.

## Useful reference files
- `COMPREHENSIVE_SYSTEM_ANALYSIS.md` — architecture, data model, and module overview
- `smart-lab/README.md` — SmartLab local setup
- `smart-lab/DEPLOYMENT.md` — SmartLab deployment and Docker usage
- `smart-lab/PROJECT_SUMMARY.md` — SmartLab feature status and design
- `composer.json` — composer dependencies and autoload settings
- `config/db.php` and `smart-lab/config/database.php` — database connection setup
- `docker-compose.yml` — root deployment and service configuration

## What not to do
- Do not assume modern PHP frameworks or conventions (Laravel, Symfony, etc.). This is a custom PHP codebase.
- Do not remove or rename database tables without user consent.
- Do not replace procedural PHP with a framework unless explicitly requested.

## Recommended next customization
Create a focused skill or `.github/copilot-instructions.md` for `smart-lab/` specifically, since it has its own deployment docs and MVC-like structure.
