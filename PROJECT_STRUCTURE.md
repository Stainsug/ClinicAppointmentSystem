# Project Structure

This project now uses a route-safe layered structure.

## Entry Points (Root)
Root `.php` files are lightweight stubs that include actual modules under `app/`.
This keeps existing URLs working (for example `admin_login.php`, `dashboard.php`).

## Application Modules
- `app/admin/` -> admin pages and admin workflows
- `app/patient/` -> patient pages and patient workflows
- `app/doctor/` -> doctor pages and schedule workflows

## Shared Backend
- `config.php` -> database connection
- `includes/admin_auth.php` -> admin auth guard
- `includes/doctor_auth.php` -> doctor auth guard

## Frontend Assets
- `assets/css/refined-theme.css` -> shared visual theme

## Database Scripts
- `database/clinic_appointment_system.sql`
- `database/admin_module.sql`
- `database/doctor_module.sql`
- `database/doctor_login_setup.sql`
- `database/schedule_module.sql`

## Notes
- Internal module files use `__DIR__`-based requires for stable includes.
- You can later remove root stubs only if you are ready to update every URL and link target.
