# CMS sidebar update

This update adds a left navigation panel to authenticated CMS pages with **Products** and **Orders** sections. The active section is highlighted, and the navigation becomes a horizontal bar on smaller screens.

## Deploy

From this archive, replace only:

- `app/layout.php`
- `public/assets/style.css`

Keep your `.env`, `vendor/`, database and S3 configuration unchanged. No database migration is required. Refresh the browser cache after deployment.

The sidebar is shown only for authenticated admin pages. Public pages and the login page retain their existing layouts.
