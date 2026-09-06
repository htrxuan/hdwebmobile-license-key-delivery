<?php

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

class HDLIC_Admin
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
        require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['license-key-delivery'] = array(
            'label'  => __('License Keys', 'hdwebmobile-license-key-delivery'),
            'order'  => 49,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    public function render_page()
    {
        require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-admin-list-table.php';

        $table = new HDLIC_Admin_List_Table();
        $table->prepare_items();

        // Read-only success flags from a post/redirect/get after the actual state-changing
        // action already passed nonce verification in class-hdlic-product.php's handlers.
        if (isset($_GET['hdlic_imported'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $count = absint($_GET['hdlic_imported']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        /* translators: %d: number of license keys just added */
                        _n('%d key added.', '%d keys added.', $count, 'hdwebmobile-license-key-delivery'),
                        $count
                    )
                )
            );
        }
        ?>
        <p><?php esc_html_e('Sell software license keys through WooCommerce. Keys are pasted or generated per-product under Products > (edit product) > License Keys tab -- never imported from an uploaded file.', 'hdwebmobile-license-key-delivery'); ?></p>
        <form method="get">
            <input type="hidden" name="page" value="hdwebmobile" />
            <input type="hidden" name="tab" value="license-key-delivery" />
            <?php
            $table->search_box(__('Search key', 'hdwebmobile-license-key-delivery'), 'hdlic-search');
            $table->display();
            ?>
        </form>
        <?php
    }
}
