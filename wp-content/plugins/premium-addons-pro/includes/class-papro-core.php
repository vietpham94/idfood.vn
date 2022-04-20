<?php
/**
 * PAPRO Core.
 */

namespace PremiumAddonsPro\Includes;

use PremiumAddonsPro\Admin\Includes\Admin_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No access of directly access.
}

if ( ! class_exists( 'PAPRO_Core' ) ) {

	/**
	 * Intialize and Sets up the plugin.
	 */
	class PAPRO_Core {

		/**
		 * Class instance
		 *
		 * @var instance
		 */
		private static $instance = null;

		/**
		 * Sets up needed actions/filters for the plug-in to initialize.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function __construct() {

			spl_autoload_register( array( $this, 'autoload' ) );

			// Register Activation Hook.
			register_activation_hook( PREMIUM_PRO_ADDONS_FILE, array( $this, 'register_activation_hook' ) );

			// Load plugin core.
			add_action( 'plugins_loaded', array( $this, 'premium_pro_elementor_setup' ), 9 );

			// Check if free version of Premium Addons installed.
			if ( self::check_premium_free() ) {
				// Load Addons required Files.
				add_action( 'elementor/init', array( $this, 'elementor_init' ) );
			}
		}

		/**
		 * AutoLoad
		 *
		 * @since 2.0.7
		 * @param string $class class.
		 */
		public function autoload( $class ) {

			if ( 0 !== strpos( $class, 'PremiumAddonsPro' ) ) {
				return;
			}

			$class_to_load = $class;

			if ( ! class_exists( $class_to_load ) ) {
				$filename = strtolower(
					preg_replace(
						array( '/^PremiumAddonsPro\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ),
						array( '', '$1-$2', '-', DIRECTORY_SEPARATOR ),
						$class_to_load
					)
				);

				$filename = PREMIUM_PRO_ADDONS_PATH . $filename . '.php';

				if ( is_readable( $filename ) ) {

					include $filename;
				}
			}
		}

		/**
		 * Register Activation Hook
		 *
		 * Reset hide white labeling tab option on plugin activate
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function register_activation_hook() {

			$white_label_settings = is_network_admin() ? get_site_option( 'pa_wht_lbl_save_settings' ) : get_option( 'pa_wht_lbl_save_settings' );

			if ( isset( $white_label_settings['premium-wht-lbl-option'] ) ) {

				$white_label_settings['premium-wht-lbl-option'] = 0;

				is_network_admin() ? update_site_option( 'pa_wht_lbl_save_settings', $white_label_settings ) : update_option( 'pa_wht_lbl_save_settings', $white_label_settings );

			}
		}

		/**
		 * Elementor Init
		 *
		 * Load required files after init Elementor
		 *
		 * @access public
		 *
		 * @return void
		 */
		public function elementor_init() {

			Compatibility\Premium_Pro_Wpml::get_instance();

			Addons_Integration::get_instance();

		}

		/**
		 * Installs translation text domain and checks if Elementor is installed
		 *
		 * @since 1.0.0
		 * @access public
		 * @return void
		 */
		public function premium_pro_elementor_setup() {

			if ( self::check_premium_free() ) {

				$this->load_domain();

			}

			$this->init_files();

			$this->init_plugin_updater();
		}

		/**
		 * Init Plugin Updater
		 *
		 * @since 2.0.7
		 * @access public
		 */
		public function init_plugin_updater() {

			// Disable SSL verification.
			add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );

			// Get License Key.
			$license_key = Admin_Helper::get_license_key();

			$edd_updater = new \PAPRO_Plugin_Updater(
				PAPRO_STORE_URL,
				PREMIUM_PRO_ADDONS_FILE,
				array(
					'version' => PREMIUM_PRO_ADDONS_VERSION,
					'license' => $license_key,
					'item_id' => PAPRO_ITEM_ID,
					'author'  => 'Leap13',
					'url'     => home_url(),
					'beta'    => false,
				)
			);

		}

		/**
		 * Check Premium Free
		 *
		 * Check if free version is activated
		 *
		 * @since 1.1.1
		 * @access public
		 *
		 * @return boolean PA active
		 */
		public static function check_premium_free() {

			return defined( 'PREMIUM_ADDONS_VERSION' );

		}

		/**
		 * Load domain
		 *
		 * Load plugin translated strings using text domain
		 *
		 * @since 1.1.1
		 * @access public
		 *
		 * @return void
		 */
		public function load_domain() {

			load_plugin_textdomain( 'premium-addons-pro' );
		}


		/**
		 * Init Files
		 *
		 * Require initial necessary files
		 *
		 * @since 1.1.1
		 * @access public
		 *
		 * @return void
		 */
		public function init_files() {

			if ( self::check_premium_free() ) {

				if ( is_admin() ) {
					\PremiumAddonsPro\Admin\Includes\Admin_Helper::get_instance();
				}

				White_Label\Branding::init();
				Plugin::instance();

			}

			\PremiumAddonsPro\Admin\Includes\Admin_Notices::get_instance();

		}


		/**
		 * Get instance
		 *
		 * Creates and returns an instance of the class
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return object
		 */
		public static function get_instance() {

			if ( ! isset( self::$instance ) ) {

				self::$instance = new self();
			}

			return self::$instance;
		}
	}
}

if ( ! function_exists( 'premium_addons_pro' ) ) {
	/**
	 * Returns an instance of the plugin class.
	 *
	 * @since  1.0.0
	 * @return object
	 */
	function premium_addons_pro() {
		return PAPRO_Core::get_instance();
	}
}
premium_addons_pro();
