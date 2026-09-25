# Customer CSV import

RV Transport coding challenge 1. A Laravel 13, Inertia/Vue and Tailwind app for reviewing a customer CSV before saving valid rows.

## Run locally

Requirements: PHP 8.3+, Composer, Node.js, and PostgreSQL (or MySQL). Create an empty database first, then:

```bash
cp .env.example .env
# Set DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. The sample CSV is available in the app and at `public/samples/customers.csv`. For a quick local smoke test without PostgreSQL, set `DB_CONNECTION=sqlite` and `DB_DATABASE` to the absolute path of an empty SQLite file before running migrations. Automated tests use in-memory SQLite.

## CSV contract

The first row must contain `first_name,last_name,email,phone,birth_date,country` in any order. Spaces and hyphens in header names are accepted. Files must be UTF-8, comma-separated, at most 2 MB and contain no more than 1000 customer rows. `phone` and `birth_date` may be empty. Dates use `YYYY-MM-DD`; birth dates cannot be in the future. Email is required, normalized to lowercase, and must be unique across the file and database.

The upload is kept in private local storage. Preview validates every row without saving customers. Confirmation reads and validates the staged file again, inserts valid rows, and reports total, imported and failed counts. Invalid rows are skipped. A database unique index and `insertOrIgnore` handle a competing import that inserts the same email between preview and confirmation.

## Decisions and limits

- This is a single-operator exercise, so there is no account system. Add authentication and authorization before exposing it beyond a trusted local environment.
- Duplicate email detection is included; history, queue processing and progress reporting are outside this small submission.
- A staged file is removed after import, cancellation or replacement. Abandoned uploads have no timed cleanup job yet.
- Preview uses row numbers as CSV record numbers. Quoted line breaks inside a field are supported by the parser, but the displayed number does not represent the physical text line in that case.

## Verify

```bash
php artisan test --filter=CustomerImportTest
npm run build
```

The feature tests cover preview without persistence, duplicate and invalid row skipping, malformed headers, and confirmation without a staged file.

Desktop and mobile screenshots of the preview and completion states are in `docs/screenshots/`.
