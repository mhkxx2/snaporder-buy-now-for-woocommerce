<?php
/**
 * Frontend Button Injection & Modal Class
 *
 * @package snaporder-buy-now-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SnapOrder_Frontend {

	/**
	 * Initialize frontend hooks
	 */
	public static function init() {
		self::reposition_tabby_product_promo();
		add_action( 'wp', array( __CLASS__, 'suppress_woodmart_quick_buy' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'suppress_woodmart_quick_buy' ), 20 );
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_action_buttons' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'render_quick_order_modal' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_sticky_buy_now_bar' ), 85 );
		add_action( 'wp_footer', array( __CLASS__, 'render_floating_whatsapp_button' ), 90 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'maybe_render_meta_pixel' ), 1 );
	}

	/**
	 * Suppress WoodMart built-in quick buy button to avoid duplicates
	 */
	public static function suppress_woodmart_quick_buy() {
		if ( class_exists( 'XTS\Modules\Quick_Buy\Main' ) ) {
			remove_action( 'woocommerce_after_add_to_cart_button', array( \XTS\Modules\Quick_Buy\Main::get_instance(), 'output_quick_buy_button' ), 1 );
		}
	}

	/**
	 * Render Tabby's product promo after the complete add-to-cart form.
	 *
	 * Tabby attaches the promo before the form by default. Moving the same
	 * callback preserves Tabby's own markup and JavaScript while placing it
	 * below this plugin's Buy Now / WhatsApp row.
	 */
	private static function reposition_tabby_product_promo() {
		if ( ! class_exists( 'WC_Tabby_Promo' ) ) {
			return;
		}

		$callback = array( 'WC_Tabby_Promo', 'product_promotion' );
		$priority = has_action( 'woocommerce_before_add_to_cart_form', $callback );

		if ( false !== $priority ) {
			remove_action( 'woocommerce_before_add_to_cart_form', $callback, (int) $priority );
		}

		if ( false === has_action( 'woocommerce_after_add_to_cart_form', $callback ) ) {
			add_action( 'woocommerce_after_add_to_cart_form', $callback, 30 );
		}
	}

	/**
	 * Check if the Floating WhatsApp Button should be shown on the current request
	 *
	 * @return bool
	 */
	public static function should_show_floating_wa() {
		if ( ! SnapOrder_Settings::is_floating_wa_enabled() ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		$exclude_pages = SnapOrder_Settings::get_floating_wa_exclude_pages();
		if ( ! empty( $exclude_pages ) ) {
			if ( in_array( '_special_checkout', $exclude_pages, true ) && function_exists( 'is_checkout' ) && is_checkout() ) {
				return false;
			}
			if ( in_array( '_special_cart', $exclude_pages, true ) && function_exists( 'is_cart' ) && is_cart() ) {
				return false;
			}
			if ( in_array( '_special_products', $exclude_pages, true ) && function_exists( 'is_product' ) && is_product() ) {
				return false;
			}
			if ( in_array( '_special_shop', $exclude_pages, true ) && function_exists( 'is_shop' ) && is_shop() ) {
				return false;
			}
			if ( in_array( '_special_home', $exclude_pages, true ) && ( is_front_page() || is_home() ) ) {
				return false;
			}
			if ( in_array( '_special_blog', $exclude_pages, true ) && ( is_singular( 'post' ) || is_category() || is_tag() ) ) {
				return false;
			}

			$current_id = get_queried_object_id();
			if ( $current_id && in_array( (string) $current_id, $exclude_pages, true ) ) {
				return false;
			}
			if ( is_front_page() ) {
				$front_id = (int) get_option( 'page_on_front' );
				if ( $front_id && in_array( (string) $front_id, $exclude_pages, true ) ) {
					return false;
				}
			}
			if ( function_exists( 'wc_get_page_id' ) ) {
				if ( function_exists( 'is_shop' ) && is_shop() ) {
					$shop_id = (int) wc_get_page_id( 'shop' );
					if ( $shop_id && in_array( (string) $shop_id, $exclude_pages, true ) ) {
						return false;
					}
				}
				if ( function_exists( 'is_cart' ) && is_cart() ) {
					$cart_id = (int) wc_get_page_id( 'cart' );
					if ( $cart_id && in_array( (string) $cart_id, $exclude_pages, true ) ) {
						return false;
					}
				}
				if ( function_exists( 'is_checkout' ) && is_checkout() ) {
					$checkout_id = (int) wc_get_page_id( 'checkout' );
					if ( $checkout_id && in_array( (string) $checkout_id, $exclude_pages, true ) ) {
						return false;
					}
				}
			}
		}

		$rule = SnapOrder_Settings::get_floating_wa_display_rule();
		if ( 'all' === $rule ) {
			return true;
		}

		// Specific pages only
		$include_pages = SnapOrder_Settings::get_floating_wa_include_pages();
		if ( empty( $include_pages ) ) {
			return false;
		}

		if ( in_array( '_special_home', $include_pages, true ) && ( is_front_page() || is_home() ) ) {
			return true;
		}
		if ( in_array( '_special_products', $include_pages, true ) && function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( in_array( '_special_shop', $include_pages, true ) && function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}
		if ( in_array( '_special_blog', $include_pages, true ) && ( is_singular( 'post' ) || is_category() || is_tag() ) ) {
			return true;
		}

		$current_id = get_queried_object_id();
		if ( $current_id && in_array( (string) $current_id, $include_pages, true ) ) {
			return true;
		}

		if ( is_front_page() ) {
			$front_id = (int) get_option( 'page_on_front' );
			if ( $front_id && in_array( (string) $front_id, $include_pages, true ) ) {
				return true;
			}
		}

		if ( function_exists( 'wc_get_page_id' ) && function_exists( 'is_shop' ) && is_shop() ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id && in_array( (string) $shop_id, $include_pages, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue CSS & JS assets
	 */
	public static function enqueue_assets() {
		// Enqueue WhatsApp Floating Button CSS when active for current request
		if ( self::should_show_floating_wa() ) {
			wp_enqueue_style(
				'snaporder-floating-wa-css',
				SOBN_URL . 'assets/css/floating-button.css',
				array(),
				SOBN_VERSION
			);
		}

		// Enqueue Quick Order Single Product Assets
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product ) {
			return;
		}

		// CSS
		$css_path = SOBN_PATH . 'assets/css/quick-order.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : SOBN_VERSION;
		wp_enqueue_style(
			'snaporder-quick-order-css',
			SOBN_URL . 'assets/css/quick-order.css',
			array(),
			$css_ver
		);

		// JS (with jQuery dependency for standard WooCommerce variation events)
		$js_path = SOBN_PATH . 'assets/js/quick-order.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SOBN_VERSION;
		wp_enqueue_script(
			'snaporder-quick-order-js',
			SOBN_URL . 'assets/js/quick-order.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		$is_variable = $product->is_type( 'variable' );
		$price       = (float) $product->get_price();
		$variations  = array();

		if ( $is_variable ) {
			$prices = $product->get_variation_prices( true );
			if ( ! empty( $prices['price'] ) ) {
				$price = (float) current( $prices['price'] );
			}
			$variations = $product->get_available_variations();
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();

		$params = array(
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'snaporder_quick_order_action' ),
			'product_id'           => $product->get_id(),
			'product_name'         => html_entity_decode( $product->get_name(), ENT_QUOTES, 'UTF-8' ),
			'product_price'        => $price,
			'product_url'          => get_permalink( $product->get_id() ),
			'product_image'        => $image_url,
			'is_variable'          => $is_variable,
			'available_variations' => $variations,
			'currency_symbol'      => get_woocommerce_currency_symbol() ?: 'AED',
			'currency_code'        => get_woocommerce_currency() ?: 'AED',
			'currency_pos'         => get_option( 'woocommerce_currency_pos', 'left' ),
			'thousand_sep'         => wc_get_price_thousand_separator(),
			'decimal_sep'          => wc_get_price_decimal_separator(),
			'decimals'             => wc_get_price_decimals(),
			'product_brand'        => class_exists( 'SnapOrder_Ecommerce_Tracking' ) ? SnapOrder_Ecommerce_Tracking::get_product_brand( $product ) : '',
			'product_cat'          => class_exists( 'SnapOrder_Ecommerce_Tracking' ) ? ( SnapOrder_Ecommerce_Tracking::get_product_categories( $product )['cat1'] ?? '' ) : '',
			'whatsapp_number'      => SnapOrder_Settings::get_whatsapp_number(),
			'whatsapp_template'    => SnapOrder_Settings::get_whatsapp_template(),
			'pixel_id'             => SnapOrder_Settings::get_pixel_id(),
			'i18n'                 => array(
				'complete_order'   => __( 'Complete Purchase', 'snaporder-buy-now-for-woocommerce' ),
				'submitting'       => __( 'Processing...', 'snaporder-buy-now-for-woocommerce' ),
				'phone_invalid'    => __( 'Please enter a valid UAE mobile number (e.g. 050 123 4567).', 'snaporder-buy-now-for-woocommerce' ),
				'name_required'    => __( 'Please enter your full name.', 'snaporder-buy-now-for-woocommerce' ),
				'addr_required'    => __( 'Please enter your delivery address.', 'snaporder-buy-now-for-woocommerce' ),
				'city_required'    => __( 'Please select your city.', 'snaporder-buy-now-for-woocommerce' ),
				'select_options'   => __( 'Please select all required product options first.', 'snaporder-buy-now-for-woocommerce' ),
				'out_of_stock'     => __( 'The selected option is currently out of stock.', 'snaporder-buy-now-for-woocommerce' ),
				'apply_coupon'     => __( 'Apply', 'snaporder-buy-now-for-woocommerce' ),
				'applying_coupon'  => __( 'Applying...', 'snaporder-buy-now-for-woocommerce' ),
				'coupon_empty'     => __( 'Please enter a coupon code.', 'snaporder-buy-now-for-woocommerce' ),
				'coupon_applied'   => __( 'Coupon applied successfully!', 'snaporder-buy-now-for-woocommerce' ),
				'coupon_removed'   => __( 'Coupon removed.', 'snaporder-buy-now-for-woocommerce' ),
				'payment_failed'   => __( 'Payment failed or was cancelled. Please retry or choose Cash on Delivery.', 'snaporder-buy-now-for-woocommerce' ),
				'timeout_error'    => __( 'Payment request timed out. Please try again or choose Cash on Delivery.', 'snaporder-buy-now-for-woocommerce' ),
				'error_occurred'   => __( 'An error occurred. Please try again.', 'snaporder-buy-now-for-woocommerce' ),
			),
		);

		wp_localize_script( 'snaporder-quick-order-js', 'snaporder_quick_order_params', $params );
	}

	/**
	 * Render Buy Now and WhatsApp buttons below Add to Cart
	 */
	public static function render_action_buttons() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}
		if ( ! $product->is_type( 'variable' ) && ! $product->is_in_stock() ) {
			return;
		}
		?>
		<div class="snaporder-action-buttons" id="snaporder-action-buttons">
			<!-- Buy Now (Left CTA) -->
			<button type="button" class="snaporder-btn snaporder-buy-now-btn" id="snaporder-buy-now-btn" aria-label="<?php esc_attr_e( 'Buy Now with Quick Order', 'snaporder-buy-now-for-woocommerce' ); ?>">
				<span class="snaporder-btn-text"><?php esc_html_e( 'Buy Now', 'snaporder-buy-now-for-woocommerce' ); ?></span>
			</button>

			<!-- Order via WhatsApp (Right CTA) -->
			<a href="#" class="snaporder-btn snaporder-chat-btn" id="snaporder-chat-btn" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Order via WhatsApp', 'snaporder-buy-now-for-woocommerce' ); ?>">
				<span class="snaporder-btn-icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
						<path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0012.04 2zm5.79 14.07c-.24.68-1.4 1.26-1.92 1.32-.49.06-1.12.08-3.23-.79-2.69-1.11-4.42-3.83-4.55-4.01-.13-.18-1.09-1.45-1.09-2.76 0-1.31.69-1.95.93-2.22.24-.26.54-.33.72-.33.18 0 .36 0 .52.01.17.01.39-.06.61.47.23.55.77 1.88.84 2.02.07.14.12.31.02.5-.1.19-.15.31-.29.48-.15.17-.31.38-.44.51-.15.15-.3.31-.13.61.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.44.29.15.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.39-.24.65-.15.26.1 1.67.79 1.96.93.29.15.48.22.55.34.07.13.07.75-.17 1.43z"/>
					</svg>
				</span>
				<span class="snaporder-btn-text"><?php esc_html_e( 'Order via WhatsApp', 'snaporder-buy-now-for-woocommerce' ); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * Render Sticky Buy Now Bar on single product pages
	 */
	public static function render_sticky_buy_now_bar() {
		if ( ! is_product() ) {
			return;
		}

		if ( ! SnapOrder_Settings::is_sticky_buy_now_enabled() ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}
		if ( ! $product->is_type( 'variable' ) && ! $product->is_in_stock() ) {
			return;
		}

		$product_name = $product->get_name();
		$image_id     = $product->get_image_id();
		$image_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
		$price        = (float) $product->get_price();
		if ( ! $price && $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			if ( ! empty( $prices['price'] ) ) {
				$price = (float) current( $prices['price'] );
			}
		}

		$device     = SnapOrder_Settings::get_sticky_buy_now_device();
		$btn_text   = SnapOrder_Settings::get_sticky_buy_now_text();
		$show_thumb = SnapOrder_Settings::get_sticky_buy_now_show_thumbnail();
		?>
		<!-- Sticky Buy Now Bar (WoodMart Styled) -->
		<div class="kbnw-sticky-bar kbnw-device-<?php echo esc_attr( $device ); ?>" id="kbnw-sticky-bar" role="complementary" aria-label="<?php esc_attr_e( 'Sticky Buy Now', 'snaporder-buy-now-for-woocommerce' ); ?>">
			<div class="kbnw-sticky-inner">
				<div class="kbnw-sticky-product-info">
					<?php if ( $show_thumb ) : ?>
						<div class="kbnw-sticky-thumb">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" id="kbnw-sticky-img" />
						</div>
					<?php endif; ?>
					<div class="kbnw-sticky-details">
						<div class="kbnw-sticky-title" id="kbnw-sticky-title"><?php echo esc_html( $product_name ); ?></div>
						<div class="kbnw-sticky-price" id="kbnw-sticky-price"><?php echo wp_kses_post( wc_price( $price ) ); ?></div>
					</div>
				</div>
				<div class="kbnw-sticky-action">
					<button type="button" class="kbnw-sticky-btn" id="kbnw-sticky-btn" aria-label="<?php echo esc_attr( $btn_text ); ?>">
						<span class="kbnw-sticky-icon">⚡</span>
						<span class="kbnw-sticky-text"><?php echo esc_html( $btn_text ); ?></span>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render WhatsApp Floating Button in wp_footer
	 */
	public static function render_floating_whatsapp_button() {
		if ( ! self::should_show_floating_wa() ) {
			return;
		}

		$position    = SnapOrder_Settings::get_floating_wa_position();
		$show_text   = SnapOrder_Settings::get_floating_wa_show_text();
		$btn_text    = SnapOrder_Settings::get_floating_wa_text();
		$raw_phone   = SnapOrder_Settings::get_floating_wa_number();
		$clean_phone = preg_replace( '/[^0-9]/', '', $raw_phone );
		$msg_tpl     = SnapOrder_Settings::get_floating_wa_default_msg();

		// Determine current URL and title
		$current_url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
		if ( empty( $current_url ) && isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			$current_url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( $_SERVER['HTTP_HOST'] ) . sanitize_text_field( $_SERVER['REQUEST_URI'] );
		}
		$page_title = wp_get_document_title();

		$replacements = array(
			'{page_url}'     => $current_url,
			'{link}'         => $current_url,
			'{page_title}'   => $page_title,
			'{site_name}'    => get_bloginfo( 'name' ),
			'{product_name}' => '',
			'{price}'        => '',
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( ! is_a( $product, 'WC_Product' ) ) {
				$product = wc_get_product( get_the_ID() );
			}
			if ( $product ) {
				$replacements['{product_name}'] = $product->get_name();
				$replacements['{price}']        = $product->get_price();
			}
		}

		$message = str_replace( array_keys( $replacements ), array_values( $replacements ), $msg_tpl );
		$wa_url  = 'https://wa.me/' . $clean_phone . ( ! empty( $message ) ? '?text=' . rawurlencode( trim( $message ) ) : '' );
		?>
		<!-- WhatsApp Floating Button -->
		<div class="kwa-floating-btn-wrap kwa-pos-<?php echo esc_attr( $position ); ?>" id="kwa-floating-whatsapp">
			<a href="<?php echo esc_url( $wa_url ); ?>" class="kwa-floating-btn <?php echo ( ! $show_text ) ? 'kwa-icon-only' : ''; ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $btn_text ? $btn_text : 'Chat on WhatsApp' ); ?>">
				<span class="kwa-pulse-ring" aria-hidden="true"></span>
				<span class="kwa-icon-wrap" aria-hidden="true">
					<svg viewBox="0 0 32 32" width="26" height="26" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
						<path d="M16.01 2.002C8.28 2.002 2 8.282 2 16.012c0 2.68.752 5.186 2.055 7.322L2.37 29.102a1 1 0 0 0 1.233 1.233l5.854-1.66A13.92 13.92 0 0 0 16.01 30.02c7.73 0 14.01-6.28 14.01-14.01 0-7.73-6.28-14.01-14.01-14.01zm0 25.518c-2.316 0-4.482-.693-6.297-1.883a1 1 0 0 0-.745-.133l-4.14 1.176 1.196-4.062a1 1 0 0 0-.154-.805A11.458 11.458 0 0 1 4.5 16.012c0-6.35 5.16-11.51 11.51-11.51s11.51 5.16 11.51 11.51-5.16 11.51-11.51 11.51zm6.85-8.18c-.38-.19-2.25-1.11-2.6-1.24-.35-.13-.6-.19-.86.19-.25.38-.98 1.24-1.2 1.5-.22.25-.45.28-.83.09-.38-.19-1.61-.59-3.07-1.89-1.13-1.01-1.9-2.26-2.12-2.64-.22-.38-.02-.58.17-.77.17-.17.38-.45.57-.67.19-.22.25-.38.38-.63.13-.25.06-.48-.03-.67-.09-.19-.86-2.07-1.18-2.83-.31-.74-.63-.64-.86-.65-.22-.01-.48-.01-.73-.01-.25 0-.67.09-1.02.48-.35.38-1.34 1.31-1.34 3.2 0 1.88 1.37 3.7 1.56 3.96.19.25 2.7 4.12 6.54 5.77.91.39 1.63.63 2.18.81.92.29 1.76.25 2.42.15.74-.11 2.25-.92 2.57-1.81.32-.89.32-1.65.22-1.81-.09-.16-.35-.25-.73-.44z"/>
					</svg>
				</span>
				<?php if ( $show_text && ! empty( $btn_text ) ) : ?>
					<span class="kwa-btn-text"><?php echo esc_html( $btn_text ); ?></span>
				<?php endif; ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render Quick Order Modal in footer
	 */
	public static function render_quick_order_modal() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}
		if ( ! $product->is_type( 'variable' ) && ! $product->is_in_stock() ) {
			return;
		}

		$product_id   = $product->get_id();
		$product_name = $product->get_name();
		$image_id     = $product->get_image_id();
		$image_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
		$price        = (float) $product->get_price();
		if ( ! $price && $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			if ( ! empty( $prices['price'] ) ) {
				$price = (float) current( $prices['price'] );
			}
		}

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		?>
		<!-- SnapOrder Quick Order Modal -->
		<div class="snaporder-modal-overlay" id="snaporder-quick-order-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="snaporder-modal-title">
			<div class="snaporder-modal-dialog">
				<!-- Close Button -->
				<button type="button" class="snaporder-modal-close" id="snaporder-modal-close-btn" aria-label="<?php esc_attr_e( 'Close Modal', 'snaporder-buy-now-for-woocommerce' ); ?>">&times;</button>

				<!-- Modal Content Wrapper -->
				<div class="snaporder-modal-body" id="snaporder-modal-body">
					<!-- Header -->
					<div class="snaporder-modal-header">
						<h2 class="snaporder-modal-title" id="snaporder-modal-title"><?php esc_html_e( 'Quick Order Checkout', 'snaporder-buy-now-for-woocommerce' ); ?></h2>
						<p class="snaporder-modal-subtitle"><?php esc_html_e( 'Fast delivery across all 7 Emirates. Pay easily on delivery or online.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Product Overview Strip -->
					<div class="snaporder-product-strip">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" class="snaporder-product-img" id="snaporder-product-img" />
						<div class="snaporder-product-meta">
							<h3 class="snaporder-product-title" id="snaporder-product-title"><?php echo esc_html( $product_name ); ?></h3>
							<div class="snaporder-variation-badge-wrap" id="snaporder-variation-badge-wrap" style="display:none;">
								<span class="snaporder-variation-badge" id="snaporder-variation-badge"></span>
							</div>
							<div class="snaporder-product-price-line">
								<span class="snaporder-price-label"><?php esc_html_e( 'Price:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								<strong class="snaporder-unit-price" id="snaporder-unit-price" data-unit-price="<?php echo esc_attr( $price ); ?>">
									<?php echo wp_kses_post( wc_price( $price ) ); ?>
								</strong>
							</div>
							<div class="snaporder-modal-addons-summary" id="snaporder-modal-addons-summary" style="display:none;">
								<span class="snaporder-modal-addons-title"><?php esc_html_e( 'Add-ons:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								<div class="snaporder-modal-addons-pills" id="snaporder-modal-addons-pills"></div>
							</div>
						</div>
					</div>

					<!-- Form Container -->
					<form id="snaporder-quick-order-form" class="snaporder-quick-order-form" novalidate>
						<input type="hidden" name="product_id" id="snaporder_product_id" value="<?php echo esc_attr( $product_id ); ?>" />
						<input type="hidden" name="variation_id" id="snaporder_variation_id" value="" />
						<input type="hidden" name="coupon_code" id="snaporder_coupon_code" value="" />
						<input type="hidden" name="action" value="snaporder_quick_order_submit" />

						<!-- Error Box -->
						<div class="snaporder-error-notice" id="snaporder-error-notice" style="display:none;" role="alert"></div>

						<!-- Field: Phone Number -->
						<div class="snaporder-form-group">
							<label for="snaporder_phone" class="snaporder-label">
								<?php esc_html_e( 'Mobile / Phone Number', 'snaporder-buy-now-for-woocommerce' ); ?>
								<span class="snaporder-required">*</span>
							</label>
							<div class="snaporder-input-wrapper snaporder-phone-wrapper">
								<span class="snaporder-phone-prefix">🇦🇪 +971</span>
								<input type="tel" id="snaporder_phone" name="phone" class="snaporder-input snaporder-input-phone" placeholder="50 123 4567" required autocomplete="tel" />
							</div>
							<span class="snaporder-field-error" id="snaporder-error-phone"></span>
						</div>

						<!-- Field: Full Name -->
						<div class="snaporder-form-group">
							<label for="snaporder_name" class="snaporder-label">
								<?php esc_html_e( 'Full Name', 'snaporder-buy-now-for-woocommerce' ); ?>
								<span class="snaporder-required">*</span>
							</label>
							<input type="text" id="snaporder_name" name="name" class="snaporder-input" placeholder="<?php esc_attr_e( 'e.g. Mohammed Al Hashimi', 'snaporder-buy-now-for-woocommerce' ); ?>" required autocomplete="name" />
							<span class="snaporder-field-error" id="snaporder-error-name"></span>
						</div>

						<!-- Field: City (Emirates) -->
						<div class="snaporder-form-group">
							<label for="snaporder_city" class="snaporder-label">
								<?php esc_html_e( 'City / Emirate', 'snaporder-buy-now-for-woocommerce' ); ?>
								<span class="snaporder-required">*</span>
							</label>
							<select id="snaporder_city" name="city" class="snaporder-select" required>
								<option value="Dubai"><?php esc_html_e( 'Dubai', 'snaporder-buy-now-for-woocommerce' ); ?></option>
								<option value="Abu Dhabi"><?php esc_html_e( 'Abu Dhabi', 'snaporder-buy-now-for-woocommerce' ); ?></option>
								<option value="Sharjah"><?php esc_html_e( 'Sharjah', 'snaporder-buy-now-for-woocommerce' ); ?></option>
								<option value="Ajman"><?php esc_html_e( 'Ajman', 'snaporder-buy-now-for-woocommerce' ); ?></option>
								<option value="Ras Al Khaimah"><?php esc_html_e( 'Ras Al Khaimah', 'snaporder-buy-now-for-woocommerce' ); ?></option>
								<option value="Fujairah"><?php esc_html_e( 'Fujairah', 'snaporder-buy-now-for-woocommerce' ); ?></option>
								<option value="Umm Al Quwain"><?php esc_html_e( 'Umm Al Quwain', 'snaporder-buy-now-for-woocommerce' ); ?></option>
							</select>
							<span class="snaporder-field-error" id="snaporder-error-city"></span>
						</div>

						<!-- Field: Full Address -->
						<div class="snaporder-form-group">
							<label for="snaporder_address" class="snaporder-label">
								<?php esc_html_e( 'Delivery Address', 'snaporder-buy-now-for-woocommerce' ); ?>
								<span class="snaporder-required">*</span>
							</label>
							<textarea id="snaporder_address" name="address" rows="2" class="snaporder-textarea" placeholder="<?php esc_attr_e( 'Building name, street, apartment / villa number...', 'snaporder-buy-now-for-woocommerce' ); ?>" required autocomplete="street-address"></textarea>
							<span class="snaporder-field-error" id="snaporder-error-address"></span>
						</div>

						<!-- Quantity Stepper -->
						<div class="snaporder-form-group snaporder-stepper-group">
							<label class="snaporder-label"><?php esc_html_e( 'Quantity', 'snaporder-buy-now-for-woocommerce' ); ?></label>
							<div class="snaporder-stepper">
								<button type="button" class="snaporder-stepper-btn snaporder-stepper-minus" id="snaporder-qty-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'snaporder-buy-now-for-woocommerce' ); ?>">&minus;</button>
								<input type="number" id="snaporder_qty" name="qty" class="snaporder-stepper-input" value="1" min="1" step="1" inputmode="numeric" />
								<button type="button" class="snaporder-stepper-btn snaporder-stepper-plus" id="snaporder-qty-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'snaporder-buy-now-for-woocommerce' ); ?>">&plus;</button>
							</div>
						</div>

						<!-- Payment Methods Radio Group -->
						<div class="snaporder-form-group snaporder-payment-methods-group">
							<label class="snaporder-label"><?php esc_html_e( 'Payment Method', 'snaporder-buy-now-for-woocommerce' ); ?></label>
							<div class="snaporder-payment-options">
								<?php
								$selected_set = false;
								if ( ! empty( $available_gateways ) ) {
									// If COD exists, make it first
									if ( isset( $available_gateways['cod'] ) ) {
										$cod_gw = $available_gateways['cod'];
										unset( $available_gateways['cod'] );
										$available_gateways = array( 'cod' => $cod_gw ) + $available_gateways;
									}

									foreach ( $available_gateways as $gateway_id => $gateway ) {
										$is_checked   = ! $selected_set;
										$selected_set = true;
										?>
										<label class="snaporder-payment-card <?php echo $is_checked ? 'active' : ''; ?>">
											<input type="radio" name="payment_method" value="<?php echo esc_attr( $gateway_id ); ?>" <?php checked( $is_checked, true ); ?> />
											<span class="snaporder-radio-custom"></span>
											<span class="snaporder-payment-info">
												<strong class="snaporder-payment-title"><?php echo esc_html( $gateway->get_title() ); ?></strong>
												<?php if ( 'cod' === $gateway_id ) : ?>
													<span class="snaporder-payment-badge"><?php esc_html_e( 'Popular', 'snaporder-buy-now-for-woocommerce' ); ?></span>
												<?php endif; ?>
											</span>
										</label>
										<?php
									}
								} else {
									?>
									<label class="snaporder-payment-card active">
										<input type="radio" name="payment_method" value="cod" checked="checked" />
										<span class="snaporder-radio-custom"></span>
										<span class="snaporder-payment-info">
											<strong class="snaporder-payment-title"><?php esc_html_e( 'Cash on Delivery', 'snaporder-buy-now-for-woocommerce' ); ?></strong>
											<span class="snaporder-payment-badge"><?php esc_html_e( 'Popular', 'snaporder-buy-now-for-woocommerce' ); ?></span>
										</span>
									</label>
									<?php
								}
								?>
							</div>
						</div>

						<!-- Coupon Code Section -->
						<div class="snaporder-coupon-section" id="snaporder-coupon-section">
							<button type="button" class="snaporder-coupon-toggle" id="snaporder-coupon-toggle" aria-expanded="false">
								<span class="snaporder-coupon-toggle-text">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
										<line x1="7" y1="7" x2="7.01" y2="7"></line>
									</svg>
									<?php esc_html_e( 'Have a coupon code?', 'snaporder-buy-now-for-woocommerce' ); ?>
								</span>
								<span class="snaporder-coupon-toggle-arrow" id="snaporder-coupon-toggle-arrow">▾</span>
							</button>
							<div class="snaporder-coupon-collapse" id="snaporder-coupon-collapse" style="display: none;">
								<div class="snaporder-coupon-input-group">
									<input type="text" id="snaporder_coupon_input" class="snaporder-input snaporder-coupon-input" placeholder="<?php esc_attr_e( 'Coupon Code', 'snaporder-buy-now-for-woocommerce' ); ?>" autocomplete="off" />
									<button type="button" id="snaporder-apply-coupon-btn" class="snaporder-btn-coupon-apply">
										<span class="snaporder-coupon-btn-spinner" id="snaporder-coupon-btn-spinner" style="display:none;" aria-hidden="true"></span>
										<span class="snaporder-coupon-btn-text" id="snaporder-coupon-btn-text"><?php esc_html_e( 'Apply', 'snaporder-buy-now-for-woocommerce' ); ?></span>
									</button>
								</div>
								<div class="snaporder-coupon-feedback" id="snaporder-coupon-feedback" style="display: none;"></div>
							</div>
							<div class="snaporder-applied-coupon-pill" id="snaporder-applied-coupon-pill" style="display: none;">
								<div class="snaporder-coupon-badge">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
									<span id="snaporder-applied-coupon-name"></span>
									<span class="snaporder-applied-coupon-disc" id="snaporder-applied-coupon-disc"></span>
								</div>
								<button type="button" class="snaporder-coupon-remove-btn" id="snaporder-remove-coupon-btn" aria-label="<?php esc_attr_e( 'Remove coupon', 'snaporder-buy-now-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Remove coupon', 'snaporder-buy-now-for-woocommerce' ); ?>">&times;</button>
							</div>
						</div>

						<!-- Order Calculation Summary Box -->
						<div class="snaporder-order-summary">
							<div class="snaporder-summary-row">
								<span><?php esc_html_e( 'Subtotal', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								<span id="snaporder-summary-subtotal"><?php echo wp_kses_post( wc_price( $price ) ); ?></span>
							</div>
							<div class="snaporder-summary-row snaporder-summary-addons-row" id="snaporder-summary-addons-row" style="display: none;">
								<span id="snaporder-summary-addons-label"><?php esc_html_e( 'Add-ons & Services', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								<span id="snaporder-summary-addons-amount" class="snaporder-summary-addons-val"></span>
							</div>
							<div class="snaporder-summary-row snaporder-summary-discount-row" id="snaporder-summary-discount-row" style="display: none;">
								<span id="snaporder-summary-discount-label"><?php esc_html_e( 'Coupon Discount', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								<span id="snaporder-summary-discount-amount" class="snaporder-summary-discount-val"></span>
							</div>
							<div class="snaporder-summary-row" id="snaporder-summary-shipping-row">
								<span id="snaporder-summary-shipping-label"><?php esc_html_e( 'Delivery', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								<span id="snaporder-summary-shipping" class="snaporder-summary-shipping-value">
									<span class="snaporder-shipping-spinner" id="snaporder-shipping-spinner" style="display:none;" aria-hidden="true"></span>
									<span id="snaporder-summary-shipping-cost"><?php esc_html_e( 'Calculating...', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</span>
							</div>
							<div class="snaporder-summary-row snaporder-summary-total-row">
								<strong><?php esc_html_e( 'Total Amount', 'snaporder-buy-now-for-woocommerce' ); ?></strong>
								<strong class="snaporder-summary-total" id="snaporder-summary-total"><?php echo wp_kses_post( wc_price( $price ) ); ?></strong>
							</div>
						</div>

						<div class="snaporder-shipping-notice" id="snaporder-shipping-unavailable-notice" style="display:none;"></div>

						<!-- Submit Button -->
						<button type="submit" class="snaporder-modal-submit kbnw-complete-purchase-btn" id="snaporder-complete-purchase-btn">
							<span class="snaporder-submit-spinner" style="display:none;" aria-hidden="true"></span>
							<span class="snaporder-submit-text"><?php esc_html_e( 'Complete Purchase', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							<span class="snaporder-submit-amount" id="snaporder-submit-amount"><?php echo wp_kses_post( wc_price( $price ) ); ?></span>
						</button>

						<p class="snaporder-security-note">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:4px;">
								<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
							</svg>
							<?php esc_html_e( '100% Safe & Secure Order. No online prepayment required for COD.', 'snaporder-buy-now-for-woocommerce' ); ?>
						</p>
					</form>

					<!-- Confirmation State (Hidden until Order Created) -->
					<div class="snaporder-success-container" id="snaporder-success-container" style="display:none;">
						<!-- Injected dynamically on successful COD creation -->
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Meta Pixel base script ONLY if manually specified and no GTM/Pixel is present
	 */
	public static function maybe_render_meta_pixel() {
		$pixel_id = SnapOrder_Settings::get_pixel_id();
		if ( empty( $pixel_id ) ) {
			return;
		}
		?>
		<!-- SnapOrder Meta Pixel Base Code -->
		<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?php echo esc_js( $pixel_id ); ?>');
		fbq('track', 'PageView');
		</script>
		<noscript><img height="1" width="1" style="display:none"
		src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pixel_id ); ?>&ev=PageView&noscript=1"
		/></noscript>
		<?php
	}
}
