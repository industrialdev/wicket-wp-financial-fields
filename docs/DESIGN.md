# DESIGN.md

## Architecture Overview

The Wicket Financial Fields plugin follows a **domain-driven design** pattern with clear separation of concerns. The architecture is organized into layers:

1. **Domain Layer**: Core business logic (Product, Order, Display)
2. **Infrastructure Layer**: Cross-cutting concerns (Logger, DateFormatter, Eligibility)
3. **Integration Layer**: External system adapters (Export, MembershipGateway, WPSettings)
4. **Presentation Layer**: Settings UI and customer-facing rendering

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Plugin.php                           │
│                    (Singleton Coordinator)                   │
│  - Initializes services                                      │
│  - Manages lifecycle                                         │
│  - Registers WordPress hooks                                 │
└────────┬────────────────────────────────────────────────────┘
         │
         ├──> Settings Layer
         │    ├── FinanceSettings (Typed Facade)
         │    └── WPSettingsSettings (UI)
         │
         ├──> Support Services
         │    ├── Logger (Audit trail)
         │    ├── DateFormatter (Validation/Format)
         │    ├── Eligibility (Category checks)
         │    └── MembershipGateway (Integration)
         │
         ├──> Domain Services
         │    ├── Product/FinanceMeta (Product config)
         │    ├── Order/LineItemMeta (Order item meta)
         │    ├── Order/DynamicDates (Membership calc)
         │    └── Display/CustomerRenderer (Display logic)
         │
         └──> Integration
              ├── Export/WooExportAdapter (CSV export)
              └── WordPress Hooks (Events)
