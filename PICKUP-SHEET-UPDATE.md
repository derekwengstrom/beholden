# Pickup sheet update

For existing installations, upload:
- `public/admin/orders.php`
- `public/admin/pickup-sheet.php` (new)

No database migration or Composer changes needed.

The **Print pickup sheet (PDF)** link appears only for Reserved orders, and the PDF endpoint independently checks administrator authentication and reserved status. The PDF contains customer contact information, pickup/return dates, notes, product quantities, checkboxes, and staff signoff lines. It opens in a new browser tab for printing. It does not change order status; use **Mark picked up / out** after collecting the items.

Product shelf/bin locations are not in the existing schema, so they are not shown.
