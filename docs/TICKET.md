! Productize Finance Mapping & Deferral Dates (Base Plugin)
# Implement Generalized Finance Mapping and Deferred Revenue

Implement a generalized version of the finance mapping and deferred revenue functionality in the Wicket Base Plugin. This will allow admins to configure product-level finance data, set default deferral dates, and control if/where dates appear for customers. The solution should also support dynamic population of membership dates, exports, and customer-facing visibility options.

### Notes:

* The ASAE environment already includes a custom implementation of some/most functionality. That environment should be used as a reference point for the productized version.
* This task acts as the umbrella for all subtasks covering:
    * Product configuration (Finance Mapping tab & deferral defaults)
    * Order line item meta & validations
    * Dynamic write of membership dates
    * Customer-facing display rules

### Acceptance:

* All subtasks implemented and tested.
* Functionality uses ASAE reference but is configurable and reusable across all Wicket web product clients.

! Base Plugin Config Options
As an admin, I can set if/where deferral dates appear as customer facing

### Acceptance Criteria
* A 'Finance' tab is available in Wicket Settings in the base plugin
* Under a heading 'Revenue Deferral Dates' there will be configurable options for
    * Subheading - Customer Visibility
        * Product categories eligible for displaying deferral dates to customers
            * Label: Select product categories that should display deferral dates to customers
            * Multi-select from native Woo Product Categories taxonomy
        * Where deferral dates will be visible
            * Label: Choose where deferral dates will be visible to customers
            * Checkboxes:
                * Order confirmation page
                * Emails (as applicable based on dynamic deferral date config): Pending payment, On hold, Processing, Completed, Renewal
                * My Account > Orders (order details)
                * Subscriptions (details)
                * PDF invoices (via supported invoice plugin)

### Expected Behaviour:
* Dates display only when all of the following are true:
    a. The line item's product (or its parent for variations) is in at least one of the selected categories.
    b. The surface is checked in Where deferral dates will be visible.
    c. The line item has both a valid Start date and End date (Y-m-d stored; localized format displayed).
* Displayed labels are exactly "Start date:" and "End date:" (translatable).
* For variable products, eligibility is determined by the parent product's categories.
* If a product is not in any selected category, no dates are shown to customers on any surface, regardless of line-item meta.
* Specific display
    * Order confirmation page: Dates render under each eligible line item.
    * Emails: Dates render under each eligible line item for the relevant email types.
        * ie: If dates exist, they are shown. This will be impacted by dynamic deferral date trigger in some cases
    * My Account > Orders (order details): Dates render for each eligible line item on the order details view.
    * Subscriptions (details): If Woo Subscriptions is active, dates render for eligible subscription line items.
    * PDF invoices: If a supported invoice plugin is active and option is checked, dates render beneath each eligible line item.

! Product Configuration Fields
As an admin
I want a Finance Mapping tab within "Product data"
so that I can capture product-level finance meta used by orders/exports.

### Acceptance Criteria
* Applies to all product types (Simple, Variable, Subscriptions, etc; for Variable, tab at parent level).
* Fields (parent-level, all product types):
    * GL Code (text) with helper text.
        * Field type: Single line text
        * Help Text: GL mapping from your financial management system.
    * Deferred revenue required
        * Field type: Checkbox
        * Help Text: Select if this product will use a deferred revenue schedule in your financial management system.
* Save persists meta
* For Variable products: fields above appear at the parent level only (not duplicated per variation).

### Setting Deferral Dates
* When Deferred revenue required = checked:
    * Simple product: Start date and End date appear in the General tab.
    * Variable product: Start date and End date appear per variation (in variation details).
    * Field Type = date picker
* Validation (at product form):
    * If Start date is set, End date is required.
    * End date >= Start date.
* On save, valid dates persist; invalid blocks save with clear error.
* These dates are defaults only; they will auto-populate line items but remain editable at the order level.

! Order Line item Data
As an admin
I want Start/End date inputs on order line items for products that require deferral
so that finance can set or adjust the revenue schedule.

### Acceptance Criteria
* When an order line item is for a product with Deferred revenue required:
    * Show Start date and End date inputs on the line item (Admin > Edit Order).
    * When the product had product-level default dates, these auto-populate the line item.
    * Each line item instance is independently editable, even for repeated products.
* Validation (on update/save order):
    * If Start date exists, End date is required.
    * End date >= Start date.
* Meta keys are saved per line item (proposed keys):
    * _wicket_finance_start_date (Y-m-d)
    * _wicket_finance_end_date (Y-m-d)
    * _wicket_finance_gl_code (string, if present on product)
* When meta changes (including system writes), add an Order Note: who changed it, old -> new values, timestamp.

### Note:
* Line item meta must be available in Woo exports

! Dynamic Deferral Dates - Membership
As an admin
I can configure when dynamic deferral dates are written to membership line items on orders

### Acceptance Criteria
* A 'Finance' tab is available in Wicket Settings in the base plugin
* Under a heading 'Revenue Deferral Dates' there will be configurable options for:
    * Dynamic Deferral Dates Trigger
        * Help text: Determines the Woo order status that triggers dynamic deferral dates to be written. Regardless of this setting, dates will always be written when the order reaches 'Processing' status.
        * Checkboxes for:
            * Draft
            * Pending payment
            * On hold
            * Processing (default)
            * Completed

---

As an admin
I want the system to automatically write membership term dates into membership line items based on a configurable trigger
so that finance dates match the actual membership period.

### Acceptance Criteria
* Start and end dates are written to membership line item when the order reaches status defined in 'Dynamic Deferral Dates Trigger' config
    * Dates will be calculated based on logic within the membership plugin
    * If order status trigger defined as Draft, Pending Payment, or On-Hold membership record will not yet exist so dates must be set by this function
* Upon order processing, when the membership is created/updated, the system overwrites line-item dates with membership dates.
* Admins can manually modify and save dynamic dates populated via system automation
    * Dates may be overwritten by dynamic dates on subsequent order status change (determined by config)
* Dates are stored as Y-m-d and displayed using the store/site locale and timezone (inherit Woo/Base plugin).

### Notes:
* The membership plugin uses specific functions to look at the relevant config to calculate the dates
* There are rules based on whether the membership is new or a renewal
* This function from the membership plugin needs to be re-used/extended to calculate the dates based on the order status change

! Add status triggers to on_create of an order
The objective is to adjust the logic so that the status triggers (defined here) also occur on order creation, rather than just status editing. So an order being created with a status of pending payment should trigger the same logic as setting the status to pending payment.

! [UAT] Adjustments to deferral dates display
Adjust the way deferral dates are being displayed.
* Remove deferral dates box
* Include only meta below line items
* Adjust labels from 'Finance Start Date' and 'Finance End Date' to 'Term Start Date:' and 'Term End Date:'

Adjustments to be made:
* Emails
    * ie: If dates exist, they are shown. This will be impacted by dynamic deferral date trigger in some cases Dynamic Deferral Dates - Membership
* My Account > Orders (order details)
* PDF invoices
