# Inventory tracking update

Replace: app/bootstrap.php, public/products.php, public/index.php, public/cart.php, public/admin/index.php.
No SQL migration needed. products.quantity remains the total owned quantity. Available stock is computed by subtracting items in reserved and out orders. Marking an order returned or cancelling it releases stock. Checkout rechecks stock under product-row locks.

IMPORTANT: this conservatively blocks stock for all active reservations, even if future rental dates do not overlap. A date-aware reservation calendar is a separate enhancement.