```

## Design Patterns

### 1. Singleton Pattern

**Location**: `Plugin.php`

**Purpose**: Single point of coordination for all services

```php
$instance = Plugin::get_instance();
```

**Rationale**:
- Prevents duplicate hook registration
- Centralized dependency injection
- Single source of truth for service instances

### 2. Facade Pattern

**Location**: `Settings\FinanceSettings`

**Purpose**: Simplified interface to settings operations

```php
$enabled = FinanceSettings::is_enabled();
$categories = FinanceSettings::get_visible_categories();
```

**Rationale**:
- Hides raw option access behind typed methods
- Provides default values in one place
- Easy to mock for testing

### 3. Gateway Pattern

**Location**: `Support\MembershipGateway`

**Purpose**: Abstraction over Wicket Memberships plugin

```php
$gateway = MembershipGatewayFactory::create();
$dates = $gateway->get_membership_dates($order_id, $item_id);
```

**Rationale**:
- Decouples plugin from membership API changes
- Allows graceful degradation if plugin inactive
- Enables testing with mock gateway

### 4. Service Locator Pattern

**Location**: `Plugin::plugin_setup()`

**Purpose**: Centralized service instantiation

**Rationale**:
- Explicit dependency order
- Single initialization point
- Easy to trace service creation

### 5. Strategy Pattern

**Location**: `Order\DynamicDates`

**Purpose**: Different date calculation strategies

**Strategies**:
- New purchase: Calculate from order date
- Renewal: Calculate from existing membership end date
- Membership creation: Use authoritative membership post meta

## Data Model

### Product Meta Keys

Stored in `wp_postmeta` for products:

| Key | Type | Purpose |
|-----|------|---------|
| `_wicket_finance_gl_code` | string | Financial system GL code |
| `_wicket_finance_deferred_required` | bool | Requires deferral dates |
| `_wicket_finance_deferral_start_date` | string (Y-m-d) | Default start date |
| `_wicket_finance_deferral_end_date` | string (Y-m-d) | Default end date |

### Order Item Meta Keys

Stored in `wp_woocommerce_order_itemmeta`:

| Key | Type | Purpose |
|-----|------|---------|
| `_wicket_finance_gl_code` | string | GL code (copied from product) |
| `_wicket_finance_start_date` | string (Y-m-d) | Actual term start date |
| `_wicket_finance_end_date` | string (Y-m-d) | Actual term end date |

**Invariants**:
- GL code copied once at order creation (no sync)
- Dates auto-populated from product defaults
- Dates overwritable by admin or dynamic triggers
- Validation: If start exists, end required and >= start

### WordPress Options

Stored in `wp_options` under `wicket_settings` via WPSettings (Wicket Base Plugin):

| Key | Type | Purpose |
|-----|------|---------|
| `wicket_finance_enable_system` | bool | Master feature toggle (default on) |
| `wicket_finance_customer_visible_categories` | int[] | Eligible product category IDs |
| `wicket_finance_display_order_confirmation` | bool | Show on order confirmation |
| `wicket_finance_display_emails` | bool | Show in email notifications |
| `wicket_finance_display_my_account` | bool | Show in My Account orders |
| `wicket_finance_display_subscriptions` | bool | Show in subscriptions details |
| `wicket_finance_display_pdf_invoices` | bool | Show in PDF invoices |
| `wicket_finance_trigger_draft` | bool | Trigger on draft |
| `wicket_finance_trigger_pending` | bool | Trigger on pending payment |
| `wicket_finance_trigger_on_hold` | bool | Trigger on on-hold |
| `wicket_finance_trigger_processing` | bool | Trigger on processing (forced on) |
| `wicket_finance_trigger_completed` | bool | Trigger on completed |

## Service Layer Architecture

### Core Services

#### Plugin (Coordinator)

**Responsibilities**:
- Singleton instance management
- Service initialization (dependency injection)
- WordPress hook coordination
- Plugin lifecycle (activation/deactivation)

**Dependencies**: None (root of tree)

**Key Methods**:
```php
Plugin::get_instance(): Plugin
Plugin::plugin_setup(): void
Plugin::get_logger(): Logger
Plugin::get_settings(): FinanceSettings
```

#### FinanceSettings (Facade)

**Responsibilities**:
- Typed access to configuration options
- Default value management
- Settings validation

**Dependencies**: None

**Key Methods**:
```php
FinanceSettings::is_enabled(): bool
FinanceSettings::get_visible_categories(): array
FinanceSettings::is_surface_enabled(string $surface): bool
FinanceSettings::get_trigger_statuses(): array
```

#### WPSettingsSettings (UI)

**Responsibilities**:
- Settings page registration
- Form field definition
- Settings persistence

**Dependencies**: FinanceSettings, Logger, WPSettings

**Registration**:
```php
add_action('init', [$this, 'register_finance_settings_page']);
add_action('admin_menu', [$this, 'register_finance_submenu'], 999);
```

### Domain Services

#### Product\FinanceMeta

**Responsibilities**:
- Product data tab registration
- Field rendering (GL code, deferred checkbox, dates)
- Validation (date range, required fields)
- Meta persistence

**Dependencies**: Logger, Eligibility

**Hooks**:
```php
woocommerce_product_data_tabs
woocommerce_product_data_panels
woocommerce_process_product_meta
woocommerce_admin_process_product_object
```

**Data Flow**:
```
Admin Input → Validate → Save Post Meta
           ↓
      Variation Inheritance
```

#### Order\LineItemMeta

**Responsibilities**:
- Line item field rendering (admin order edit)
- Auto-population from product defaults
- Validation (date range, required fields)
- Audit note generation
- Meta persistence

**Dependencies**: Logger, DateFormatter, Eligibility

**Hooks**:
```php
woocommerce_before_order_itemmeta
woocommerce_save_order_item
woocommerce_new_order_item
```

**Data Flow**:
```
Order Created → Copy Product Meta → Line Item Meta
                    ↓
Admin Edit → Validate → Update Meta + Audit Note
```

**Audit Note Format**:
```
[System] changed Term Start Date: 2024-01-01 → 2024-02-01
[John Doe] changed Term End Date: 2024-12-31 → 2025-01-15
```

#### Order\DynamicDates

**Responsibilities**:
- Status trigger detection
- Membership date calculation
- Line item meta updates
- Audit note generation
- Membership event handling

**Dependencies**: Logger, MembershipGateway, Eligibility

**Hooks**:
```php
woocommerce_order_status_changed
woocommerce_new_order
wicket_member_create_record
```

**Trigger Logic**:
```php
if (in_array($new_status, $enabled_statuses)) {
    foreach ($order_items as $item) {
        if ($this->is_membership_product($item)) {
            $dates = $membership_gateway->get_membership_dates(...);
            $this->update_line_item_dates($item, $dates);
        }
    }
}
```

**Priority Rules**:
1. Draft/Pending/On Hold: Calculate tentative dates
2. Processing: Always triggers; uses authoritative membership meta
3. Membership creation/updates: Overwrites with membership post meta
4. Later statuses: Respect existing dates unless re-triggered

### Display Services

#### Display\CustomerRenderer

**Responsibilities**:
- Eligibility determination
- Surface-specific rendering
- Localized date formatting
- Label management

**Dependencies**: Logger, DateFormatter, Eligibility, FinanceSettings

**Hooks**:
```php
woocommerce_order_item_meta_end
wpo_wcpdf_after_item_meta
```

**Context detection**:
- Email surface via `did_action('woocommerce_email_order_details')`
- Subscription surface via `wcs_is_subscription` + account view

**Eligibility Matrix**:
```
Display IF:
  ✓ Product in visible category
  ✓ Surface enabled in settings
  ✓ Start date AND end date set
