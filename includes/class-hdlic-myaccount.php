<?php

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The only lookup this class performs is find_keys_for_customer(get_current_user_id()) --
 * always the currently-authenticated user's own id, never a value taken from the request --
 * so a customer can only ever see keys assigned to orders they themselves placed.
 */
final class HDLIC_MyAccount
{

    const ENDPOINT = 'license-keys';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'add_endpoint'));
        add_filter('query_vars', array($this, 'add_query_var'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', array($this, 'render_endpoint_content'));
    }

    public function add_endpoint()
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function add_query_var($vars)
    {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    public function add_menu_item($items)
    {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ('orders' === $key) {
                $new_items[self::ENDPOINT] = __('License Keys', 'hdwebmobile-license-key-delivery');
            }
        }
        if (!isset($new_items[self::ENDPOINT])) {
            $new_items[self::ENDPOINT] = __('License Keys', 'hdwebmobile-license-key-delivery');
        }
        return $new_items;
    }

    public function render_endpoint_content()
    {
        $keys = HDLIC_Repository::find_keys_for_customer(get_current_user_id());

        echo '<h2>' . esc_html__('License Keys', 'hdwebmobile-license-key-delivery') . '</h2>';

        if (empty($keys)) {
            echo '<p>' . esc_html__('You don\'t have any license keys yet.', 'hdwebmobile-license-key-delivery') . '</p>';
            return;
        }

        echo '<table class="woocommerce-table woocommerce-table--license-keys shop_table shop_table_responsive my_account_license_keys">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Product', 'hdwebmobile-license-key-delivery') . '</th>';
        echo '<th>' . esc_html__('Key', 'hdwebmobile-license-key-delivery') . '</th>';
        echo '<th>' . esc_html__('Order', 'hdwebmobile-license-key-delivery') . '</th>';
        echo '<th>' . esc_html__('Delivered', 'hdwebmobile-license-key-delivery') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($keys as $key) {
            $product = wc_get_product($key->product_id);
            $order   = wc_get_order($key->order_id);

            echo '<tr>';
            printf('<td>%s</td>', $product ? esc_html($product->get_name()) : esc_html__('(no longer available)', 'hdwebmobile-license-key-delivery'));
            printf('<td><code>%s</code></td>', esc_html($key->key_value));
            printf('<td>%s</td>', $order ? esc_html($order->get_order_number()) : '&mdash;');
            printf('<td>%s</td>', esc_html(date_i18n(get_option('date_format'), strtotime($key->assigned_at))));
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
