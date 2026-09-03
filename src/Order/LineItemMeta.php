<?php

declare(strict_types=1);

namespace Wicket\Finance\Order;

use Wicket\Finance\Product\FinanceMeta;
use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Logger;

/**
 * Order line item meta service.
 *
 * Manages finance meta on WooCommerce order line items:
 * - Auto-populates from product defaults
 * - Renders admin edit fields
 * - Validates dates
 * - Creates audit notes on changes
 *
 * Meta keys (per line item):
 * - _wicket_finance_start_date
 * - _wicket_finance_end_date
 * - _wicket_finance_gl_code
 *
 * @since 1.0.0
 */
class LineItemMeta
{
    /**
     * Product meta service.
     *
     * @var FinanceMeta
     */
    private $product_meta;

    /**
     * Date formatter.
     *
     * @var DateFormatter
     */
    private $date_formatter;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param FinanceMeta   $product_meta   Product meta service.
     * @param DateFormatter $date_formatter Date formatter.
     * @param Logger        $logger         Logger instance.
     */
    public function __construct(FinanceMeta $product_meta, DateFormatter $date_formatter, Logger $logger)
    {
        $this->product_meta = $product_meta;
        $this->date_formatter = $date_formatter;
        $this->logger = $logger;
    }

    /**
     * Initialize line item meta hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Auto-populate line item meta when order is created
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'populate_line_item_meta'], 10, 4);

        // Render admin fields for line items
        add_action('woocommerce_before_order_itemmeta', [$this, 'render_line_item_fields'], 10, 3);

        // Save line item meta
        add_action('woocommerce_before_save_order_items', [$this, 'save_line_item_meta'], 10, 2);
    }

    /**
     * Populates line item meta from product defaults.
     *
     * Called when order is created.
     *
     * @param \WC_Order_Item_Product $item          Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @param \WC_Order              $order         Order object.
     * @return void
     */
    public function populate_line_item_meta(\WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order): void
    {
        $product = $item->get_product();
        if (!$product) {
            return;
        }

        // Get GL code from product (one-time copy)
        $gl_code = $this->product_meta->get_gl_code($product);
        if (!empty($gl_code)) {
            $item->update_meta_data('_wicket_finance_gl_code', $gl_code);
        }

        // Get deferral dates from product
        $dates = $this->product_meta->get_deferral_dates($product);
        if (!empty($dates['start_date'])) {
            $item->update_meta_data('_wicket_finance_start_date', $dates['start_date']);
        }
        if (!empty($dates['end_date'])) {
            $item->update_meta_data('_wicket_finance_end_date', $dates['end_date']);
        }
    }

    /**
     * Renders line item fields in admin.
     *
     * $item_id follows the same rule as CustomerRenderer::render_on_order_items():
     * items on an unsaved order arrive keyed as strings, so the hint stays permissive.
     *
     * $item stays hinted as the base class: woocommerce_before_order_itemmeta also
     * fires for shipping, fee, and tax rows (admin order edit), which pass a
     * WC_Order_Item_Shipping etc. With a WC_Order_Item_Product hint and strict_types
     * that is an uncaught TypeError mid-render, fataling the whole order screen on
     * any order that ships (PACE flat rate, WWID-2383).
     *
     * @param int|string       $item_id Item ID, or the item's key on an unsaved order.
     * @param \WC_Order_Item   $item    Order item.
     * @param \WC_Product|null $product Product object, null for shipping/fee rows and deleted products.
     * @return void
     */
    public function render_line_item_fields(int|string $item_id, \WC_Order_Item $item, ?\WC_Product $product): void
    {
        if (!($item instanceof \WC_Order_Item_Product)) {
            return;
        }

        if (!$product) {
            return;
        }

        // Check if product has deferred revenue required
        if ($product->get_meta('_wicket_finance_deferred_required', true) !== 'yes') {
            // Check parent for variations
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                if (!$parent || $parent->get_meta('_wicket_finance_deferred_required', true) !== 'yes') {
                    return;
                }
            } else {
                return;
            }
        }

        $start_date = $item->get_meta('_wicket_finance_start_date', true);
        $end_date = $item->get_meta('_wicket_finance_end_date', true);
        $gl_code = $item->get_meta('_wicket_finance_gl_code', true);