```

**Output Format**:
```html
<p>
  <strong>Term Start Date:</strong> Jan 1, 2024
</p>
<p>
  <strong>Term End Date:</strong> Dec 31, 2024
</p>
```

### Support Services

#### Support\DateFormatter

**Responsibilities**:
- Date validation (Y-m-d format, range checks)
- Format conversion (ISO 8601 → Y-m-d)
- Localized display formatting
- Timezone handling

**Dependencies**: None

**Key Methods**:
```php
DateFormatter::is_valid_date(string $date): bool
DateFormatter::validate_range(string $start, string $end): bool
DateFormatter::format_display(string $date): string
DateFormatter::iso_to_ymd(string $iso_date): string
```

**Storage Format**: `Y-m-d` (UTC)
**Display Format**: Site locale + timezone

#### Support\Eligibility

**Responsibilities**:
- Category membership checks
- Variation parent category resolution
- Membership product detection
- Display eligibility determination

**Dependencies**: FinanceSettings (for category config)

**Key Methods**:
```php
Eligibility::product_in_visible_category(int $product_id): bool
Eligibility::is_membership_product(int $product_id): bool
Eligibility::is_display_eligible(WC_Order_Item $item): bool
```

**Membership Categories**:
- Default: `['membership']`
- Filter: `wicket/finance/membership_categories`
- Cached per request for performance

#### Support\MembershipGateway

**Responsibilities**:
- Wicket Memberships plugin abstraction
- Membership date calculation
- Graceful degradation if plugin inactive

**Dependencies**: Wicket Memberships plugin (optional)

**Key Methods**:
```php
MembershipGateway::get_membership_dates(
    int $order_id,
    int $item_id,
    array $existing_membership = []
): array
```

**Return Format**:
```php
[
    'start_date' => '2024-01-01',  // ISO 8601 from API
    'end_date' => '2024-12-31',
]
```

**Factory Pattern**:
```php
$gateway = MembershipGatewayFactory::create();
// Returns RealGateway if plugin active
// Returns NoOpGateway if plugin inactive
```

#### Support\Logger

**Responsibilities**:
- Daily log rotation
- Log level management (debug, info, warn, error)
- Contextual logging
- Performance tracking

**Dependencies**: None

**Log Location**:
```
wp-content/uploads/wicket-logs/
└── wicket-finance-{Y-m-d}.log
```

**Usage**:
```php
$this->logger->info('Message', ['context' => 'data']);
$this->logger->error('Error', ['exception' => $e]);
```

**Debug Toggle**:
- Setting: `wicket_finance_debug_mode`
- Constant: `WICKET_FINANCE_DEBUG` (overrides)

### Integration Services

#### Export\WooExportAdapter

**Responsibilities**:
- CSV column registration
- Export data formatting
- Date localization for export

**Dependencies**: DateFormatter

**Hooks**:
```php
woocommerce_report_export_prepare_columns (filter)
woocommerce_report_export_prepare_row_item (filter)
```

**Export Columns**:
```php
[
    'gl_code' => 'GL Code',
    'term_start_date' => 'Term Start Date',
    'term_end_date' => 'Term End Date',
]
```

## Data Flow Diagrams

### Product Configuration Flow

```
┌─────────────┐
│ Admin User  │
└──────┬──────┘
       │
       ├──> Edit Product
       │    │
       │    ├──> Finance Mapping Tab
       │    │    ├──> GL Code Input
       │    │    └──> Deferred Required Checkbox
       │    │
       │    └──> General Tab (if deferred)
       │         ├──> Start Date Picker
       │         └──> End Date Picker
       │
       ├──> Save Product
       │    │
       │    ├──> FinanceMeta::validate_product_data()
       │    │    ├──> Date range check
       │    │    └──> Required field check
       │    │
       │    └──> Update Post Meta
       │         ├──> _wicket_finance_gl_code
       │         ├──> _wicket_finance_deferred_required
       │         ├──> _wicket_finance_deferral_start_date
       │         └──> _wicket_finance_deferral_end_date
       │
       └──> Success/Error Notice
