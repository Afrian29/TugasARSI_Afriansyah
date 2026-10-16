# Project Scheduling App

A project scheduling web application built with **Laravel 13**, **Livewire 4**, and **Pest**. It supports work-breakdown structures, working calendars, Finish-to-Start dependencies with lag, resource allocation, CPM critical-path calculation, Gantt charts, and PERT analysis. The UI is available in **English and Indonesian**.

See [`PRD-Project-Scheduling-App.md`](PRD-Project-Scheduling-App.md) and [`docs/PRD.md`](docs/PRD.md) for requirements, and the `docs/` folder for decision records, suggestions, and the traceability matrix.

## Requirements

- PHP >= 8.3 (with `pdo_mysql`, `sqlite3`, `mbstring`, `openssl` extensions)
- Composer
- MySQL 8 / MariaDB 10.4+ (XAMPP is fine) — for the app database
- Node.js + npm (only needed if you change front-end assets)

## Installation

```sh
composer install
cp .env.example .env        # skip if .env already exists
php artisan key:generate
php artisan migrate          # after the database exists (see below)
npm install && npm run build # only if assets changed; built assets are committed
```

Or run everything in one step:

```sh
composer setup
```

### Database (XAMPP MySQL)

`.env` is pre-configured for XAMPP's MySQL:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_scheduler
DB_USERNAME=project_scheduler
```

Create the databases and a dedicated user (run in phpMyAdmin, **not** as root):

```sql
CREATE DATABASE IF NOT EXISTS project_scheduler
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS project_scheduler_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'project_scheduler'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON `project_scheduler`.* TO 'project_scheduler'@'localhost';
GRANT ALL PRIVILEGES ON `project_scheduler_test`.* TO 'project_scheduler'@'localhost';
FLUSH PRIVILEGES;
```

Then confirm connectivity:

```sh
php artisan migrate:status
```

## Running the app

### Option A — Laravel dev server

```sh
php artisan serve
```

Open <http://127.0.0.1:8000> — the home page lists projects.

### Option B — XAMPP / Apache

1. Start **Apache** (and MySQL) from the XAMPP control panel.
2. Keep this project inside `htdocs` (e.g. `C:\xampp\htdocs\proyekArsi`).
3. Apache serves the `public/` directory — either:
   - visit <http://localhost/proyekArsi/public>, or
   - add a vhost pointing its `DocumentRoot` at `C:\xampp\htdocs\proyekArsi\public`
     and set `<Directory "C:\xampp\htdocs\proyekArsi"> AllowOverride All </Directory>`.
4. Ensure `mod_rewrite` is enabled (it is by default in XAMPP) so `public/.htaccess` routes requests to `index.php`.

The home page is rendered in English by default; use the **EN / ID** switcher in the top navigation to change language (the choice is stored in the session).

## Tests & checks

```sh
composer test        # Pest against in-memory SQLite (default, no DB setup needed)
composer test:mysql  # Pest against MySQL database `project_scheduler_test`
composer lint        # Pint code style check
composer analyse     # Larastan static analysis
composer check       # test + lint + analyse (run this before committing)
```

`phpunit.xml` uses SQLite `:memory:` by default; `phpunit.mysql.xml` switches the same suite to MySQL via `DB_DATABASE=project_scheduler_test` (credentials come from `.env`).

## Project layout

```
app/Domain/Scheduling/   Pure scheduling engine (working calendar, CPM, dependencies)
app/Services/Schedule/   Recalculation + dependency validation (application services)
app/Livewire/            UI components (projects, tasks, calendar, resources, gantt, pert)
app/Models/              Eloquent models
resources/lang/en|id     English and Indonesian translations
docs/                    PRD, DECISIONS, SUGGESTIONS, TRACEABILITY
tests/                   Pest unit + feature tests
```

## Documentation

- [`docs/PRD.md`](docs/PRD.md) — full requirement list (PRJ, TSK, CAL, DEP, ASN, RES, CPM, GNT, PRT)
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — architectural decision records
- [`docs/SUGGESTIONS.md`](docs/SUGGESTIONS.md) — improvement backlog
- [`docs/TRACEABILITY.md`](docs/TRACEABILITY.md) — requirement → code → test matrix
