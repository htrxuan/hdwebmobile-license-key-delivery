<?php

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Claims one key per unit purchased, the moment an order is marked completed. Idempotent by
 * design (guarded by the _hdlic_keys_assigned order meta flag) so a status transition firing
 * twice -- or an admin manually re-saving the order -- can never claim a second key for the
 * same line item.
 */
final class HDLIC_Order
{

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
        add_action('woocommerce_order_status_completed', array($this, 'assign_keys'));
        add_action('woocommerce_order_item_meta_end', array($this, 'maybe_show_backorder_notice'), 10, 3);
    }

    public function assign_keys($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ('yes' === $order->get_meta('_hdlic_keys_assigned')) {
            return;
        }

        $product = null;
        $shortages = array();

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-product.php';
            if (!HDLIC_Product::get_instance()->is_license_product($product->get_id())) {
                continue;
            }

            $quantity = max(1, (int) $item->get_quantity());
            $claimed  = array();

            for ($i = 0; $i < $quantity; $i++) {
                $key = HDLIC_Repository::claim_key_for_order($product->get_id(), $order_id);
                if ($key) {
                    $claimed[] = $key->key_value;
                }
            }

            $delivered = count($claimed);
            if ($delivered < $quantity) {
                $shortages[] = sprintf('%s (%d of %d)', $product->get_name(), $delivered, $quantity);
            }

            foreach ($claimed as $key_value) {
                $item->add_meta_data(__('License Key', 'hdwebmobile-license-key-delivery'), $key_value, false);
            }
            $item->save();
        }

        $order->update_meta_data('_hdlic_keys_assigned', 'yes');
        $order->save();

        if (!empty($shortages)) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: comma-separated list of "Product Name (delivered of needed)" */
                    __('HDWebmobile License Key Delivery: not enough keys in stock to fully fulfil this order -- %s. Add more keys under Products > (edit product) > License Keys, then deliver the remaining key(s) to the customer manually.', 'hdwebmobile-license-key-delivery'),
                    implode(', ', $shortages)
                )
            );
            $this->notify_admin_of_shortage($order, $shortages);
        }
    }

    private function notify_admin_of_shortage($order, $shortages)
    {
        $subject = sprintf(
            /* translators: %d: order ID */
            __('License key stock shortage on order #%d', 'hdwebmobile-license-key-delivery'),
            $order->get_id()
        );

        $body = sprintf(
            "Order #%d completed but could not be fully fulfilled with license keys:\n\n%s\n\nAdd more keys and deliver the remaining key(s) to the customer manually.",
            $order->get_id(),
            implode("\n", $shortages)
        );

        wp_mail(get_option('admin_email'), $subject, $body);
    }

    /**
     * A quiet, customer-facing note on the order-received/thank-you page and order-detail
     * view for any line item that came up short, so the customer isn't left wondering why
     * their key is missing while the admin follows up.
     */
    public function maybe_show_backorder_notice($item_id, $item, $order)
    {
        require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-product.php';
        $product = $item->get_product();
        if (!$product || !HDLIC_Product::get_instance()->is_license_product($product->get_id())) {
            return;
        }

        if ('yes' !== $order->get_meta('_hdlic_keys_assigned')) {
            return;
        }

        $delivered = count($item->get_meta('License Key', false));
        $needed    = max(1, (int) $item->get_quantity());

        if ($delivered < $needed) {
            echo '<p class="hdlic-backorder-notice">' . esc_html__('One or more license keys for this item are still being prepared and will be sent to you shortly.', 'hdwebmobile-license-key-delivery') . '</p>';
        }
    }
}