```

### Order Creation Flow

```
┌─────────────┐
│ Customer    │
└──────┬──────┘
       │
       └──> Checkout
            │
            ├──> Order Created
            │    │
            │    ├──> LineItemMeta::populate_line_item_meta()
            │    │    ├──> Get product defaults
            │    │    ├──> Copy GL code
            │    │    ├──> Copy start date (if set)
            │    │    └──> Copy end date (if set)
            │    │
            │    └──> Save Line Item Meta
            │         ├──> _wicket_finance_gl_code
            │         ├──> _wicket_finance_start_date
            │         └──> _wicket_finance_end_date
            │
            └──> Order Status Set
                 │
                 └──> DynamicDates::maybe_write_membership_dates()
                      ├──> Check status in triggers
                      ├──> Check product membership category
                      ├──> Calculate dates via MembershipGateway
                      └──> Update line item meta + audit note
```

### Dynamic Date Calculation Flow

```
┌──────────────────┐
│ Order Status     │
│   Changed        │
└────────┬─────────┘
         │
         ├──> Draft/Pending/On Hold
         │    │
         │    ├──> MembershipGateway::get_membership_dates()
         │    │    ├──> Calculate from order date
         │    │    └──> Return tentative dates
         │    │
         │    └──> Update line item meta
         │         └──> Add audit note
         │
         ├──> Processing (ALWAYS triggers)
         │    │
         │    ├──> MembershipGateway::get_membership_dates()
         │    │    ├──> Get membership post meta (authoritative)
         │    │    └──> Return actual dates
         │    │
         │    └──> Update line item meta
         │         └──> Add audit note
         │
         └──> Membership Created/Updated
              │
              ├──> Get membership post meta
              │    ├──> membership_starts_at
              │    └──> membership_ends_at
              │
              └──> Overwrite line item meta
                   └──> Add audit note
```

### Customer Display Flow

```
┌──────────────────┐
│ Surface Render   │
│ (Email/View/etc) │
└────────┬─────────┘
         │
         ├──> CustomerRenderer::render_line_item_dates()
         │    │
         │    ├──> Eligibility::is_display_eligible()
         │    │    ├──> Product in visible category?
         │    │    ├──> Surface enabled in settings?
         │    │    └── Both dates set?
         │    │
         │    └──> IF eligible:
         │         ├──> Format dates for display
         │         └──> Output HTML
         │
         └──> Output (or skip if not eligible)
```

## WordPress Hooks Integration

### Action Hooks (Registration)

**Product Level**:
```php
add_action('woocommerce_product_data_tabs', [$this, 'add_finance_tab']);
add_action('woocommerce_product_data_panels', [$this, 'render_finance_fields']);
add_action('woocommerce_process_product_meta_simple', [$this, 'save_product_meta']);
add_action('woocommerce_process_product_meta_variable', [$this, 'save_product_meta']);
add_action('woocommerce_admin_process_product_object', [$this, 'validate_product_data']);
```

**Order Level**:
```php
add_action('woocommerce_new_order_item', [$this, 'populate_line_item_meta'], 10, 3);
add_action('woocommerce_save_order_item', [$this, 'save_line_item_meta'], 10, 2);
add_action('woocommerce_before_order_itemmeta', [$this, 'render_line_item_fields'], 10, 3);
```

**Dynamic Dates**:
```php
add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 3);
add_action('woocommerce_new_order', [$this, 'on_order_created'], 10, 2);
add_action('wicket_member_create_record', [$this, 'on_membership_created'], 10, 3);
```

**Customer Display**:
```php
add_action('woocommerce_order_item_meta_end', [$this, 'render_on_order_items'], 10, 3);
add_action('wpo_wcpdf_after_item_meta', [$this, 'render_on_pdf_invoice'], 10, 3);
```

**Export**:
```php
add_filter('woocommerce_report_export_prepare_columns', [$this, 'add_export_columns'], 10, 2);
add_filter('woocommerce_report_export_prepare_row_item', [$this, 'add_export_data'], 10, 5);
```

### Filter Hooks (Public API)

**Extensibility Filters**:
```php
// Membership category configuration
apply_filters('wicket/finance/membership_categories', ['membership']);

