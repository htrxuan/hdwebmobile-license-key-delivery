<?php

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Closes CVE-2026-28114 (CVSS 9.1, Unrestricted Upload of File with Dangerous Type) in a
 * competing WooCommerce license-key plugin, where a Shop Manager-level account -- a role
 * routinely handed to shop staff and third-party agencies, deliberately less trusted than
 * Administrator -- could bulk-import license keys via a file upload with insufficient
 * extension/MIME/content validation, letting a `.php` file disguised as a key list achieve
 * remote code execution.
 *
 * This plugin closes that vulnerability class by construction, not mitigation: bulk import
 * (import_keys() below) is paste-a-textarea/generate-server-side ONLY. There is no file
 * upload input anywhere in this plugin's admin screens, no `$_FILES` handling, no
 * `wp_handle_upload()` call -- the vulnerable code path simply does not exist here, so no
 * amount of extension/MIME/content-sniffing correctness or incorrectness is even relevant.
 * A merchant with a large key list from a vendor pastes it in (documented as a Limitation,
 * not silently unsupported); nothing this plugin does can ever write an attacker-controlled
 * file to a web-accessible location.
 *
 * generate_random_keys() below is the only place new key VALUES are ever created without a
 * merchant typing them: it uses random_bytes(), never anything predictable.
 * claim_key_for_order() is the only place a key is ever handed to a customer, and does so
 * with a single atomic UPDATE ... LIMIT 1 so two orders can never race for the same key.
 *
 * Direct queries against a custom table are unavoidable here -- there is no WP API for this
 * data -- so DirectDatabaseQuery/NoCaching advisories are expected and accepted for this
 * class, matching standard practice for custom-table plugins.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
class HDLIC_Repository
{

    const STATUS_UNASSIGNED = 'unassigned';
    const STATUS_ASSIGNED   = 'assigned';

