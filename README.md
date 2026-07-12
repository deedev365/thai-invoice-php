# Invoice — Thai Tax Invoice & Billing System

A lightweight, self-contained PHP web application for creating, editing, and printing Thai tax invoices / billing documents. Invoices are stored as JSON files on disk — no database required.

## Tech stack

- **Backend:** Plain PHP (no framework, no Composer dependencies). Business logic and storage live entirely in [lib.php](lib.php).
- **Frontend:** Vanilla JS (no build step, no npm dependencies) + plain CSS.
- **Storage:** JSON files — one default template ([invoice_data.json](invoice_data.json)) plus one file per saved invoice under [invoice/](invoice/).
- **Tests:** PHPUnit ([tests/](tests/)).

## Features

- **Public print view** ([index.php](index.php)) — renders a saved invoice as a clean, print-ready page. No login required, so a link can be shared with a customer. Includes a "Print / Save as PDF" button that uses the browser's print dialog.
- **Admin panel** ([admin.php](admin.php), password-protected) — a form to create and edit invoices: document details, seller, customer, contact person, line items, payment bank accounts, and a note. Includes a live preview iframe that reflects unsaved edits as you type.
- **Create / Save / Duplicate / Delete** — duplicate clones an existing invoice into a brand-new one with a fresh document number; the default blank template ([invoice_data.json](invoice_data.json)) can never be deleted.
- **Automatic totals** — per-item pre-tax amount (qty × price, minus discount %), taxable amount, VAT, total, withholding tax, and amount payable are all derived server-side from the raw item inputs (see `compute_invoice_totals()` in [lib.php](lib.php)) — never entered by hand.
- **Amount in words** — total amount is spelled out automatically in Thai (`...บาทถ้วน`) or English (`... Baht Only`), depending on the invoice's `language` field.
- **Automatic sequential numbering** — new invoices get a number like `DL-YYMMDD001`, computed from the issue date and the highest existing sequence for that day. A file lock ([`with_storage_lock()`](lib.php)) serializes number allocation so concurrent saves never collide.
- **Automatic due date** — calculated from issue date + credit days.
- **Password-protected admin** ([auth.php](auth.php), [login.php](login.php), [logout.php](logout.php)) — a single shared password gates the admin UI and all write/enumeration API calls. Only `api.php?action=get` (viewing one invoice by key) stays public.

## Project layout

```
index.php                 Public print view
admin.php                 Admin panel (password-protected)
login.php / logout.php    Sign-in / sign-out
auth.php                  Session-based auth (password hash resolution, guards)
auth_config.sample.php    Template for auth_config.php (your own admin password)
api.php                   JSON REST API — list / get / save / delete / duplicate / next-number
lib.php                   All business logic + JSON storage (single source of truth)
router.php                Router for the PHP built-in server
invoice_data.json         Default blank invoice template ("New invoice")
invoice/                  Saved invoices (invoice-YYMMDD###.json)
js/invoice-render.js      Shared HTML renderer for the invoice (print view + admin preview)
js/invoice-calc.js        Client-side totals calculation (mirrors lib.php, for live preview)
js/frontend.js            Loads + renders an invoice on the public print view
js/admin.js               Admin form logic (load/save/duplicate/delete, repeaters, live preview)
css/index.css              Print view styles
css/admin.css              Admin panel styles
tests/                    PHPUnit tests (calculations, numbering, data validation/sanitizing)
```

## API (`api.php`)

All responses are `application/json`.

| Method | Action | Description | Access |
|---|---|---|---|
| GET | `?action=list` | List saved invoices | admin |
| GET | `?action=get&data=<key>` | Fetch one invoice's data | public |
| GET | `?action=next-number&date=...` | Preview the next document number | admin |
| POST | `?action=save` | Create or update an invoice | admin |
| POST | `?action=delete` | Delete an invoice | admin |
| POST | `?action=duplicate` | Clone an invoice under a new number | admin |

## Admin password

The default password is `invoice2026` (hard-coded fallback hash in [auth.php](auth.php)). To set your own:

1. Copy [auth_config.sample.php](auth_config.sample.php) to `auth_config.php`.
2. Generate a hash:
   ```
   php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
   ```
3. Paste the hash into `auth_config.php`.

Alternatively, set the `INVOICE_ADMIN_PASSWORD_HASH` environment variable.

## Running locally

```
php -S localhost:8000 router.php
```

Then open `http://localhost:8000/` for the print view or `http://localhost:8000/admin.php` for the admin panel.

## Tests

```
vendor/bin/phpunit
```

Configuration is in [phpunit.xml](phpunit.xml); tests cover totals calculation, document numbering, and invoice data sanitizing/validation.

## 💻 Interface Screenshots

Here is what the invoice generation system looks like in action:

![Invoice Frontend Page](http://utmlink.tech/screenshot/invoice_frontend.png)
![Invoice Main Page](http://utmlink.tech/screenshot/invoice_admin.png)