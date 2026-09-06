<?php

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

class HDLIC_Activator
{

    public static function activate()
    {
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(HDLIC_PLUGIN_FILE));
            set_transient('hdlic_wc_missing_notice', true, 30);
            return;
        }

        self::maybe_upgrade_db();

        require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-myaccount.php';
        add_rewrite_endpoint(HDLIC_MyAccount::ENDPOINT, EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    public static function is_woocommerce_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce');
    }

    public static function maybe_upgrade_db()
    {
        if (get_option('hdlic_db_version') === HDLIC_DB_VERSION) {
            return;
        }

        require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-repository.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(HDLIC_Repository::get_schema_sql());

        update_option('hdlic_db_version', HDLIC_DB_VERSION);
    }

    public static function declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDLIC_PLUGIN_FILE, true);
        }
    }
}
