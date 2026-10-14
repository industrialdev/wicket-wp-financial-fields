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
 * value, `$prev_value` is ignored. The canonical row is the single source of truth, so the
 * redirect owns four interception points:
 *
 * - `add_post_metadata`: a legacy add writes the canonical row and reports failure. The
 *   caller must never keep a meta id for the legacy key: WooCommerce stores the
 *   add_metadata() return as $meta->id and feeds it to the by-mid helpers on the next
 *   save, and a stored mid under the legacy key would let the by-mid raw queries rename
 *   or delete the canonical row.
 * - `update_post_metadata`: a legacy update (add_post_meta-less writers, importers)
 *   writes the canonical row.
 * - `update_post_metadata_by_mid`: WooCommerce keeps a legacy row's mid when one already
 *   exists in the database, so its saves take the by-mid path. The canonical row is
 *   updated, the raw legacy-row write is blocked, and the stale legacy row is removed,
 *   so rows that predate the map converge on their next object save.
 * - `delete_post_metadata_by_mid`: a legacy delete removes the canonical row and the
 *   stale legacy row, mirroring what a delete means for a non-aliased product.
 *
 * Reads of a legacy key are not redirected. A legacy row that predates the map survives
 * only until its next object save or delete, which removes it; no data migration is
 * required.
 *
 * WHY
 * ---
 * Import tools (WP All Import, custom REST writers) write product meta directly, so they
 * never pass through FinanceMeta::save_product_meta() and cannot be remapped there.
 * Intercepting the metadata layer catches every writer at one choke point, and the by-mid
 * hooks close the one path WooCommerce itself takes on later saves.
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
        add_filter('add_post_metadata', [$this, 'redirect_add'], 10, 5);
        add_filter('update_post_metadata', [$this, 'redirect_update'], 10, 4);
        add_filter('update_post_metadata_by_mid', [$this, 'redirect_update_by_mid'], 10, 4);
        add_filter('delete_post_metadata_by_mid', [$this, 'redirect_delete_by_mid'], 10, 2);
    }

    /**
     * Redirect a legacy meta write on the add path.
     *
     * Reports failure once redirected: the canonical row holds the value and no meta id
     * ever reaches the caller. With `$unique`, an existing canonical row wins untouched,
     * matching core's "already exists" result.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key being written.
     * @param mixed  $meta_value Meta value being written.
     * @param bool   $unique     Whether the caller asked for a unique add.
     * @return mixed False when redirected, otherwise the untouched $check.
     */
    public function redirect_add($check, int $object_id, string $meta_key, $meta_value, bool $unique = false)
    {
        return $this->redirect($check, $object_id, $meta_key, $meta_value, true, $unique);
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
     * filter already handled or blocked the write, and we do not override it. A mapped key
     * whose target is itself mapped is a broken map; it passes through rather than chain.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key being written.
     * @param mixed  $meta_value Meta value being written.
     * @param bool   $is_add     True on the add_post_metadata path, false on update.
     * @param bool   $unique     Whether the caller asked for a unique add.
     * @return mixed False on the redirected add path, bool on the update path, untouched $check otherwise.
     */
    private function redirect($check, int $object_id, string $meta_key, $meta_value, bool $is_add, bool $unique = false)
    {
        if (null !== $check) {
            return $check;
        }

        $aliases = apply_filters('wicket/finance/meta_aliases', []);

        if (!$this->is_mapped($aliases, $meta_key)) {
            return $check;
        }

        $post_type = get_post_type($object_id);

        if (!in_array($post_type, ['product', 'product_variation'], true)) {
            return $check;
        }

        $canonical = $aliases[$meta_key];

        if ($is_add && $unique && metadata_exists('post', $object_id, $canonical)) {
            return false;
        }

        // Core unslashes the value before this filter; update_post_meta unslashes again.
        update_post_meta($object_id, $canonical, wp_slash($meta_value));

        $this->logger->debug('Redirected legacy finance meta write.', [
            'from' => $meta_key,
            'to' => $canonical,
            'post_id' => $object_id,
        ]);

        // The add path always reports failure: no meta id may leave this redirect for a legacy key.
        return $is_add ? false : true;
    }

    /**
     * Redirect a WooCommerce save of a pre-existing legacy meta row to the canonical key.
     *
     * WC keeps a legacy row's mid, so its saves take the by-mid path, whose raw query
     * bypasses redirect_update(). Writing the canonical row and short-circuiting blocks
     * that query; removing the stale legacy row afterwards restores the no-legacy-rows
     * invariant without a migration. The removal goes through delete_post_meta(), whose
     * by-key filter this redirect does not hook, so nothing re-enters here.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $meta_id    Meta row ID.
     * @param mixed  $meta_value Meta value being written.
     * @param mixed  $meta_key   Requested meta key, or false to keep the row's key.
     * @return mixed True when redirected, otherwise the untouched $check.
     */
    public function redirect_update_by_mid($check, int $meta_id, $meta_value, $meta_key = false)
    {
        if (null !== $check) {
            return $check;
        }

        $meta = get_metadata_by_mid('post', $meta_id);

        if (!$meta instanceof \stdClass || !$this->should_redirect_row((int) $meta->post_id, (string) $meta->meta_key)) {
            return $check;
        }

        $requested_key = is_string($meta_key) && $meta_key !== '' ? $meta_key : $meta->meta_key;
        $aliases = apply_filters('wicket/finance/meta_aliases', []);

        // Any rename of a mapped legacy row (requested key differs from the row key)
        // would move the row off the alias through the raw by-mid query: block it.
        if ($requested_key !== $meta->meta_key) {
            return false;
        }

        $this->write_canonical((int) $meta->post_id, $aliases[$meta->meta_key], $meta->meta_key, $meta_value);

        delete_post_meta((int) $meta->post_id, $meta->meta_key);

        return true;
    }

    /**
     * Redirect a delete of a legacy meta row by mid to the canonical row.
     *
     * Removing the legacy alias means removing the value: the canonical row goes first,
     * then the stale legacy row, which is what core would have deleted.
     *
     * @param mixed $check   Null to proceed with the original delete, anything else short-circuits it.
     * @param int   $meta_id Meta row ID.
     * @return mixed True when redirected, otherwise the untouched $check.
     */
    public function redirect_delete_by_mid($check, int $meta_id)
    {
        if (null !== $check) {
            return $check;
        }

        $meta = get_metadata_by_mid('post', $meta_id);

        if (!$meta instanceof \stdClass || !$this->should_redirect_row((int) $meta->post_id, (string) $meta->meta_key)) {
            return $check;
        }

        $object_id = (int) $meta->post_id;
        $aliases = apply_filters('wicket/finance/meta_aliases', []);
        $canonical = $aliases[$meta->meta_key];

        delete_post_meta($object_id, $canonical);
        delete_post_meta($object_id, $meta->meta_key);

        $this->logger->debug('Redirected legacy finance meta delete.', [
            'from' => $meta->meta_key,
            'to' => $canonical,
            'post_id' => $object_id,
        ]);

        return true;
    }

    /**
     * Whether a meta row on a product carries a mapped, self-consistent legacy key.
     *
     * @param int    $object_id Post ID.
     * @param string $meta_key  Row's meta key.
     * @return bool
     */
    private function should_redirect_row(int $object_id, string $meta_key): bool
    {
        $aliases = apply_filters('wicket/finance/meta_aliases', []);

        if (!$this->is_mapped($aliases, $meta_key)) {
            return false;
        }

        $post_type = get_post_type($object_id);

        return in_array($post_type, ['product', 'product_variation'], true);
    }

    /**
     * Whether the map maps this key to a canonical key it does not also map.
     *
     * A target that is itself a mapped key would chain or cycle; that is a broken map,
     * and broken entries pass through.
     *
     * @param array  $aliases  Alias map.
     * @param string $meta_key Meta key to look up.
     * @return bool
     */
    private function is_mapped(array $aliases, string $meta_key): bool
    {
        if (!isset($aliases[$meta_key]) || !is_string($aliases[$meta_key]) || $aliases[$meta_key] === $meta_key) {
            return false;
        }

        return !isset($aliases[$aliases[$meta_key]]);
    }

    /**
     * Write the canonical row for a redirected legacy write.
     *
     * @param int    $object_id  Post ID.
     * @param string $canonical  Canonical meta key.
     * @param string $legacy_key Legacy meta key.
     * @param mixed  $meta_value Meta value being written.
     * @return void
     */
    private function write_canonical(int $object_id, string $canonical, string $legacy_key, $meta_value): void
    {
        // Core unslashes the value before this filter; update_post_meta unslashes again.
        update_post_meta($object_id, $canonical, wp_slash($meta_value));

        $this->logger->debug('Redirected legacy finance meta write.', [
            'from' => $legacy_key,
            'to' => $canonical,
            'post_id' => $object_id,
        ]);
    }
}