        ?>
        <div class="wicket-finance-line-item-meta" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
            <h4><?php esc_html_e('Finance Fields', 'wicket-finance'); ?></h4>
            <p>
                <label>
                    <?php esc_html_e('GL Code:', 'wicket-finance'); ?>
                    <input type="text" name="wicket_finance_gl_code[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($gl_code); ?>" readonly style="background: #eee;">
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Term Start Date:', 'wicket-finance'); ?>
                    <input type="date" name="wicket_finance_start_date[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($start_date); ?>" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}">
                </label>
            </p>
            <p>
                <label>
                    <?php esc_html_e('Term End Date:', 'wicket-finance'); ?>
                    <input type="date" name="wicket_finance_end_date[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($end_date); ?>" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}">
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Saves line item meta and creates audit notes.
     *
     * @param int       $order_id Order ID.
     * @param array     $items    Items being saved.
     * @return void
     */
    public function save_line_item_meta(int $order_id, array $items): void
    {
        if (empty($_POST['wicket_finance_start_date']) && empty($_POST['wicket_finance_end_date'])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            if (!($item instanceof \WC_Order_Item_Product)) {
                continue;
            }

            $old_start = $item->get_meta('_wicket_finance_start_date', true);
            $old_end = $item->get_meta('_wicket_finance_end_date', true);

            $new_start = isset($_POST['wicket_finance_start_date'][$item_id])
                ? $this->date_formatter->sanitize_date_input(wp_unslash($_POST['wicket_finance_start_date'][$item_id]))
                : '';

            $new_end = isset($_POST['wicket_finance_end_date'][$item_id])
                ? $this->date_formatter->sanitize_date_input(wp_unslash($_POST['wicket_finance_end_date'][$item_id]))
                : '';

            // Validate date range
            if (!empty($new_start) && !empty($new_end)) {
                if (!$this->date_formatter->validate_date_range($new_start, $new_end)) {
                    wc_add_notice(__('Finance: End date must be greater than or equal to start date.', 'wicket-finance'), 'error');
                    continue;
                }
            }

            // Validate start date requires end date
            if (!empty($new_start) && empty($new_end)) {
                wc_add_notice(__('Finance: End date is required when start date is set.', 'wicket-finance'), 'error');
                continue;
            }

            // Track changes for audit note
            $changes = [];

            if ($old_start !== $new_start) {
                $item->update_meta_data('_wicket_finance_start_date', $new_start);
                $changes[] = sprintf(
                    'Term Start Date: %s → %s',
                    $old_start ?: 'empty',
                    $new_start ?: 'empty'
                );
            }

            if ($old_end !== $new_end) {
                $item->update_meta_data('_wicket_finance_end_date', $new_end);
                $changes[] = sprintf(
                    'Term End Date: %s → %s',
                    $old_end ?: 'empty',
                    $new_end ?: 'empty'
                );
            }

            // Enforce GL-code is always present
            $gl_code = $item->get_meta('_wicket_finance_gl_code', true);
            if (empty($gl_code)) {
                $product = $item->get_product();
                if ($product) {
                    $gl_code = $this->product_meta->get_gl_code($product);
                    if (!empty($gl_code)) {
                        $item->update_meta_data('_wicket_finance_gl_code', $gl_code);
                        $changes[] = 'GL Code: auto-populated from product';
                    }
                }
            }

            if (!empty($changes)) {
                $item->save();

                // Add audit note
                $user = wp_get_current_user();
                $user_name = $user->exists() ? $user->display_name : 'System';

                $note = sprintf(
                    '[%s] changed %s',
                    $user_name,
                    implode(', ', $changes)
                );

                $order->add_order_note($note);

                $this->logger->info('Line item finance meta updated', [
                    'order_id' => $order_id,
                    'item_id' => $item_id,
                    'changes' => $changes,
                    'user' => $user_name,
                ]);
            }
        }

        $order->save();
    }

    /**
     * Updates line item dates (for system/dynamic writes).
     *
     * @param int    $order_id   Order ID.
     * @param int    $item_id    Line item ID.
     * @param string $start_date Start date in Y-m-d format.
     * @param string $end_date   End date in Y-m-d format.
     * @param string $source     Source of update (default: 'System').
     * @return bool True on success, false on failure.
     */
    public function update_dates(int $order_id, int $item_id, string $start_date, string $end_date, string $source = 'System'): bool
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $item = $order->get_item($item_id);
        if (!$item) {
            return false;
        }

        $old_start = $item->get_meta('_wicket_finance_start_date', true);
        $old_end = $item->get_meta('_wicket_finance_end_date', true);

        $item->update_meta_data('_wicket_finance_start_date', $start_date);
        $item->update_meta_data('_wicket_finance_end_date', $end_date);
        $item->save();

        // Add audit note
        $note = sprintf(
            '[%s] changed Term Start Date: %s → %s, Term End Date: %s → %s',
            $source,
            $old_start ?: 'empty',
            $start_date,
            $old_end ?: 'empty',
            $end_date
        );

        $order->add_order_note($note);
        $order->save();

        $this->logger->info('Line item dates updated automatically', [
            'order_id' => $order_id,
            'item_id' => $item_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'source' => $source,
        ]);

        return true;
    }

    /**
     * Gets line item finance data.
     *
     * @param \WC_Order_Item_Product $item Line item.
     * @return array Array with start_date, end_date, gl_code.
     */
    public function get_line_item_data(\WC_Order_Item_Product $item): array
    {
        return [
            'start_date' => $item->get_meta('_wicket_finance_start_date', true),
            'end_date' => $item->get_meta('_wicket_finance_end_date', true),
            'gl_code' => $item->get_meta('_wicket_finance_gl_code', true),
        ];
    }
}
