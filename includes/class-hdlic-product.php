<?php

namespace htrxuan\hdlic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The "License Keys" product-data tab is the only place keys ever enter this plugin, and
 * both actions on it (paste, generate) are plain textarea/number POSTs handled by
 * admin_post -- there is no file input anywhere on this screen. See class-hdlic-repository.php
 * for the full picture of how this closes CVE-2026-28114.
 */
final class HDLIC_Product
{

    const NONCE_META    = 'hdlic_save_meta';
    const NONCE_IMPORT  = 'hdlic_import_keys';
    const NONCE_GENERATE = 'hdlic_generate_keys';

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
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'render_product_data_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
        add_action('admin_post_hdlic_import_keys', array($this, 'handle_import_keys'));
        add_action('admin_post_hdlic_generate_keys', array($this, 'handle_generate_keys'));
    }

    public function add_product_data_tab($tabs)
    {
        $tabs['hdlic'] = array(
            'label'    => __('License Keys', 'hdwebmobile-license-key-delivery'),
            'target'   => 'hdlic_product_data',
            'class'    => array('show_if_simple'),
            'priority' => 25,
        );
        return $tabs;
    }

    public function is_license_product($product_id)
    {
        return 'yes' === get_post_meta($product_id, '_hdlic_enabled', true);
    }

    public function render_product_data_panel()
    {
        global $post;
        $product_id = $post->ID;
        $enabled    = $this->is_license_product($product_id);
        $available  = HDLIC_Repository::count_available($product_id);

        wp_nonce_field(self::NONCE_META, 'hdlic_meta_nonce');
        ?>
        <div id="hdlic_product_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <p class="form-field">
                    <label for="hdlic_enabled"><?php esc_html_e('Sell as license key product', 'hdwebmobile-license-key-delivery'); ?></label>
                    <input type="checkbox" id="hdlic_enabled" name="hdlic_enabled" value="yes" <?php checked($enabled); ?> />
                    <span class="description"><?php esc_html_e('One key is delivered to the customer automatically when their order is completed.', 'hdwebmobile-license-key-delivery'); ?></span>
                </p>
            </div>

            <div class="options_group" style="<?php echo $enabled ? '' : 'display:none;'; ?>" id="hdlic-key-management">
                <p style="padding:0 12px;">
                    <strong><?php
                        /* translators: %d: number of unassigned license keys currently in stock for this product */
                        printf(esc_html__('Available keys: %d', 'hdwebmobile-license-key-delivery'), (int) $available);
                    ?></strong>
                </p>

                <div style="padding:0 12px;margin-bottom:1em;">
                    <p><strong><?php esc_html_e('Paste keys (one per line)', 'hdwebmobile-license-key-delivery'); ?></strong></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="hdlic_import_keys" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />
                        <?php wp_nonce_field(self::NONCE_IMPORT . '_' . $product_id, 'hdlic_import_nonce'); ?>
                        <textarea name="raw_keys" rows="6" style="width:100%;" placeholder="ABCDE-12345-FGHIJ"></textarea>
                        <?php submit_button(__('Add Keys', 'hdwebmobile-license-key-delivery'), 'secondary', 'submit', false); ?>
                    </form>
                </div>

                <div style="padding:0 12px;">
                    <p><strong><?php esc_html_e('Or generate random keys', 'hdwebmobile-license-key-delivery'); ?></strong></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="hdlic_generate_keys" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />
                        <?php wp_nonce_field(self::NONCE_GENERATE . '_' . $product_id, 'hdlic_generate_nonce'); ?>
                        <input type="number" name="count" min="1" max="<?php echo (int) HDLIC_Repository::MAX_KEYS_PER_IMPORT; ?>" value="10" style="width:100px;" />
                        <?php submit_button(__('Generate Keys', 'hdwebmobile-license-key-delivery'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var cb = document.getElementById('hdlic_enabled');
            var box = document.getElementById('hdlic-key-management');
            if (cb && box) {
                cb.addEventListener('change', function () {
                    box.style.display = cb.checked ? '' : 'none';
                });
            }
        })();
        </script>
        <?php
    }

    public function save_product_meta($post_id)
    {
        if (!isset($_POST['hdlic_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdlic_meta_nonce'])), self::NONCE_META)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled = isset($_POST['hdlic_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_hdlic_enabled', $enabled);
    }

    public function handle_import_keys()
    {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (!current_user_can('edit_product', $product_id)) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-license-key-delivery'));
        }

        check_admin_referer(self::NONCE_IMPORT . '_' . $product_id, 'hdlic_import_nonce');

        // Deliberately a plain textarea value, never a file -- see class-hdlic-repository.php.
        $raw_keys = isset($_POST['raw_keys']) ? sanitize_textarea_field(wp_unslash($_POST['raw_keys'])) : '';

        $result = HDLIC_Repository::import_keys($product_id, $raw_keys);

        wp_safe_redirect(add_query_arg(
            array('hdlic_imported' => $result['inserted'], 'hdlic_duplicates' => $result['duplicates']),
            get_edit_post_link($product_id, 'raw')
        ));
        exit;
    }

    public function handle_generate_keys()
    {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (!current_user_can('edit_product', $product_id)) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-license-key-delivery'));
        }

        check_admin_referer(self::NONCE_GENERATE . '_' . $product_id, 'hdlic_generate_nonce');

        $count  = isset($_POST['count']) ? absint($_POST['count']) : 0;
        $result = HDLIC_Repository::generate_random_keys($product_id, $count);

        wp_safe_redirect(add_query_arg(
            array('hdlic_imported' => $result['inserted']),
            get_edit_post_link($product_id, 'raw')
        ));
        exit;
    }
}
