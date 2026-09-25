# Customer CSV import

RV Transport coding challenge: a small Laravel app for reviewing customer records before importing them. Upload a CSV, inspect valid and rejected rows, then confirm the import. The interface is built with Inertia, Vue and Tailwind.

![Customer import preview](docs/screenshots/desktop-preview.png)

[Mobile preview](docs/screenshots/mobile-preview.png) · [Desktop result](docs/screenshots/desktop-summary.png) · [Mobile result](docs/screenshots/mobile-summary.png)

## Run locally

Requires PHP 8.3+, Composer, Node.js and a PHP SQLite driver. From a fresh clone:

```bash
cp .env.example .env
touch database/database.sqlite
```

In `.env`, change `DB_CONNECTION` to `sqlite` and remove the `DB_DATABASE` line. Laravel will use `database/database.sqlite`. Then run:

```bash
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Open <http://127.0.0.1:8000> and try the [sample CSV](public/samples/customers.csv). To use PostgreSQL instead, keep the default `DB_CONNECTION=pgsql`, create a database and set the `DB_*` values in `.env` before migrating.

## What the import does

1. Uploads a CSV of up to 2 MB and 1,000 customer rows to private local storage.
2. Previews every row with validation errors and counts; preview does not write customers to the database.
3. On confirmation, reads and validates the staged file again, saves valid rows and reports total, imported and skipped counts. Canceling, replacing or completing an import deletes its staged file.

The header must contain these six columns, in any order:

```text
first_name,last_name,email,phone,birth_date,country
```

The parser accepts UTF-8 CSV with an optional BOM and comma-separated fields. Header spaces and hyphens are normalized to underscores. `phone` and `birth_date` can be empty; other fields are required. Birth dates use `YYYY-MM-DD` and cannot be in the future. Emails are lowercased and checked against both the file and existing customers. A database unique index and `insertOrIgnore` protect against a duplicate inserted between preview and confirmation.

## Verify

```bash
php artisan test --filter=CustomerImportTest
npm run build
```

The feature tests cover preview without persistence, invalid and duplicate rows, malformed headers, reuse of an email from an invalid row, and confirmation without a staged upload.

## Scope

This is a single-operator exercise with no authentication. It should run only in a trusted local environment until access control is added. Imports run synchronously; there is no job queue, history or progress tracking. Abandoned staged files have no timed cleanup. Displayed row numbers count CSV records, so a quoted line break within a field does not change the displayed number.
