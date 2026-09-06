<?php

/**
 * Plugin Name: HDWebmobile License Key Delivery
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-license-key-delivery/
 * Description: Sell software license keys through WooCommerce -- keys are pasted or generated in wp-admin, never imported from an uploaded file, and one is atomically assigned to each order the moment it completes.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-license-key-delivery
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('HDLIC_VERSION', '1.0.0');
define('HDLIC_DB_VERSION', '1.0.0');
define('HDLIC_PLUGIN_FILE', __FILE__);
define('HDLIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDLIC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-activator.php';

register_activation_hook(HDLIC_PLUGIN_FILE, array(HDLIC_Activator::class, 'activate'));
add_action('before_woocommerce_init', array(HDLIC_Activator::class, 'declare_hpos_compatibility'));

add_action('plugins_loaded', function () {
    require_once HDLIC_PLUGIN_DIR . 'includes/class-hdlic-core.php';
    HDLIC_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(HDLIC_PLUGIN_FILE), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" style="color:#d54e21;font-weight:bold;">' . __('Donate', 'hdwebmobile-license-key-delivery') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
