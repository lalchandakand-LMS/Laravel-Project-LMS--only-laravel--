# Flash Sales & Discount Campaigns

## Admin workflow

Open Admin → Commerce → Flash Sales. Administrators can create, edit, enable, disable, archive and restore campaigns.

Each campaign includes:
- Percentage discount (0.01–100%) or fixed BDT reduction.
- Required start and end date/time; end must be later than start.
- Multiple course targets, category targets, or both. At least one target is required.
- Optional description and an explicit enabled/disabled switch.

A campaign only affects published course prices while it is enabled and the current application time is within its start/end window. Category targeting matches both a course's primary category and its additional assigned categories.

## Price calculation

The permanent course discount and the best eligible active flash-sale price are compared; the lower price is used. They are not added together, so a sale cannot accidentally compound a pre-existing course discount. Fixed reductions are capped at the course's original price and never create a negative price.

The same effective course price is used in course cards, course details, cart totals and checkout order items. Checkout recalculates the cart at order creation, and the resulting order-item price is stored as a snapshot. Coupon codes remain a separate cart-level discount and are calculated after item prices.

## Data and deployment

The additive migration creates flash_sales, flash_sale_courses and flash_sale_categories. Existing course prices, coupons, orders and payments are not rewritten. Campaigns use soft deletion and can be restored from the existing Recycle Bin.

After deploying the code to the production server, back up the database and run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

No production deployment or migration is performed by this change.