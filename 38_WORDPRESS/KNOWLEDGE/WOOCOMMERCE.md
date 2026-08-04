Status: Stable

---
# SquirrelForge WooCommerce Extension Knowledge

## Purpose

Defines knowledge for extending WooCommerce: hooks, product data, checkout flow, order lifecycle, and compatibility with WooCommerce core and HPOS.

## Review Areas

Review WooCommerce hook usage, product and order data access patterns, checkout and cart modifications, order status transitions, payment and shipping integration points, and High-Performance Order Storage (HPOS) compatibility.

## Output

This Knowledge file must support:

- WooCommerce extension review notes;
- compatibility risk classification against WooCommerce core and HPOS;
- checkout and order-flow correctness review;
- data-access pattern recommendations;
- and WooCommerce validation handoff.

## Validation Requirements

WooCommerce guidance is valid only when:

- product, order, and customer data are accessed through WooCommerce's CRUD objects and APIs (e.g. `WC_Product`, `WC_Order`) rather than direct queries against WooCommerce-managed tables;
- code declares and respects HPOS (custom order tables) compatibility rather than assuming legacy post-based order storage;
- checkout and cart modifications preserve required validation, totals recalculation, and nonce/capability checks;
- order status transitions use WooCommerce's status-change APIs and hooks rather than direct database writes;
- payment and shipping integrations follow WooCommerce's gateway and shipping-method extension points rather than bypassing them;
- and extensions declare compatibility with the WooCommerce and WordPress versions they target.

## Handoff Rules

- Product, order, and checkout implementation issues route to the relevant `38_WORDPRESS/ROLES/PHP-ENGINEER.md` implementation owner.
- Direct database access against WooCommerce-managed data routes to `38_WORDPRESS/ROLES/DATABASE-ENGINEER.md`.
- Security-sensitive payment, checkout, or order data handling routes to `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`.
- Performance concerns from order or product queries route to `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when WooCommerce extension work can be reviewed for API usage, HPOS compatibility, checkout/order correctness, and integration-point safety.

## Rule

WooCommerce data must be accessed through WooCommerce's CRUD objects and APIs, and extensions must declare and preserve HPOS compatibility.
