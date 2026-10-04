# BDRPIS

Barangay Digital Records and Portal Information System — resident services, document
requests, relief distribution and resident verification for a single barangay.

Laravel 13 on PHP 8.5, MySQL, Tailwind CSS 3 and Alpine.js. No SPA framework, no
component library, no paid services.

---

## Stack

| | |
|---|---|
| Framework | Laravel 13 (`laravel/framework ^13.17`) |
| Language | PHP 8.5 |
| Database | MySQL |
| Frontend build | Vite 8 via `laravel-vite-plugin` |
| CSS | Tailwind CSS 3.4, `tailwind.config.js` + PostCSS/autoprefixer |
| Interactivity | Alpine.js 3 (loaded in `resources/js/app.js`) |
| Font | Figtree, self-hosted in `public/fonts` |
| Tests | PHPUnit 12 |

Two entry points are compiled by Vite: `resources/css/app.css` and
`resources/js/app.js`. Blade is the templating layer throughout.

---

## Setup

Requires PHP 8.5, Composer, Node 20+ and a running MySQL server.

```bash
# 1. dependencies
composer install
npm install

# 2. environment
cp .env.example .env
php artisan key:generate

# 3. point .env at your database, then:
php artisan migrate --seed
```

`APP_URL` in `.env` must match how you actually serve the app, because signed
URLs and asset URLs are built from it. The Vite dev server proxies asset requests,
so `npm run dev` plus `php artisan serve` is the normal combination.

### Database

`php artisan migrate --seed` targets the database named in `.env`. The test suite
uses a **different** one, `bdrpis_testing`, so create it once before running tests:

```sql
CREATE DATABASE bdrpis_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`tests/TestCase.php` asserts the suite is pointed at `bdrpis_testing` and refuses to
run otherwise, so a misconfigured `.env` cannot make the suite touch real data.

---

## Seed accounts

`php artisan db:seed` creates these. **Password for all of them: `password`**

| Email | Role | Notes |
|---|---|---|
| `admin@bdrpis.test` | Admin | The Barangay Captain. Sees every staff screen. |
| `secretary@bdrpis.test` | Secretary | Operational staff screens; no admin-only pages. |
| `kagawad@bdrpis.test` | Kagawad | Assigned to the first sector. |
| `kagawad-unassigned@bdrpis.test` | Kagawad | Deliberately has **no** sector, so the empty state is demonstrable. |

**No resident is seeded.** Residents are real records, not demo rows, so a fresh
database starts with none. To get one, register through the public form at
`/register` (five short steps), then sign in as the Secretary and approve them on
**Staff → Resident Verification**. Only then do they appear in the Residents list
and gain access to their own dashboard.

### The four roles

Permissions are specified in **[ROLES.md](ROLES.md)** — that file is the source of
truth, not this one. In short:

- **Admin** — the Barangay Captain. Everything a Secretary can do, plus User
  Accounts, Sectors & Kagawad, Settings and Audit Logs.
- **Secretary** — Resident Verification, Residents, Document Requests, Relief
  Distribution, Announcements. Owns announcements outright, including deleting them.
- **Kagawad** — their own sectors only: the relief checklist, and a read-only list
  of the residents in their sector with their relief status.
- **Resident** — their own household only: document requests, relief history, their
  own profile.

There is no `captain` role in the database; the Captain **is** the Admin. No role
area overlaps another — a Kagawad gets 403 in the staff area and an Admin gets 403
in the Kagawad area.

---

## Running the tests

```bash
php artisan test                              # everything
php artisan test --filter=RolesMatrixTest     # one file
vendor/bin/phpunit tests/Feature/KagawadSectorResidentsTest.php
```

The suite uses `RefreshDatabase`, so `bdrpis_testing` is migrated and rolled back
per test. It does not need the seed data.

Code style is enforced with Pint and is part of the finish line for any change:

```bash
vendor/bin/pint --dirty          # fix
vendor/bin/pint --test           # check only
```

Tests asserting markup are not sufficient evidence that a control works — an Alpine
`x-on:click` outside any `x-data` scope is silently inert with no console error.
Verify interactions in a browser.

## Building assets

```bash
npm run build     # production build into public/build (committed)
npm run dev       # Vite dev server with hot reload
```

`public/build` is **committed to the repository**, so a fresh clone renders styled
pages without running a build. Re-run `npm run build` whenever a Tailwind class
changes and commit the result — the stylesheet is compiled by scanning source, so a
new utility class does not exist in CSS until you do.

---

## Where things are

| Path | What lives there |
|---|---|
| `routes/web.php` | Every route, grouped by area, with its middleware. |
| `app/Http/Controllers/{Staff,Kagawad}/` | Staff and Kagawad screens. |
| `app/Http/Controllers/Resident/` | Resident screens. |
| `resources/views/staff/`, `resources/views/kagawad/` | Staff-area Blade, using `x-staff.*` components. |
| `resources/views/components/staff/` | Staff-area component set (table, filter bar, badges, dialogs). |
| `resources/views/components/` | **Shared** components, used by the resident and public areas too. |
| `app/Support/RoleNavigation.php` | What each role can reach, as data. |
| `database/seeders/` | Demo data and accounts. |

### Mail setup

Two messages depend on this working, and nothing else in the application sends mail:

- **Confirm your email address**, sent the moment a resident registers. Until they
  press that link the account exists but cannot reach the resident area.
- **Email me a copy**, the encrypted PDF of a ready document, sent to the
  resident's own verified address.

Both are sent **synchronously** — the notification is not queued, so there is no
worker to run and no `jobs` table to watch.

#### 1. `APP_URL` has to match your browser

This is the step that breaks first and is easiest to miss. The confirmation link
is a signed URL built from `APP_URL`, so if it says `localhost` and you are
reading the email on a phone, the link points at the phone and can never work.

Set it to the address you actually browse from, port included:

```
APP_URL=http://localhost:8000        # php artisan serve
APP_URL=http://localhost:8001        # php artisan serve --port=8001
APP_URL=https://bdrpis.example.gov.ph # production
```

#### 2. Local: read the email out of the log

The default needs no credentials and no network. Every message is written whole
into `storage/logs/laravel.log`, and the verification URL is on a line of its
own:

```
MAIL_MAILER=log
```

To find the link during development:

```
tail -f storage/logs/laravel.log
```

Look for `verification/verify` and copy the URL out. This is also how the
end-to-end check reaches the confirmation step.

#### 3. Real delivery over Gmail

Turn on SMTP and supply an App Password:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your.barangay@gmail.com
MAIL_PASSWORD=<paste the 16-character App Password>   # spaces are ignored
MAIL_FROM_ADDRESS=your.barangay@gmail.com
```

