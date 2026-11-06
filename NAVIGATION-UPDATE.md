CMS navigation update
=====================
Replace app/layout.php, public/admin/index.php, public/admin/orders.php, and public/assets/style.css.

Changes:
- Removes View storefront from the CMS sidebar.
- Removes redundant Rental orders button and old logout button from Products toolbar.
- Removes redundant Manage products and Account links from Orders toolbar.
- Adds secure CSRF-protected POST Log out control to the global header next to Admin and Cart, visible when signed in.
No database changes required.
