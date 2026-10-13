<?php

declare(strict_types=1);

namespace Wicket\Finance\Product;

use Wicket\Finance\Support\Logger;

/**
 * Redirects writes of site-declared legacy meta keys to their canonical finance keys.
 *
 * Sites declare the map through the `wicket/finance/meta_aliases` filter:
 *
 *     add_filter('wicket/finance/meta_aliases', function (array $aliases): array {
 *         $aliases['GL_Deferred'] = '_wicket_finance_gl_deferred';
 *
 *         return $aliases;
 *     });
 *
 * The map covers single-valued keys only: a legacy write always collapses to one canonical
 * value, `$prev_value` and `$unique` are ignored. Deletes and reads of a legacy key are not
 * redirected either, so importers that compare the legacy value before writing always see a
 * change and rewrite; that is harmless.
 *
 * WHY
 * ---
 * Import tools (WP All Import, custom REST writers) write product meta directly, so they
 * never pass through FinanceMeta::save_product_meta() and cannot be remapped there.
 * Intercepting the metadata layer (add_post_metadata / update_post_metadata) catches every
 * writer at one choke point: the legacy row never exists, reads need no fallback, and each
 * site keeps its legacy names out of the product.
 */
class MetaAliases
{
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Initialize the metadata-layer interception.
     *
     * @return void
     */
    public function init(): void
    {
        add_filter('add_post_metadata', [$this, 'redirect_add'], 10, 4);
        add_filter('update_post_metadata', [$this, 'redirect_update'], 10, 4);
    }

    /**
     * Redirect a legacy meta write on the add path.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key being written.
     * @param mixed  $meta_value Meta value being written.
     * @return mixed The canonical meta id when created, true when the canonical row already existed, otherwise the untouched $check.
     */
    public function redirect_add($check, int $object_id, string $meta_key, $meta_value)
    {
        return $this->redirect($check, $object_id, $meta_key, $meta_value, true);
    }

    /**
     * Redirect a legacy meta write on the update path.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key being written.
     * @param mixed  $meta_value Meta value being written.
     * @return mixed True when redirected (short-circuits the original write), otherwise the untouched $check.
     */
    public function redirect_update($check, int $object_id, string $meta_key, $meta_value)
    {
        return $this->redirect($check, $object_id, $meta_key, $meta_value, false);
    }

    /**
     * Redirect a legacy meta write to its canonical key.
     *
     * Writes on anything other than products (or product variations) and keys absent from
     * the map pass through untouched. A non-null $check short-circuits too: an earlier
     * filter already handled or blocked the write, and we do not override it.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key being written.
     * @param mixed  $meta_value Meta value being written.
     * @param bool   $is_add     True on the add_post_metadata path, false on update.
     * @return mixed See redirect_add()/redirect_update().
     */
    private function redirect($check, int $object_id, string $meta_key, $meta_value, bool $is_add)
    {
        if (null !== $check) {
            return $check;
        }

        $aliases = apply_filters('wicket/finance/meta_aliases', []);

        if (!isset($aliases[$meta_key]) || !is_string($aliases[$meta_key]) || $aliases[$meta_key] === $meta_key) {
            return $check;
        }

        $post_type = get_post_type($object_id);

        if (!in_array($post_type, ['product', 'product_variation'], true)) {
            return $check;
        }

        $canonical = $aliases[$meta_key];

        // Core unslashes the value before this filter; update_post_meta() unslashes again.
        $result = update_post_meta($object_id, $canonical, wp_slash($meta_value));

        $this->logger->debug('Redirected legacy finance meta write.', [
            'from' => $meta_key,
            'to' => $canonical,
            'post_id' => $object_id,
        ]);

        return $is_add ? $result : (bool) $result;
    }
}
