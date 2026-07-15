---
title: "Wicket Financial Fields Overview"
audience: [implementer, support]
wp_admin_path: "Wicket → Settings → Finance"
php_class: FinanceSettings
db_option_prefix: wicket_finance_
source_files: ["src/Settings/FinanceSettings.php", "src/Settings/WPSettingsSettings.php"]
---

# Overview

Wicket Financial Fields maps WooCommerce orders to GL codes and handles deferred revenue recognition for membership products. It works with WooCommerce Subscriptions to populate dynamic revenue deferral dates on subscription orders.

## What It Does

- Maps WooCommerce products and orders to GL codes for accounting systems
- Calculates and attaches deferred revenue start/end dates on order line items for membership products
- Supports WooCommerce Subscriptions — calculates dynamic revenue deferral dates based on subscription term length
- Provides a WooCommerce export adapter that includes finance data on standard WC export fields
- Displays finance data on the order confirmation page and in customer account pages

## Requirements

- WordPress 6.0+
- PHP 8.3+
- WooCommerce 10.0+
- `wicket-wp-base-plugin`
- `wicket-wp-memberships`

## Settings

Settings are stored with the `wicket_finance_` prefix. The main settings page is in **Wicket → Settings → Finance**.

### Key Settings

| Setting | Description |
|---|---|
| **System Enabled** | Master toggle for the entire finance system |
| **Customer Visible Categories** | Which product categories show finance info to customers |
| **Display Surfaces** | Where to show finance data: order confirmation, emails, my account, subscriptions, PDF invoice |

### Display Surfaces

Finance data can be surfaced in:
- `order_confirmation` — Order confirmation page
- `emails` — WooCommerce order emails
- `my_account` — Customer account pages
- `subscriptions` — Subscription confirmation and management pages
- `pdf_invoice` — (when PDF invoice plugin is present)

## Documentation Links