To get an App Password:

1. Turn on 2-Step Verification for the Google account — the menu below only
   appears once it is on.
2. Go to <https://myaccount.google.com/apppasswords>.
3. Create one named for this application and copy the 16 characters **when it is
   shown**. Google displays it exactly once and there is no way to read it back
   later; if it is lost, delete it and make another.

Three things that quietly do not work:

- **The account password is not the App Password.** Gmail rejects it, or accepts
  the connection and drops the mail. Neither is obvious from the symptom.
- **`MAIL_FROM_ADDRESS` should be the same account, or an alias that account is
  allowed to send as.** A third address is silently discarded.
- **Port 587 with `tls` is deliberate.** Port 465 wants `ssl` instead.

#### 4. After editing `.env`

```bash
php artisan config:clear && php artisan cache:clear
```

and restart the server. Config is cached, so a running server keeps serving the
values it read at boot — edit `.env` and reload the page and nothing appears to
have happened. In short: after changing `.env`, run `php artisan config:clear`
and restart the server.

#### Link lifetime

`AUTH_VERIFICATION_EXPIRE` sets how long a confirmation link stays valid, in
minutes. The default is `1440` — 24 hours. The framework's own default is 60,
which is short for a reader on a phone whose mail may sit unread behind a
secretary or a spam folder for a day; the setting is a plain number, and the
message tells the resident the same number the server enforces.

#### Never commit a credential

`.env.example` carries placeholders only, and `.env` is ignored by git. Check
before pushing:

```bash
git ls-files | grep -v vendor | xargs grep -nE "MAIL_PASSWORD=.+|APP_KEY=base64:.+"
```

Any hit is a real secret in a tracked file. See `.env.example` for the full set
of placeholder values.

---

## Before you change the staff area

Two documents, and they disagree on purpose if you let them:

- **[ROLES.md](ROLES.md)** — the owner's confirmed permission model. Check it before
  adding a route, a nav item or a column: if your change makes a row in that table
  wrong, the table is the thing that has to change, and it has to change
  deliberately.
- **[.ai/rules/staff-polish.md](.ai/rules/staff-polish.md)** — the working rules for
  the staff area, including which files are shared and must not be edited in place.
  Run the diff guards it lists before finishing.

The resident and public areas are off-limits when working on staff screens. If a
staff page needs something only the resident area has, add a `x-staff.*` variant
rather than editing the shared file.

## Project history

`PROMPT_LOG.md` records each task with what changed, what was found and what was
deliberately left alone. `docs/STAFF_UI_AUDIT.md` is the staff-area UI audit, with
a resolved/deferred table.
