# Canteen Hisab

A mobile-first **daily register for a school canteen in Pakistan**. Staff record sales, purchases (maal) and
payments on a phone in a few taps; the owner/admin sees the real cash position, what is owed to each vendor,
how much rent has been set aside and how much the owner has paid out of pocket. Built with Laravel 12, Blade,
Alpine.js and Bootstrap 5. The UI is written in friendly Roman-Urdu/English.

## Key features

- **Quick entry** for sales (per break/segment), purchases (total or itemized), vendor payments, expenses, rent
- **Vendor ledger** with opening balances (owed or advance), running balance and bill photos
- **Partial / advance payments** — paying more than owed pushes a vendor balance negative (advance)
- **Three money buckets** kept separate: canteen cash, rent reserve and owner recoverable (see Accounting overview)
- **Personal-pocket payments** — pay a vendor from your own money; the app remembers what the owner must get back
- **Day closing** with counted cash vs expected cash, mandatory reason on a difference, and carry-forward of the counted cash to the next day
- **Special days** — weekends, holidays, PTM and special working days via the school calendar
- **Duplicate-submission protection** (idempotency keys) so double taps and flaky networks never double-post
- **Audit trail** for balance adjustments, opening-balance changes, closing and reopening a day
- **Dashboard activity feed** of everything done today, with "load more"
- **Private bill photos** (compressed, stored on a private disk, only visible to logged-in users)
- **Roles**: `super_user` (full privileged field edit — settings, closing, reopening, balance corrections), `admin` (same elevated access), and `staff` (daily entry)
- **Reports**: daily, monthly and per-vendor
- **PWA**: installable, offline fallback page

## Requirements

- PHP **8.3+** (extensions: `bcmath`, `gd`, `mbstring`, `pdo_mysql` or `pdo_sqlite`, `fileinfo`, `exif` recommended)
- Composer 2
- Node.js **20+** and npm
- **MySQL 8** (production) — or **SQLite** for local development and for the automated tests

## Installation

```bash
git clone <repo-url> canteen_management
cd canteen_management
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Option A — MySQL 8

```sql
CREATE DATABASE canteen_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'canteen'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON canteen_management.* TO 'canteen'@'localhost';
```

Set these in `.env` (they are the defaults in `.env.example`):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=canteen_management
DB_USERNAME=canteen
DB_PASSWORD=choose-a-password
```

### Option B — SQLite (local/dev)

```bash
touch database/database.sqlite
```

In `.env` comment out the MySQL block and use:

```dotenv
DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/canteen_management/database/database.sqlite   (optional, this is the default location)
```

(If `DB_DATABASE=canteen_management` is still set, remove or comment it — for SQLite it must be a file path or unset.)

### Then, for either database

```bash
php artisan migrate --seed
npm run build          # or `npm run dev` while developing
php artisan serve      # http://127.0.0.1:8000
```

The seeder creates the two users, the three cash accounts, the sale segments (First Break, Second Break,
School Closing / Off Time, Other Sale), default settings and **sample vendors**. The sample vendors are demo data
(marked `SAMPLE vendor`): delete or replace them with real vendors before going live. The first time an admin
logs in with no vendors, a setup wizard (`/setup/vendors`) helps enter real vendors and opening balances.

The timezone defaults to `Asia/Karachi` (`APP_TIMEZONE`); the business date of every entry follows it.

## Default logins

| Role       | Email                  | Password   |
|------------|------------------------|------------|
| Super User | `super@canteen.local`  | `password` |
| Admin      | `admin@canteen.local`  | `password` |
| Staff      | `staff@canteen.local`  | `password` |

**Change both passwords before using the app with real data.**

## PWA notes

- `public/manifest.json` makes the app installable ("Add to Home Screen") with shortcuts for *Add Sale*, *Maal Entry* and *Vendor Ko Diye*.
- `public/sw.js` is a minimal service worker: it only caches the static shell (icons, manifest, `offline.html`). Authenticated pages and
  API responses are **never cached**, so money data is always live and nothing private is stored on the device.
- Service workers need **HTTPS** (or `localhost`). Serve production over HTTPS to get the install prompt.
- After changing the shell files, bump `VERSION` in `public/sw.js` so devices drop the old cache.

## Running tests

```bash
php artisan test
```

Tests use `RefreshDatabase` on an in-memory SQLite database (see `phpunit.xml`), so your real database is untouched.
Feature tests live in `tests/Feature/Canteen/` and cover vendors, credit/partial/advance payments, personal-pocket
payments, owner deposits, sales, rent reserve/skip, day closing and carry-forward, special days, idempotency,
audited corrections, role restrictions, bill uploads, the dashboard feed, balance consistency and login/logout.
Shared set-up (cash accounts, sale segments, settings, admin/staff) is in the trait `Tests\Concerns\SeedsCanteenBasics`.

> Concurrency: the tests verify the row-locking pattern with sequential locked updates. True parallel concurrency is
> database-engine dependent (SQLite ignores `FOR UPDATE`; MySQL/InnoDB honours it).

