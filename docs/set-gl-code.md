---
title: "Set Up GL Code Mapping for Products"
audience: end-user
---

# Set Up GL Code Mapping for Products

Wicket Financial Fields lets you attach a GL code and deferred revenue settings to WooCommerce products. These settings flow into your accounting or ERP system when orders are exported.

## Before You Start

- WooCommerce must be installed and active
- Wicket Financial Fields plugin must be active
- Products must be configured as subscription products if you want dynamic revenue deferral

## Add Finance Settings to a Product

1. In the WordPress admin, go to **Products**.
2. Open the product you want to configure (or create a new one).
3. Click the **Finance Mapping** tab in the product data panel.
4. Fill in the fields:

| Field | Description |
|---|---|
| **GL Code** | The general ledger code this product maps to in your accounting system |
| **Deferred Revenue Required** | Toggle on if revenue from this product should be treated as deferred |
| **Deferral Start Date** | When the deferred revenue period begins (for simple products) |
| **Deferral End Date** | When the deferred revenue period ends (for simple products) |

## Variable Products

For variable subscription products, deferral dates are set **per variation**:

1. Go to the **Variations** tab of the variable product.
2. Open the variation you want to configure.
3. Expand the **Finance Mapping** section within that variation.
4. Set the GL code and deferral dates for that specific variation.

## Where Finance Data Appears

Once configured, finance data surfaces in:
- Order confirmation pages
- Customer account pages
- WooCommerce emails
- Subscription management pages
- PDF invoices (when a PDF invoice plugin is present)

You can control which surfaces show finance data in **WooCommerce → Settings → Wicket Finance**.
