# Wicket Financial Fields

Finance mapping and deferred revenue system for WooCommerce orders. Provides GL code mapping, revenue deferral dates, and dynamic membership term date population.

## Overview

This plugin extends WooCommerce with financial fields for revenue deferral tracking and reporting:

- **GL Code Mapping**: Associate products with General Ledger codes
- **Deferred Revenue**: Mark products requiring revenue deferral schedules
- **Deferral Dates**: Default term start/end dates at product level
- **Order Line Item Meta**: Store finance data with each order line item
- **Dynamic Dates**: Automatic population of membership term dates
- **Customer Display**: Configurable visibility of term dates on customer-facing surfaces
- **CSV Export**: Finance fields included in WooCommerce exports

## Requirements

- WordPress 6.0+
- PHP 8.3+
- WooCommerce 10.0+
- Wicket Base Plugin (active)
- Wicket Memberships Plugin (active)

## Installation

1. Upload the plugin files to `/wp-content/plugins/wicket-wp-financial-fields/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to Wicket > Finance to configure

## Features

### Product-Level Configuration

**Finance Mapping Tab** (all product types):
- GL Code field (parent level)
- Deferred revenue required checkbox (parent level)

**Deferral Dates**:
- Simple products: appear in General tab
- Variable products: per-variation settings
- Validation: end date must be >= start date

### Order Line Item Management

- Auto-populates from product defaults on order creation
- Admin edit fields in order line items
- Validation on save
- Audit notes track all changes (manual and system)
- GL code copied once (does not sync with product changes)

### Dynamic Membership Dates

Automatically writes term dates for membership products based on:
- Configured order status triggers (Draft, Pending, On Hold, Processing, Completed)
- Processing status always triggers (per specifications)
- Calculates dates via `Wicket_Memberships\Membership_Config::get_membership_dates()`
- Handles new purchases and renewals
- Overwrites with authoritative dates when membership is created

### Customer-Facing Display

Configurable visibility on:
- Order confirmation page
- Emails (8 types supported)
- My Account › Orders
- Subscriptions (if WooCommerce Subscriptions active)
- PDF invoices (via supported invoice plugins)

**Display Requirements** (all must be true):
- Product in eligible category (configured in settings)
- Surface enabled in settings
- Both start and end dates set on line item

**Labels**: "Term Start Date:" and "Term End Date:" (translatable)

### Exports

Finance fields available in WooCommerce CSV exports:
- GL Code
- Term Start Date (formatted for readability)
- Term End Date (formatted for readability)

## Configuration

### Settings (Wicket › Finance)

**Revenue Deferral Dates**

- Finance system is enabled by default on activation

*Customer Visibility:*
- Select product categories eligible for date display
- Choose surfaces where dates will be visible

*Dynamic Deferral Dates Trigger:*
- Order statuses that trigger automatic date population
- Processing is always enabled

### Status Notes

- Email rendering uses a single hook and should be verified across all 8 email types

### Product Configuration

1. Edit any WooCommerce product
2. Go to Product Data › Finance Mapping tab
3. Set GL Code and Deferred Revenue Required
4. If deferred:
   - Simple: set default dates in General tab
   - Variable: set dates per variation

### Order Management

Finance fields appear in order edit screen for line items where deferred revenue is required:
- GL Code (read-only, copied from product)
- Term Start Date (editable)
- Term End Date (editable)

All changes create audit notes with user and timestamp.

## Technical Details

### Data Model

**Product Meta:**
- `_wicket_finance_gl_code`
- `_wicket_finance_deferred_required`
- `_wicket_finance_deferral_start_date`
- `_wicket_finance_deferral_end_date`

**Order Line Item Meta:**
- `_wicket_finance_start_date`
- `_wicket_finance_end_date`
- `_wicket_finance_gl_code`

**Settings (stored in `wicket_settings`):**
- `wicket_finance_enable_system` (default on)
- `wicket_finance_customer_visible_categories`
- `wicket_finance_display_order_confirmation`
- `wicket_finance_display_emails`
- `wicket_finance_display_my_account`
- `wicket_finance_display_subscriptions`
- `wicket_finance_display_pdf_invoices`
- `wicket_finance_trigger_draft`
- `wicket_finance_trigger_pending`
- `wicket_finance_trigger_on_hold`
- `wicket_finance_trigger_processing`
- `wicket_finance_trigger_completed`

### Date Format

- **Storage**: `Y-m-d` (2024-01-15)
- **Display**: Site locale/timezone via `date_i18n()`
- **Membership Plugin**: ISO 8601 (converted internally)

### Hooks & Filters

**Filters:**
- `wicket/finance/membership_categories` - Extend membership category slugs (default: ['membership'])
- `wicket/finance/debug_enabled` - Enable debug logging

**Constants:**
- `WICKET_FINANCE_DEBUG` - Force debug logging on

### Logging

Logs stored in `wp-content/uploads/wicket-logs/`:
- Source: `wicket-finance`
- Levels: debug, info, warning, error, critical
- Debug mode required for non-critical logs (WP_DEBUG or filter)

## Development

### Directory Structure

```
wicket-wp-financial-fields/
├── src/
│   ├── Plugin.php                    # Bootstrap
│   ├── Settings/
│   │   ├── FinanceSettings.php       # Settings facade
│   │   └── WPSettingsSettings.php    # Admin UI
│   ├── helpers.php                   # Finance option helpers
│   ├── Product/
│   │   └── FinanceMeta.php           # Product fields
│   ├── Order/
│   │   ├── LineItemMeta.php          # Line item management
│   │   └── DynamicDates.php          # Membership integration
│   ├── Display/
│   │   └── CustomerRenderer.php      # Customer-facing display
│   ├── Export/
│   │   └── WooExportAdapter.php      # CSV export
│   └── Support/
│       ├── Logger.php                # Logging service
│       ├── DateFormatter.php         # Date utilities
│       ├── Eligibility.php           # Eligibility checks
│       └── MembershipGateway.php     # Membership facade
├── tests/                            # Pest tests
├── composer.json
└── wicket-wp-financial-fields.php    # Main plugin file
```

### Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run linter
composer lint

# Fix code style
composer format
```

### ⚠️ IMPORTANT: Before Tagging a New Version

**Always run `composer production` before tagging a new version.** This command:
- Removes development dependencies
- Optimizes autoloader for production
- Generates a clean build without dev packages

```bash
composer production
```

Without this step, the plugin will include unnecessary dev dependencies in the release.

### Testing

Uses **PEST** and **PHPUnit** testing frameworks:

```bash
# Run all tests
composer test

# Unit tests only
composer test:unit

# Coverage report
composer test:coverage

# Browser tests
composer test:browser
```

### Available Composer Scripts

```bash
composer production       # Build for production (remove dev deps, optimize autoload)
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:coverage   # Run tests with HTML coverage report
composer test:browser    # Run browser tests
composer lint            # Check code style
composer format          # Fix code style
composer check           # Run lint + test
```

## Support

For issues, questions, or feature requests, contact Wicket Inc.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Product-level finance mapping and deferral configuration
- Order line item meta management
- Dynamic membership date population
- Customer-facing display with configurable visibility
- CSV export integration
