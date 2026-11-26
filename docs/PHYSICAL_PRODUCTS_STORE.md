# Physical Products Store — Inventory and Fulfillment

## Inventory model

- `products.stock_quantity` is the physical on-hand total.
- `products.reserved_quantity` is stock held for unpaid orders.
- `Product::availableQuantity()` is `stock_quantity - reserved_quantity`; the public shop and cart only offer unreserved stock.
- Each `order_items` product line records `stock_reserved=true` after a checkout transaction successfully reserves its quantity.
- Student checkout and Admin-created orders lock product rows and reserve inventory before payment is submitted. A failed reservation rolls the order transaction back.
- Payment fulfillment locks the order and product, verifies the reservation, decrements both on-hand and reserved quantities, and clears the order-item reservation. Legacy orders without reservations can only consume stock that is not reserved by newer orders.
- Admin cannot reduce on-hand stock below the quantity reserved by unpaid orders.
- Cancelling or failing an unpaid order releases its reservations. Reopening a failed/cancelled order reserves stock again and fails if it is no longer available.
- A full refund of an unfulfilled order releases its reservation. A return after fulfillment does not automatically restock; staff must inspect the item before inventory is adjusted.

## Automatic expiry

Orders that remain `pending` for 24 hours are cancelled and their stock is released, provided they have no pending or verified payment. Orders with a submitted payment awaiting review are not expired. Partially paid orders are not auto-cancelled.

The command is:

```bash
php artisan orders:expire-unpaid --hours=24
```

It is scheduled daily at 03:00 through Laravel Scheduler. Ensure the cPanel scheduler cron is configured once per minute:

```cron
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Operational notes

- Courier integrations and live shipping-rate calculation are not enabled.
- Admin enters the carrier and tracking number and updates fulfillment through processing → packed → shipped → delivered → returned.
- Returned goods require physical inspection and a separate refund/inventory decision; the system deliberately does not auto-restock or auto-refund based only on a status change.
- Keep daily database and uploaded-file backups, and test restore before production launch.