// Eligibility checks
apply_filters('wicket/finance/eligible_categories', $categories, $product_id);
apply_filters('wicket/finance/is_eligible_for_display', $eligible, $item, $surface);

// Date calculation
apply_filters('wicket/finance/membership_dates', $dates, $order_id, $item_id);

// Export data
apply_filters('wicket/finance/export_data', $export_data, $item, $product);
```

## Dependency Injection

### Initialization Order

```php
1. Logger                          // No deps
2. FinanceSettings                 // No deps
3. DateFormatter                   // No deps
4. MembershipGateway (Factory)     // No deps
5. Eligibility                     // Dep: FinanceSettings
6. WPSettingsSettings              // Dep: FinanceSettings, Logger
7. Product\FinanceMeta             // Dep: Logger, Eligibility
8. Order\LineItemMeta              // Dep: Logger, DateFormatter, Eligibility
9. Order\DynamicDates              // Dep: Logger, MembershipGateway, Eligibility
10. Display\CustomerRenderer       // Dep: Logger, DateFormatter, Eligibility, FinanceSettings
11. Export\WooExportAdapter        // Dep: DateFormatter
```

### Service Resolution

```php
class Plugin {
    private Logger $logger;
    private FinanceSettings $settings;
    // ... other services

    public function get_logger(): Logger {
        return $this->logger;
    }

    public function get_settings(): FinanceSettings {
        return $this->settings;
    }
}
```

## Performance Considerations

### Optimization Strategies

1. **Lazy Loading**: Services only instantiate when needed
2. **Query Caching**: Membership categories cached per request
3. **Conditional Hooks**: Display hooks only registered if enabled
4. **Database Indexes**: Post meta keys indexed by WordPress

### Bottlenecks to Avoid

1. **N+1 Queries**: Bulk operations for export
2. **Redundant Checks**: Early returns in eligibility
3. **Heavy Calculations**: Membership date calc cached by membership ID

## Security Considerations

### Input Validation

- All dates validated before storage
- GL codes sanitized (text field)
- Category IDs cast to integers
- Nonce verification on all forms

### Capability Checks

- Settings: `manage_options`
- Product edit: Inherited from WooCommerce
- Order edit: Inherited from WooCommerce

### Audit Trail

- All meta changes logged to order notes
- User attribution (admin name or "System")
- Timestamp on each change
- Before/after values recorded

## Testing Strategy

### Unit Tests (Pending)

**Target Classes**:
- DateFormatter
- Eligibility
- FinanceSettings
- Validation logic

### Integration Tests (Pending)

**Scenarios**:
- Product creation with finance meta
- Order creation with meta population
- Status trigger execution
- Export data generation

### Browser Tests (Pending)

**Scenarios**:
- Admin product data tab
- Admin order item fields
- Settings page form submission
- Customer display surfaces

## Future Considerations

### Potential Enhancements

1. **Settings UI**: Add AJAX-powered category filtering
2. **Export**: Add custom date range selector
3. **Display**: Add admin preview mode
4. **Validation**: Client-side JavaScript validation
5. **Caching**: Cache membership dates per order
6. **API**: REST API endpoints for finance data

### Migration Path

If breaking changes needed:
1. Add migration class
2. Version settings option
3. Run migrations on plugin update
4. Preserve data integrity
5. Rollback capability

## References

### Dependencies

- **WooCommerce**: Core commerce platform
- **Wicket Memberships**: Membership management
- **WPSettings (Wicket Base Plugin)**: Settings UI framework
- **WordPress 6.0+**: Core CMS

### Related Plugins

- `wicket-wp-base-plugin`: Foundation plugin
- `wicket-wp-guest-checkout`: Tooling baseline
- `wicket-wp-memberships`: Membership integration
- `woocommerce-pdf-invoices-packing-slips`: Invoice export

### Documentation

- SPECS.md: Feature specifications
- README.md: User-facing documentation
- TICKET.md: Original requirements ticket
