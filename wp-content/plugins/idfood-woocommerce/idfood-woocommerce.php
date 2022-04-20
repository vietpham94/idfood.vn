<?php
/**
 * Plugin Name: WooCommerce for IDFOOD
 * Plugin URI: https://woocommerce.com/
 * Description: An eCommerce toolkit that helps you sell anything. Beautifully.
 * Version: 6.1.0
 * Author: Automattic
 * Author URI: https://woocommerce.com
 * Text Domain: idfood-wc
 * Domain Path: /i18n/languages/
 * Requires at least: 5.6
 * Requires PHP: 7.0
 *
 * @package WooCommerce
 */

// Create notification table
register_activation_hook(__FILE__, 'notifications_install');
function notifications_install()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'notifications';
    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

    if (!$wpdb->get_var($query) == $table_name) {

        $charset_collate = $wpdb->get_charset_collate();

        $sql  = "CREATE TABLE $table_name(
                title text NOT NULL, 
                body text NOT NULL, 
                receiver_id mediumint(9) NOT NULL, 
                order_id mediumint(9) NOT NULL, 
                status boolean DEFAULT false, 
                time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, 
                UNIQUE KEY (order_id)) $charset_collate";

        if(!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        dbDelta($sql);
        write_log(__FILE__ . ":43 Table notifications created...");
        update_option('tables_created', true);
    }
}

// Add custom column to customer
register_activation_hook(__FILE__, 'customer_add_provider_field');
function customer_add_provider_field()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_customer_lookup';
    $row1 = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='$table_name' AND column_name='provider_id'");
    if (empty($row1)) {
        $wpdb->query("ALTER TABLE $table_name ADD provider_id INT(20)");
    }

    $row2 = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='$table_name' AND column_name='address'");
    if (empty($row2)) {
        $wpdb->query("ALTER TABLE $table_name ADD address VARCHAR(255)");
    }

    $row3 = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='$table_name' AND column_name='phone'");
    if (empty($row3)) {
        $wpdb->query("ALTER TABLE $table_name ADD phone VARCHAR(20)");
    }
}

register_deactivation_hook(__FILE__, 'on_deactivation');
function on_deactivation()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'notifications';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
    delete_option('jal_db_version');
}

include_once(plugin_dir_path(__FILE__) . 'firebase.php');
include_once(plugin_dir_path(__FILE__) . 'WC_REST_Custom_Controller.php');
include_once(plugin_dir_path(__FILE__) . 'woocommerce-custom.php');

