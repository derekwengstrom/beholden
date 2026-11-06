# Add-to-cart modal update

Deploy only these files to the existing site:

- `public/cart.php`
- `public/products.php`
- `public/assets/cart-modal.js` (new)
- `public/assets/style.css`

No SQL changes. Existing cart and checkout remain functional. Adding a product on the rentals page now uses an AJAX POST and opens a cart summary modal. Continue shopping closes the modal and leaves the visitor on the rentals page; Checkout links to the existing checkout process. The full cart page remains accessible from the header for quantity edits. Without JavaScript, forms still POST and redirect to the cart page.