## Deployment notes (Hostinger)

Hostinger shared hosting usually has **PHP + Composer**, but **no npm**. Vite CSS/JS are therefore
**built on your PC** and committed as `public/dist/` (see `scripts/build-assets.sh`).

### Layout on the server

```
~/domains/your-domain.com/
  canteen_management/     ← this Laravel repo
  public_html/            ← web root (symlinks into canteen_management/public)
```

### First time on Hostinger

1. Upload / clone the repo next to `public_html` (folder name can be anything; scripts auto-detect).
2. Copy `.env.example` → `.env`, set MySQL, `APP_URL=https://your-domain.com`, `APP_ENV=production`, `APP_DEBUG=false`.
3. On your **local** machine (not Hostinger):

```bash
bash scripts/build-assets.sh --commit
git push
```

4. On the **server**:

```bash
cd ~/domains/your-domain.com/canteen_management   # adjust path
git pull
composer install --no-dev --optimize-autoloader   # or php composer.phar …
php artisan key:generate                          # first time only
bash scripts/setup-public-html.sh                 # one-time: wire public_html
bash scripts/deploy-live.sh
php artisan db:seed                               # first time only (sample + admin users)
```

Override paths if needed:

```bash
PUBLIC_HTML=$HOME/domains/your-domain.com/public_html bash scripts/setup-public-html.sh
```

### Every later deploy

**Local (when CSS/JS changed):**

```bash
bash scripts/build-assets.sh --commit && git push
```

**Server:**

```bash
bash scripts/deploy.sh              # git pull + migrate + cache + public_html check
# or after a manual pull:
bash scripts/deploy-live.sh
```

### Scripts

| Script | Purpose |
|--------|---------|
| `scripts/build-assets.sh` | Local: `npm ci` + `npm run build` (+ optional `--commit`) |
| `scripts/setup-public-html.sh` | One-time: `public_html` → project `public/` symlinks + Laravel 12 `index.php` |
| `scripts/deploy-live.sh` | Server: composer, migrate, caches, storage links (no npm) |
| `scripts/deploy.sh` | Server: `git pull` then `deploy-live.sh` |
| `scripts/deploy-sync-public.sh` | Alias for `setup-public-html.sh` |

- **`php artisan storage:link` is not needed** for invoices: bill photos live on the private disk
  (`storage/app/private/invoices/…`) and are streamed via `/invoices/{purchase}`. Scripts still create
  `public/storage` for any future public uploads (Hostinger-safe `ln -s`, not PHP `symlink()`).
- Make `storage/` and `bootstrap/cache/` writable. **Back up `storage/app/private/invoices` with the DB.**
- **No queue worker is required.**
- Serve over **HTTPS** (needed for PWA).

## Accounting overview

The app tracks four different things. Mixing them up is the most common source of confusion:

| Bucket | Meaning | Goes UP when | Goes DOWN when |
|--------|---------|--------------|----------------|
| **Canteen cash** | Physical cash in the drawer | cash sales, owner deposit | vendor payment from cash, rent paid from cash, expense, rent set aside, owner repayment |
| **Rent reserve** | Cash set aside for the landlord (not spendable) | rent is "set aside" from canteen cash | rent paid from the reserve |
| **Owner recoverable** | Money the canteen owes the owner | owner deposits cash; a bill/rent/expense is paid from the owner's pocket | canteen repays the owner |
| **Vendor outstanding** | Money the canteen owes each vendor | a purchase (bill) is recorded | a payment is made; negative balance = **advance** |

Key rules:

- **Credit purchase** (bill, nothing paid): vendor balance up, cash untouched.
- **Partial payment**: `new balance = previous balance + bill − paid`; cash goes down only by the paid amount.
- **Payment without a bill** (`/payments`): vendor balance down. The purchase form (`/purchases`) always needs a bill greater than zero.
- **Advance**: paying more than owed is allowed; the vendor balance becomes negative and is used up by later bills.
- **Paid from personal pocket**: canteen cash is unchanged, **owner recoverable goes up** by the amount.
- **Owner cash deposit**: canteen cash up **and** owner recoverable up (the owner lent the canteen money).
- **Rent set-aside is a transfer, not an expense**: canteen cash down, rent reserve up. It is only "spent" when the rent is actually paid.
- **Closing a day**: counted cash is compared with expected (`opening + cash in − cash out`). A difference needs a reason, the ledger is
  adjusted to the counted cash and **the counted cash becomes the next day's opening cash**. Only an admin can close or reopen a day.
- Every money value uses fixed 2-decimal arithmetic (BCMath); every balance change runs in a DB transaction with row locks.

## Project layout

- `app/Services` — accounting logic (`PurchaseService`, `VendorLedgerService`, `CashLedgerService`, `OwnerLedgerService`, `RentService`, `DailyClosingService`, …)
- `app/Http/Controllers`, `app/Http/Requests` — thin HTTP layer and validation
- `tests/Feature/Canteen` — feature tests described above

## License

Private project. All rights reserved.
