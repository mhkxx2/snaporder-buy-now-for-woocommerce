<?php
/**
 * Plugin Name: SnapOrder Buy Now for WooCommerce
 * Plugin URI: https://karazshop.ae/
 * Description: Adds Buy Now quick-order checkout and chat-order buttons on WooCommerce product pages, plus a floating chat button with Meta Pixel & GTM-friendly tracking.
 * Version:     1.2.1
 * Author:      ShopForge Labs
 * Author URI: https://mudassar.net/
 * Text Domain: snaporder-buy-now-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SOBN_VERSION', '1.2.1' );
define( 'SOBN_FILE', __FILE__ );
define( 'SOBN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOBN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
final class SnapOrder_Buy_Now {

	/**
	 * Single instance of the class
	 *
	 * @var SnapOrder_Buy_Now|null
	 */
	private static $instance = null;

	/**
	 * Main SnapOrder_Buy_Now Instance
	 *
	 * @return SnapOrder_Buy_Now
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files used in admin and frontend
	 */
	private function includes() {
		require_once SOBN_PATH . 'includes/class-settings.php';
		require_once SOBN_PATH . 'includes/class-shipping-calculator.php';
		require_once SOBN_PATH . 'includes/class-frontend.php';
		require_once SOBN_PATH . 'includes/class-ajax-order.php';
	}

	/**
	 * Hook into actions and filters
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		register_activation_hook( SOBN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Check WooCommerce dependency and instantiate components
	 */
	public function on_plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		SnapOrder_Settings::init();
		SnapOrder_Frontend::init();
		SnapOrder_Ajax_Order::init();
	}

	/**
	 * Activation hook
	 */
	public function activate() {
		// Set default settings if not already present
		if ( false === get_option( 'snaporder_chat_number' ) ) {
			update_option( 'snaporder_chat_number', '' );
		}
		if ( false === get_option( 'snaporder_chat_message_template' ) ) {
			$default_template = "Hi, I want to order this product:\n{product_name}\nQty: {qty}\nPrice: {price} AED\nLink: {link}";
			update_option( 'snaporder_chat_message_template', $default_template );
		}
		if ( false === get_option( 'snaporder_cod_order_status' ) ) {
			update_option( 'snaporder_cod_order_status', 'processing' );
		}
		if ( false === get_option( 'snaporder_floating_chat_enabled' ) ) {
			update_option( 'snaporder_floating_chat_enabled', 'yes' );
		}
		if ( false === get_option( 'snaporder_floating_chat_position' ) ) {
			update_option( 'snaporder_floating_chat_position', 'right' );
		}
		if ( false === get_option( 'snaporder_floating_chat_show_text' ) ) {
			update_option( 'snaporder_floating_chat_show_text', 'yes' );
		}
		if ( false === get_option( 'snaporder_floating_chat_text' ) ) {
			update_option( 'snaporder_floating_chat_text', 'Need help?' );
		}
		if ( false === get_option( 'snaporder_floating_chat_display_rule' ) ) {
			update_option( 'snaporder_floating_chat_display_rule', 'all' );
		}
		if ( false === get_option( 'snaporder_floating_chat_default_msg' ) ) {
			update_option( 'snaporder_floating_chat_default_msg', 'Hello! I need help regarding your store ({page_url})' );
		}
	}

	/**
	 * Admin notice when WooCommerce is inactive
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'SnapOrder Buy Now for WooCommerce requires WooCommerce to be installed and active.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
		</div>
		<?php
	}
}

/**
 * Global function to instantiate plugin
 */
function snaporder_buy_now() {
	return SnapOrder_Buy_Now::instance();
}

// Global bootstrap
snaporder_buy_now();
