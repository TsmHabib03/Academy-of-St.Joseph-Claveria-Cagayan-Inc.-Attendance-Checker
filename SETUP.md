# ACSCCI Attendance System — Setup Guide

This guide walks through installing and configuring the ACSCCI Attendance Checker locally (Windows / XAMPP). Follow each step exactly and replace placeholders with your environment values.

Prerequisites
- XAMPP (Apache + PHP 8+, MySQL 8+) installed and running
- Git (optional) to clone the repository
- phpMyAdmin or MySQL CLI for importing SQL dumps

Default credentials used by the application (change in production):
- DB_HOST: `localhost`
- DB_NAME: `asj_attendease_db`
- DB_USER: `root`
- DB_PASS: `` (empty)

1) Place files in webroot
- Copy or clone the repo into XAMPP's `htdocs` folder. Example path used in this guide:

  c:\xampp\htdocs\ACSCCI-Attendance-Checker

2) Create the database
- Open PowerShell or use phpMyAdmin and create the database:

```powershell
# PowerShell + MySQL CLI (replace with your MySQL credentials if different)
# Create database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS asj_attendease_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

3) Import the SQL schema
- Use the provided SQL export to populate the database. To avoid confusion, we recommend moving the exported file into `database/exports/` and renaming it to a clean filename.

PowerShell example to move/rename the export file:

```powershell
# create exports folder
mkdir database\exports
# move and rename (adapt filename if different)
Move-Item "database\asj_attendease_db (USE THIS).sql" "database\exports\asj_attendease_db.sql"
```

Import with MySQL CLI:

```powershell
mysql -u root -p asj_attendease_db < database\exports\asj_attendease_db.sql
```

Or use phpMyAdmin: select the `asj_attendease_db` database → Import → choose file → Go.

4) Configure application database connection
- Primary config file: `config/db_config.php` — it reads environment variables and `config/secrets.local.php` (ignored by git).
- Recommended local pattern:
  - Keep real credentials only in `config/secrets.local.php` and do not commit it.
  - Tracked files should use safe defaults (empty passwords or placeholders).

Create or edit `config/secrets.local.php` with your local overrides (this file must return an array):

```php
<?php
return [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'asj_attendease_db',
    // Optional: SMTP placeholders
    'SMTP_HOST' => '',
    'SMTP_PORT' => 465,
    'SMTP_SECURE' => 'ssl',
    'SMTP_USERNAME' => '',
    'SMTP_PASSWORD' => '',
];
```

5) Ensure `config/secrets.local.php` is ignored by Git
- Add `config/secrets.local.php` to your `.gitignore` so secrets don't get committed.

Add to `.gitignore` if missing:

```
config/secrets.local.php
```

6) Start services
- Open XAMPP Control Panel → Start `Apache` and `MySQL`.

7) Test the application
- Open a browser and navigate to:

  http://localhost/ACSCCI-Attendance-Checker/

- Test an API route in the browser or via `curl`:

```powershell
# Example: get today's attendance via API (adjust path if needed)
Invoke-WebRequest -Uri "http://localhost/ACSCCI-Attendance-Checker/api/get_today_attendance.php" -UseBasicParsing
```

8) Email / SMTP (optional)
- If you plan to use emails, set SMTP credentials in `config/secrets.local.php`. Use an app password for Gmail or use your SMTP provider.

9) Security & deployment notes
- Never commit `secrets.local.php` with real credentials.
- For production, use environment variables or a secure secrets store.
- If you move the project to another machine, export/import the database and ensure `DB_NAME` matches the imported database.

10) Troubleshooting
- Database connection errors:
  - Verify MySQL is running in XAMPP.
  - Confirm credentials in `config/secrets.local.php` or environment variables.
  - Test connection using MySQL CLI: `mysql -u root -p`.

- Blank pages / 500 errors:
  - Check `apache` and `php` error logs in XAMPP (logs folder).
  - Enable `display_errors` temporarily in `php.ini` only for debugging.

11) Helpful commands (PowerShell)

```powershell
# Start XAMPP services using GUI or use the XAMPP shortcuts.
# Check files changed and commit
git add .
git commit -m "Sanitize local secrets and add setup guide"
git push
```

12) Where to look in the code
- DB connection wrapper: `includes/database.php`
- App-level config: `config/db_config.php`
- Local secrets template: `config/secrets.local.php`
- Admin UI: `admin/` (pages expect `config.php` to be included first)

If you want, I can also:
- Replace hardcoded defaults in `includes/database.php` to use an empty password by default.
- Create a `.gitignore` entry if it doesn't exist.
- Move and rename the SQL export file for you.

---
File created: `SETUP.md`
