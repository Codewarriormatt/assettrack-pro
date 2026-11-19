# AssetTrack Pro

A web app for tracking an organization's fixed assets: what you own, who has it, what it is worth today, and when it needs maintenance.

Class project (group work) for a Web Development course, Sep–Oct 2025. Runs locally on XAMPP.

## What it does

- **Login and dashboard**: password login (bcrypt hashes) and a dashboard with asset totals and recent activity.
- **Assets**: add, edit, view and delete assets. Each asset has a unique control number.
- **Depreciation**: straight-line or double-declining-balance. The current value is calculated automatically from cost, purchase date and lifetime.
- **Assignment**: assign an asset to a person (name, email, phone) and a department.
- **Insurance and warranty**: type, provider, start and end dates.
- **Maintenance**: schedule maintenance with a next due date. A pending job sets the asset to "Under Maintenance"; completing it sets the asset back to "Active".
- **Departments**: manager contact details and an active/inactive flag.
- **Users**: manage user accounts. Each user can change their password or delete their own account (after a confirmation step).
- **Activity log**: records logins and changes. Login records are kept even after an account is deleted.
- **Reports**: standard and advanced reports (depreciation, warranty expiring, maintenance due, replacement candidates) with CSV, Excel, PDF and print export.
- **Monitoring**: a monitoring dashboard and a script (`scripts/asset_monitor.php`) that updates asset values and statuses and counts upcoming alerts.
- Prices are shown in Philippine pesos (₱).

## Tech stack

- PHP 8 (built on PHP 8.2) with PDO and prepared statements
- MySQL / MariaDB (built on MariaDB 10.4, the XAMPP default)
- Bootstrap 5.3, jQuery 3.7, DataTables 1.13 (Buttons, Responsive, ColReorder, FixedHeader), JSZip, pdfmake, Font Awesome 6
- XAMPP (Apache + MySQL) for local hosting

The front-end libraries load from public CDNs, so the browser needs internet access.

## Run it on your computer (XAMPP)

1. **Install XAMPP** (with PHP 8) from apachefriends.org. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. **Get the code.** On GitHub click **Code > Download ZIP** and extract it.
3. **Copy the folder into `htdocs`.** Any folder name works, for example:
   `C:\xampp\htdocs\assettrack-pro-main\`
   (A name without spaces is safest.)
4. **Import the demo database.**
   - Open http://localhost/phpmyadmin, click the **Import** tab, choose `database/assettrack_demo.sql`, and click **Import** (or **Go**).
   - Or from a terminal inside the project folder: `C:\xampp\mysql\bin\mysql.exe -u root < database\assettrack_demo.sql`
   - The file creates a database named `asset_management`. If you already have a database with that name, drop it or rename it first, or the import will fail.
5. **Check the database settings** in `config/database.php`. The defaults match a fresh XAMPP install: host `localhost`, database `asset_management`, user `root`, empty password. Change them only if your MySQL is set up differently.
6. **Open the app:** http://localhost/assettrack-pro-main/ (use your folder name).
7. **Log in with the demo account** (see below).

### Optional: run the monitoring script

- From the app: **Monitoring** page > **Update Monitoring** button (admin login required).
- From a terminal: `C:\xampp\php\php.exe scripts\asset_monitor.php`
- It writes its log to `logs/asset_monitor.log`. You can schedule it daily with Windows Task Scheduler or cron.

## Demo login

| Username | Password |
|---|---|
| `admin` | `Demo@1234` |

This is a demo password for local use only. The demo data (departments, assets, maintenance) is fake: emails use `@example.com` and phone numbers are `555-01xx`. Dates in the demo data are relative to the day you import it, so warranty and maintenance alerts show up. To reset everything, drop the database and import the file again.

## Screenshots

Coming soon. Screenshots will be added to [`docs/screenshots/`](docs/screenshots/).

## Post-submission fixes

These changes were made after the class submission, before publishing:

- **Stored XSS fixed** in the delete buttons of the Assets, Departments and Maintenance pages. Names are now passed to JavaScript with `json_encode` + `htmlspecialchars(..., ENT_QUOTES)` instead of being placed inside a quoted JS string.
- **Monitoring script protected**: `scripts/asset_monitor.php` now requires an admin login when opened in a browser. Command-line runs still work without a login.
- **Session fixation fixed**: the session ID is regenerated after a successful login (`session_regenerate_id(true)`).
- **Logs folder blocked from the web**: `logs/.htaccess` (`Require all denied`) stops Apache from serving `logs/asset_monitor.log`.
- **Demo SQL blocked from the web**: `database/.htaccess` (`Require all denied`) stops Apache from serving `database/assettrack_demo.sql`.
- **Monitoring script paths**: the script now builds its include and log paths from `__DIR__`, so it runs from any working folder.
- **Any folder name works**: `getBaseUrl()` now works out the app folder from the request path. Before, the folder name `asset-management-system` was hard-coded.
- **Clean demo database**: personal data, real accounts and the activity history were removed. The new `database/assettrack_demo.sql` has fake sample data, one demo admin, an empty activity log, a `CREATE DATABASE` statement, and no `DEFINER` clauses on the views (so it imports on any MySQL/MariaDB user).

## Known limitations (class project)

This was built as a class project and is meant for **local demo use only**. It is not hardened for the public internet.

- **No CSRF protection.** Forms have no CSRF tokens, and logout is a plain GET link.
- **One role only.** The `users.role` column only allows `Admin`, so every account has full access. There is no role-based access control.
- **No brute-force protection or session timeout** on login.
- **Short passwords allowed.** The minimum password length is 6 characters.
- **Database errors are shown on screen** if the connection fails (`config/database.php`).
- **Database settings are hard-coded** in `config/database.php` (XAMPP default: `root` with no password).
- **CSV export** does not guard against spreadsheet formula injection.
- **CDN libraries** load without Subresource Integrity (SRI) hashes.
- The monitoring script does not run on its own; run it by hand or schedule it.

## Team and credits

Built by a 5-member student team for a Web Development course (Sep–Oct 2025).
My role: requirements (SRS), workflow and business logic, and testing.
Post-submission cleanup and fixes: [Codewarriormatt](https://github.com/Codewarriormatt).

Third-party libraries (Bootstrap, jQuery, DataTables, JSZip, pdfmake, Font Awesome) are loaded from CDNs and keep their own licenses.

## License

All rights reserved. Shared for portfolio viewing only.