    const MAX_KEYS_PER_IMPORT = 2000;
    const MAX_KEY_LENGTH      = 190;

    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'hdlic_license_keys';
    }

    public static function get_schema_sql()
    {
        global $wpdb;
        $table           = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            key_value VARCHAR(191) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'unassigned',
            order_id BIGINT UNSIGNED DEFAULT NULL,
            assigned_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY key_value (key_value),
            KEY product_id_status (product_id, status),
            KEY order_id (order_id)
        ) {$charset_collate};";
    }

    /**
     * Parses raw pasted text directly in memory -- one key per line -- and inserts each as
     * its own row. There is no file path or $_FILES argument anywhere in this method's
     * signature; callers (class-hdlic-admin.php) only ever pass a plain string read from a
     * textarea POST field.
     *
     * @return array { inserted: int, duplicates: int, skipped_blank: int }
     */
    public static function import_keys($product_id, $raw_text)
    {
        global $wpdb;
        $table = self::get_table_name();

        $lines      = preg_split('/\r\n|\r|\n/', (string) $raw_text);
        $lines      = array_slice($lines, 0, self::MAX_KEYS_PER_IMPORT);
        $inserted   = 0;
        $duplicates = 0;
        $skipped    = 0;

        foreach ($lines as $line) {
            $key = trim($line);
            if ('' === $key) {
                $skipped++;
                continue;
            }
            $key = substr($key, 0, self::MAX_KEY_LENGTH);

            $result = $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'key_value'  => $key,
                    'status'     => self::STATUS_UNASSIGNED,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s')
            );

            if (false === $result) {
                // Almost certainly the UNIQUE KEY on key_value rejecting a duplicate --
                // never overwrite or silently merge, since that could reassign a key that
                // was already delivered to a different customer.
                $duplicates++;
            } else {
                $inserted++;
            }
        }

        return array('inserted' => $inserted, 'duplicates' => $duplicates, 'skipped_blank' => $skipped);
    }

    /**
     * Server-generated keys, never merchant-typed. Purely random -- no product id, timestamp,
     * or sequence number is ever encoded into the value, so a key never reveals anything
     * about how many others exist or in what order.
     */
    public static function generate_random_keys($product_id, $count, $segments = 4, $segment_length = 5)
    {
        $count = max(1, min((int) $count, self::MAX_KEYS_PER_IMPORT));
        $lines = array();

        for ($i = 0; $i < $count; $i++) {
            $parts = array();
            for ($s = 0; $s < $segments; $s++) {
                $parts[] = strtoupper(substr(bin2hex(random_bytes($segment_length)), 0, $segment_length));
            }
            $lines[] = implode('-', $parts);
        }

        return self::import_keys($product_id, implode("\n", $lines));
    }

    public static function count_available($product_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE product_id = %d AND status = %s',
            self::get_table_name(),
            $product_id,
            self::STATUS_UNASSIGNED
        ));
    }

    /**
     * The only place a key is ever handed to a customer. A single UPDATE ... LIMIT 1 is
     * atomic in MySQL/InnoDB -- the matching row is locked and updated in one statement, so
     * two orders completing at the same instant can never both claim the same key. Returns
     * the claimed row, or null if the product had no unassigned key left (the caller must
     * treat that as a backorder, never fabricate a placeholder key).
     */
    public static function claim_key_for_order($product_id, $order_id)
    {
        global $wpdb;
        $table = self::get_table_name();

        $updated = $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'UPDATE %i SET status = %s, order_id = %d, assigned_at = %s WHERE product_id = %d AND status = %s ORDER BY id ASC LIMIT 1',
            $table,
            self::STATUS_ASSIGNED,
            $order_id,
            current_time('mysql'),
            $product_id,
            self::STATUS_UNASSIGNED
        ));

        if (!$updated) {
            return null;
        }

        // order_id is unique to this single claim operation, so this always finds exactly
        // the row this call just claimed, even if other keys for the same product were
        // claimed by other orders in between.
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM %i WHERE product_id = %d AND order_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
            $table,
            $product_id,
            $order_id,
            self::STATUS_ASSIGNED
        ));
    }

    public static function find_keys_for_order($order_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM %i WHERE order_id = %d ORDER BY id ASC',
            self::get_table_name(),
            (int) $order_id
        ));
    }

    /**
     * Ownership is enforced entirely by this query joining through the customer's own
     * orders -- always call with get_current_user_id(), never a value taken from the request.
     */
    public static function find_keys_for_customer($customer_id)
    {
        global $wpdb;

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return array();
        }

        if (!self::hpos_table_exists()) {
            // Classic post-based orders storage.
            return $wpdb->get_results($wpdb->prepare(
                'SELECT k.* FROM %i k INNER JOIN %i p ON k.order_id = p.ID ' .
                'WHERE p.post_author = %d AND k.status = %s ORDER BY k.assigned_at DESC',
                self::get_table_name(),
                $wpdb->posts,
                $customer_id,
                self::STATUS_ASSIGNED
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT k.* FROM %i k INNER JOIN %i o ON k.order_id = o.id ' .
            'WHERE o.customer_id = %d AND k.status = %s ORDER BY k.assigned_at DESC',
            self::get_table_name(),
            $wpdb->prefix . 'wc_orders',
            $customer_id,
            self::STATUS_ASSIGNED
        ));
    }

    private static function hpos_table_exists()
    {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'wc_orders';
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orders_table)) === $orders_table;
    }

    /**
     * @param array $args { product_id, status, s (key search), per_page, paged, orderby, order }
     * @return array { items: array, total: int }
     */
    public static function get_for_list_table(array $args)
    {
        global $wpdb;

        $where  = array('1=1');
        $params = array();

        if (!empty($args['product_id'])) {
            $where[]  = 'product_id = %d';
            $params[] = (int) $args['product_id'];
        }

        if (!empty($args['status']) && 'all' !== $args['status']) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['s'])) {
            $where[]  = 'key_value LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['s']) . '%';
        }

        $where_sql = implode(' AND ', $where);

        $allowed_orderby = array('created_at', 'status', 'product_id');
        $orderby         = in_array($args['orderby'] ?? '', $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order           = 'ASC' === strtoupper($args['order'] ?? '') ? 'ASC' : 'DESC';

        $per_page = max(1, (int) ($args['per_page'] ?? 20));
        $paged    = max(1, (int) ($args['paged'] ?? 1));
        $offset   = ($paged - 1) * $per_page;

        $total = (int) $wpdb->get_var($wpdb->prepare( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT COUNT(*) FROM %i WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            array_merge(array(self::get_table_name()), $params)
        ));

        $items = $wpdb->get_results($wpdb->prepare( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            "SELECT * FROM %i WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            array_merge(array(self::get_table_name()), $params, array($per_page, $offset))
        ));

        return array(
            'items' => $items,
            'total' => $total,
        );
    }
}
