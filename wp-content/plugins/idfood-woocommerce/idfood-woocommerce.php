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

/**
 * On activation plugin
 */
register_activation_hook(__FILE__, 'on_activation');
function on_activation()
{

}

/**
 * On deactivation plugin
 */
register_deactivation_hook(__FILE__, 'on_deactivation');
function on_deactivation()
{

}

include_once(plugin_dir_path(__FILE__) . 'firebase.php');
include_once(plugin_dir_path(__FILE__) . 'WC_REST_Custom_Controller.php');
include_once(plugin_dir_path(__FILE__) . 'woocommerce-custom.php');

