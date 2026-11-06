# Event rental upgrade — installation

This version adds a date-aware reservation cart, admin order processing, and a 75%-viewport photo lightbox. It preserves existing product photos, thumbnails, descriptions, and quantity fields.

## Before deployment

1. Back up your database and existing site.
2. Run `mysql -u beholden -p YOUR_DATABASE < sql/migrate_rentals.sql` exactly once.
3. Deploy the updated `app/`, `public/` files. Keep your existing `.env` and `vendor/` directory. Set `APP_NAME` to your business name if desired.
4. Make sure the server's PHP timezone and MySQL timezone are aligned with your business locale. Set `date.timezone=America/Chicago` in PHP configuration if appropriate.
5. Test a sample reservation, order checkout, return and cancellation on staging before using real inventory.

## Behavior

- Each product's `quantity` is **total physical inventory**. It is NOT decremented in the database when rented.
- The available quantity is computed for requested dates from `quantity` minus overlapping **reserved** orders and all **out** orders. An overdue checked-out order continues to block stock until an admin marks it returned. Returned/cancelled orders release stock.
- Return date is **exactly 14 days after pickup**. Reservation overlap uses half-open `[pickup, return)` intervals, allowing a new pickup on the same day as a scheduled return. If your cleaning/turnaround policy requires buffer days, adjust this logic.
- Reservations are created transactionally using `SELECT ... FOR UPDATE` product row locks to prevent concurrent overbooking during checkout. Do not edit inventory quantities below already committed reservations.
- The admin **Rental orders** page lists reservations and supports mark picked up, mark returned, and cancel before pickup. Mark returned only after physical items are received. No partial returns or per-item damage adjustments yet.
- The cart and checkout do not collect online payment. **This is a reservation system, not a credit-card payment integration.** Contact customers to arrange payment. A production payment workflow requires a payment provider and verified webhook events.
- Confirmation is displayed on-screen only; email notifications, sales tax, deposits, customer accounts, refund policy, and delivery scheduling are not implemented.
- `order-success.php` shows the order number once, via the session. Save the order number or find it in CMS.
- The photo modal displays the full S3 presigned original; thumbnail carousel uses smaller preview objects. Because GET URLs expire after 15 minutes, a page left open longer may need refreshing.

## Security and operations

Use HTTPS. Add rate limiting, bot protection, monitoring, email confirmations and a payment provider before launching publicly. Validate policies with your business requirements. The supplied files were syntax checked but not tested against your live Apache/PHP-FPM/MySQL/S3 deployment.
