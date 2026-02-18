# Project Overview

Wicket Financial Fields is a WordPress/WooCommerce plugin for finance mapping and deferred revenue tracking. It extends WooCommerce with GL code mapping, revenue deferral scheduling, and dynamic membership term date management.

**Dependencies:**
- WordPress 6.0+
- PHP 8.3+
- WooCommerce 10.0+
- Wicket Base Plugin (active)
- Wicket Memberships Plugin (active)

## Architecture

### Plugin Bootstrap
- Entry point: `wicket-wp-financial-fields.php`
- Singleton pattern: `Wicket\Finance\Plugin::get_instance()`
- Namespace: `Wicket\Finance\`

### Service Layer (src/)

**Settings/**: Configuration facade + Wicket Settings page integration
- `FinanceSettings`: Settings facade (getters, validation)
- `WPSettingsSettings`: Admin UI registration (hooks into Wicket Settings)

**Product/**: Product-level finance configuration
- `FinanceMeta`: Product data fields (GL code, deferred revenue flag, deferral dates)

**Order/**: Order line item management + membership integration
- `LineItemMeta`: Line item CRUD, validation, audit notes
- `DynamicDates`: Auto-writes membership term dates based on order status triggers

**Display/**: Customer-facing rendering
- `CustomerRenderer`: Displays term dates on emails, order confirmation, my account, subscriptions, PDFs

**Export/**: WooCommerce CSV export integration
- `WooExportAdapter`: Adds finance columns to exports

**Support/**: Shared utilities
- `Logger`: Wicket logging service (`wp-content/uploads/wicket-logs/`, source: `wicket-finance`)
- `DateFormatter`: Date format conversion (Y-m-d storage, ISO 8601 for memberships, locale display)
- `Eligibility`: Product/line item eligibility checks (membership detection, deferred revenue flag)
- `MembershipGateway`: Facade for Wicket Memberships plugin (date calculation, authoritative dates)

**helpers.php**: Global functions for settings access (`wicket_get_finance_option()`, trigger status arrays, category eligibility)

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

**Settings (stored in `wicket_settings` option):**
- `wicket_finance_enable_system` (default: '1')
- `wicket_finance_customer_visible_categories` (array of term IDs)
- `wicket_finance_display_order_confirmation`, `wicket_finance_display_emails`, etc.
- `wicket_finance_trigger_draft`, `wicket_finance_trigger_pending`, etc.

### Key Workflows

1. **Product Configuration**: Admin sets GL code + deferred flag at parent level. If deferred, dates appear (General tab for simple, variation panel for variable products).

2. **Order Creation**: Line items auto-populate with product defaults (GL code copied once, dates editable).

3. **Dynamic Dates**:
   - Triggers on order creation and status changes (configurable: Draft/Pending/On Hold/Processing/Completed)
   - Processing always triggers
   - Calculates dates via `Wicket_Memberships\Membership_Config::get_membership_dates()`
   - Membership creation overwrites line item dates with authoritative post meta

4. **Customer Display**: Term dates render when ALL conditions met:
   - Product in eligible category (settings)
   - Surface enabled in settings
   - Line item has both start and end dates

5. **Exports**: Finance columns added to WooCommerce CSV exports

## Development Commands

```bash
# Install dependencies (includes autoload generation)
composer install

# Run all tests (Pest PHP)
composer test

# Run unit tests only
composer test:unit

# Generate coverage report
composer test:coverage

# Run browser tests (requires https://localhost)
composer test:browser

# Check code style (PHP CS Fixer)
composer lint

# Fix code style automatically
composer format

# Run full check (lint + test)
composer check
```

## Important Notes

- **Date Formats**: Storage = `Y-m-d` (2024-01-15); Membership = ISO 8601; Display = `date_i18n()` with site locale/timezone
- **Variable Products**: Finance fields are parent-level only; deferral dates are per-variation
- **Validation**: End date >= Start date (blocks save with error)
- **Audit**: All line item date changes (manual or system) create order notes with user/timestamp
- **Logging**: Debug mode required for non-critical logs (WP_DEBUG or `wicket/finance/debug_enabled` filter)
- **HPOS**: Plugin declares compatibility with WooCommerce High-Performance Order Storage

## Testing

Uses Pest PHP testing framework. Tests located in `tests/` directory with namespace `Tests\` or `Wicket\Finance\Tests\`.

Brain/Monkey used for WP function mocking in unit tests.

## Hooks & Filters

**Filters:**
- `wicket/finance/membership_categories` - Extend membership category slugs (default: ['membership'])
- `wicket/finance/debug_enabled` - Enable debug logging

**Constants:**
- `WICKET_FINANCE_DEBUG` - Force debug logging on
