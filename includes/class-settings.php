<?php
/**
 * Plugin Settings Class
 *
 * @package snaporder-buy-now-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SnapOrder_Settings {

	/**
	 * Initialize settings hooks
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add menu page under WooCommerce
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'SnapOrder Buy Now Settings', 'snaporder-buy-now-for-woocommerce' ),
			__( 'SnapOrder Buy Now', 'snaporder-buy-now-for-woocommerce' ),
			'manage_woocommerce',
			'snaporder-buy-now-whatsapp',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register settings and sanitize callbacks
	 */
	public static function register_settings() {
		// 1. General / Product Page settings
		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_chat_number',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_phone_number' ),
				'default'           => '',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_chat_message_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => "Hi, I want to order this product:\n{product_name}\nQty: {qty}\nPrice: {price} AED\nLink: {link}",
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_cod_order_status',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => 'processing',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_meta_pixel_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// 2. Sticky Buy Now settings
		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_sticky_buy_now_enabled',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_sticky_buy_now_device',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_device' ),
				'default'           => 'both',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_sticky_buy_now_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Buy Now',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_sticky_buy_now_show_thumbnail',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
			)
		);

		// 3. WhatsApp Floating Button settings
		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_enabled',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_position',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_position' ),
				'default'           => 'right',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_show_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Need help?',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_number',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_phone_number' ),
				'default'           => '',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_default_msg',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => 'Hello! I need help regarding your store.',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_display_rule',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_display_rule' ),
				'default'           => 'all',
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_include_pages',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_array' ),
				'default'           => array(),
			)
		);

		register_setting(
			'snaporder_bnw_settings_group',
			'snaporder_floating_chat_exclude_pages',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_array' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize Yes/No values
	 */
	public static function sanitize_yes_no( $val ) {
		return ( 'yes' === $val || '1' === $val || 'true' === $val ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize Device values
	 */
	public static function sanitize_device( $val ) {
		return in_array( $val, array( 'both', 'mobile', 'desktop' ), true ) ? $val : 'both';
	}

	/**
	 * Sanitize Position value
	 */
	public static function sanitize_position( $val ) {
		return in_array( $val, array( 'right', 'left' ), true ) ? $val : 'right';
	}

	/**
	 * Sanitize Display Rule value
	 */
	public static function sanitize_display_rule( $val ) {
		return in_array( $val, array( 'all', 'specific' ), true ) ? $val : 'all';
	}

	/**
	 * Sanitize Page Selection array
	 */
	public static function sanitize_page_array( $val ) {
		if ( ! is_array( $val ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', array_filter( $val ) );
	}

	/**
	 * Sanitize WhatsApp phone number
	 *
	 * @param string $number Raw number
	 * @return string Cleaned number
	 */
	public static function sanitize_phone_number( $number ) {
		$number = sanitize_text_field( $number );
		if ( empty( $number ) ) {
			return '';
		}
		$has_plus = ( strpos( $number, '+' ) === 0 );
		$digits   = preg_replace( '/[^0-9]/', '', $number );
		return ( $has_plus ? '+' : '' ) . $digits;
	}

	/**
	 * Helper: Get WhatsApp number
	 *
	 * @return string
	 */
	public static function get_whatsapp_number() {
		$num = get_option( 'snaporder_chat_number', '' );
		return ! empty( $num ) ? $num : '';
	}

	/**
	 * Helper: Get WhatsApp message template
	 *
	 * @return string
	 */
	public static function get_whatsapp_template() {
		$default = "Hi, I want to order this product:\n{product_name}\nQty: {qty}\nPrice: {price} AED\nLink: {link}";
		$tpl     = get_option( 'snaporder_chat_message_template', $default );
		return ! empty( $tpl ) ? $tpl : $default;
	}

	/**
	 * Helper: Get COD default status
	 *
	 * @return string
	 */
	public static function get_cod_status() {
		$status = get_option( 'snaporder_cod_order_status', 'processing' );
		return in_array( $status, array( 'processing', 'on-hold', 'completed', 'pending' ), true ) ? $status : 'processing';
	}

	/**
	 * Helper: Get Meta Pixel ID
	 *
	 * @return string
	 */
	public static function get_pixel_id() {
		return trim( (string) get_option( 'snaporder_meta_pixel_id', '' ) );
	}

	/**
	 * Helper: Is Sticky Buy Now Enabled
	 *
	 * @return bool
	 */
	public static function is_sticky_buy_now_enabled() {
		$val = get_option( 'snaporder_sticky_buy_now_enabled', 'yes' );
		return ( 'yes' === $val || '1' === $val || true === $val );
	}

	/**
	 * Helper: Get Sticky Buy Now Device ('both', 'mobile', 'desktop')
	 *
	 * @return string
	 */
	public static function get_sticky_buy_now_device() {
		$val = get_option( 'snaporder_sticky_buy_now_device', 'both' );
		return in_array( $val, array( 'both', 'mobile', 'desktop' ), true ) ? $val : 'both';
	}

	/**
	 * Helper: Get Sticky Buy Now Text
	 *
	 * @return string
	 */
	public static function get_sticky_buy_now_text() {
		$val = get_option( 'snaporder_sticky_buy_now_text', 'Buy Now' );
		return ! empty( $val ) ? $val : 'Buy Now';
	}

	/**
	 * Helper: Show Thumbnail in Sticky Bar
	 *
	 * @return bool
	 */
	public static function get_sticky_buy_now_show_thumbnail() {
		$val = get_option( 'snaporder_sticky_buy_now_show_thumbnail', 'yes' );
		return ( 'yes' === $val || '1' === $val || true === $val );
	}

	/**
	 * Helper: Is Floating WhatsApp Enabled
	 *
	 * @return bool
	 */
	public static function is_floating_wa_enabled() {
		$val = get_option( 'snaporder_floating_chat_enabled', 'yes' );
		return ( 'yes' === $val || '1' === $val || true === $val );
	}

	/**
	 * Helper: Get Floating WhatsApp Position ('right' or 'left')
	 *
	 * @return string
	 */
	public static function get_floating_wa_position() {
		$val = get_option( 'snaporder_floating_chat_position', 'right' );
		return in_array( $val, array( 'right', 'left' ), true ) ? $val : 'right';
	}

	/**
	 * Helper: Show Text Next to Floating Icon
	 *
	 * @return bool
	 */
	public static function get_floating_wa_show_text() {
		$val = get_option( 'snaporder_floating_chat_show_text', 'yes' );
		return ( 'yes' === $val || '1' === $val || true === $val );
	}

	/**
	 * Helper: Get Floating WhatsApp Button Text
	 *
	 * @return string
	 */
	public static function get_floating_wa_text() {
		$val = get_option( 'snaporder_floating_chat_text', 'Need help?' );
		return ! empty( $val ) ? $val : 'Need help?';
	}

	/**
	 * Helper: Get Floating WhatsApp Phone Number (falls back to main store number)
	 *
	 * @return string
	 */
	public static function get_floating_wa_number() {
		$custom = get_option( 'snaporder_floating_chat_number', '' );
		if ( ! empty( $custom ) ) {
			return $custom;
		}
		return self::get_whatsapp_number();
	}

	/**
	 * Helper: Get Floating WhatsApp Default Message
	 *
	 * @return string
	 */
	public static function get_floating_wa_default_msg() {
		$val = get_option( 'snaporder_floating_chat_default_msg', 'Hello! I need help regarding your store ({page_url})' );
		return ! empty( $val ) ? $val : 'Hello! I need help regarding your store ({page_url})';
	}

	/**
	 * Helper: Get Floating WhatsApp Display Rule ('all' or 'specific')
	 *
	 * @return string
	 */
	public static function get_floating_wa_display_rule() {
		$val = get_option( 'snaporder_floating_chat_display_rule', 'all' );
		return in_array( $val, array( 'all', 'specific' ), true ) ? $val : 'all';
	}

	/**
	 * Helper: Get Included Pages
	 *
	 * @return array
	 */
	public static function get_floating_wa_include_pages() {
		$val = get_option( 'snaporder_floating_chat_include_pages', array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Helper: Get Excluded Pages
	 *
	 * @return array
	 */
	public static function get_floating_wa_exclude_pages() {
		$val = get_option( 'snaporder_floating_chat_exclude_pages', array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Render settings page HTML
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$all_pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);

		// Sticky Buy Now Options
		$sticky_enabled     = self::is_sticky_buy_now_enabled();
		$sticky_device      = self::get_sticky_buy_now_device();
		$sticky_text        = self::get_sticky_buy_now_text();
		$sticky_show_thumb  = self::get_sticky_buy_now_show_thumbnail();

		// Floating WhatsApp Options
		$include_pages = self::get_floating_wa_include_pages();
		$exclude_pages = self::get_floating_wa_exclude_pages();
		$display_rule  = self::get_floating_wa_display_rule();
		$wa_position   = self::get_floating_wa_position();
		$show_text     = self::get_floating_wa_show_text();
		$btn_text      = self::get_floating_wa_text();
		$wa_enabled    = self::is_floating_wa_enabled();
		?>
		<div class="wrap kwa-settings-wrap">
			<style>
				.kwa-settings-wrap { max-width: 1080px; margin-top: 20px; }
				.kwa-card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-radius: 8px; padding: 24px 28px; margin-bottom: 24px; }
				.kwa-card-header { border-bottom: 1px solid #eee; padding-bottom: 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
				.kwa-card-header h2 { margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
				.kwa-badge-pill { font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 12px; text-transform: uppercase; }
				.kwa-form-row { margin-bottom: 20px; }
				.kwa-form-row:last-child { margin-bottom: 0; }
				.kwa-label { display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 13.5px; }
				.kwa-desc { color: #64748b; font-size: 12.5px; margin-top: 4px; line-height: 1.4; }
				.kwa-radio-group { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 6px; }
				.kwa-radio-card { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; }
				.kwa-radio-card:hover { border-color: #cbd5e1; background: #f1f5f9; }
				.kwa-radio-card.active { border-color: #0f172a; background: #f1f5f9; font-weight: 600; }
				.kwa-radio-card.active-wa { border-color: #25D366; background: #f0fdf4; font-weight: 600; }
				.kwa-pages-box { max-height: 180px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; }
				.kwa-page-checkbox-item { display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 13px; color: #334155; }
				.kwa-page-checkbox-item input { margin: 0; }
				.kwa-preview-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 16px; display: flex; align-items: center; justify-content: space-between; margin-top: 14px; }
				.kwa-preview-badge { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: #fff; padding: 9px 18px 9px 12px; border-radius: 50px; font-weight: 600; font-size: 14px; box-shadow: 0 4px 14px rgba(37,211,102,0.35); }
				.kwa-preview-badge svg { fill: #fff; width: 22px; height: 22px; }
				.kbnw-sticky-preview { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: 480px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
				.kbnw-sticky-preview-btn { background: #0F172A; color: #fff; border: none; padding: 8px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
			</style>

			<h1 style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
				<span>⚡</span> <?php esc_html_e( 'SnapOrder Buy Now for WooCommerce Settings', 'snaporder-buy-now-for-woocommerce' ); ?>
			</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'snaporder_bnw_settings_group' );
				do_settings_sections( 'snaporder_bnw_settings_group' );
				?>

				<!-- CARD 1: Sticky Buy Now Button Settings (WoodMart Inspired) -->
				<div class="kwa-card">
					<div class="kwa-card-header">
						<h2>
							<span>📌</span>
							<?php esc_html_e( 'Sticky Buy Now Button (Product Pages)', 'snaporder-buy-now-for-woocommerce' ); ?>
						</h2>
						<span class="kwa-badge-pill" style="background:#fef3c7;color:#92400e;"><?php esc_html_e( 'High Conversion', 'snaporder-buy-now-for-woocommerce' ); ?></span>
					</div>

					<!-- Turn On / Off Toggle -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Sticky Buy Now Status', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
							<input type="checkbox" name="snaporder_sticky_buy_now_enabled" id="snaporder_sticky_buy_now_enabled" value="yes" <?php checked( $sticky_enabled, true ); ?> />
							<strong><?php esc_html_e( 'Enable Sticky Buy Now bar on Single Product pages (Slides up when scrolling)', 'snaporder-buy-now-for-woocommerce' ); ?></strong>
						</label>
						<p class="kwa-desc"><?php esc_html_e( 'When the main add-to-cart area scrolls out of view, a sleek WoodMart-style bottom bar appears with product details and a direct Buy Now CTA.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Device Selection: Mobile / Desktop / Both -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Device Display Options', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<div class="kwa-radio-group">
							<label class="kwa-radio-card <?php echo ( 'both' === $sticky_device ) ? 'active' : ''; ?>">
								<input type="radio" name="snaporder_sticky_buy_now_device" value="both" <?php checked( $sticky_device, 'both' ); ?> onchange="kbnwUpdateRadioCard(this);" />
								<span>📱💻 <?php esc_html_e( 'Both Mobile & Desktop', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							</label>
							<label class="kwa-radio-card <?php echo ( 'mobile' === $sticky_device ) ? 'active' : ''; ?>">
								<input type="radio" name="snaporder_sticky_buy_now_device" value="mobile" <?php checked( $sticky_device, 'mobile' ); ?> onchange="kbnwUpdateRadioCard(this);" />
								<span>📱 <?php esc_html_e( 'Mobile Only (Recommended)', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							</label>
							<label class="kwa-radio-card <?php echo ( 'desktop' === $sticky_device ) ? 'active' : ''; ?>">
								<input type="radio" name="snaporder_sticky_buy_now_device" value="desktop" <?php checked( $sticky_device, 'desktop' ); ?> onchange="kbnwUpdateRadioCard(this);" />
								<span>💻 <?php esc_html_e( 'Desktop Only', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							</label>
						</div>
						<p class="kwa-desc"><?php esc_html_e( 'Choose whether to show the sticky bottom bar on Mobile phones only, Desktop only, or all devices.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Sticky Button Text -->
					<div class="kwa-form-row">
						<label for="snaporder_sticky_buy_now_text" class="kwa-label"><?php esc_html_e( 'Sticky Button Text', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<input type="text" name="snaporder_sticky_buy_now_text" id="snaporder_sticky_buy_now_text" value="<?php echo esc_attr( $sticky_text ); ?>" class="regular-text" placeholder="Buy Now" oninput="kbnwUpdateStickyPreview();" />
						<p class="kwa-desc"><?php esc_html_e( 'Default is "Buy Now". You can also use "⚡ Buy Now", "Quick Order", etc.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Show Thumbnail & Details -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Product Info Display', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
							<input type="checkbox" name="snaporder_sticky_buy_now_show_thumbnail" id="snaporder_sticky_buy_now_show_thumbnail" value="yes" <?php checked( $sticky_show_thumb, true ); ?> />
							<span><?php esc_html_e( 'Show product thumbnail image alongside title & price in the sticky bar', 'snaporder-buy-now-for-woocommerce' ); ?></span>
						</label>
					</div>

					<!-- Live Preview -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Sticky Bar Preview', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<div class="kwa-preview-box">
							<div class="kbnw-sticky-preview">
								<div style="display:flex;align-items:center;gap:10px;">
									<div style="width:36px;height:36px;background:#e2e8f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:16px;">🛍️</div>
									<div>
										<div style="font-weight:600;font-size:13px;color:#0f172a;line-height:1.2;">Sample Product Title</div>
										<div style="font-weight:700;font-size:13.5px;color:#0f172a;">AED 1,999.00</div>
									</div>
								</div>
								<div class="kbnw-sticky-preview-btn">
									<span>⚡</span>
									<span id="kbnw-sticky-preview-text"><?php echo esc_html( $sticky_text ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- CARD 2: WhatsApp Floating Button Settings -->
				<div class="kwa-card">
					<div class="kwa-card-header">
						<h2>
							<svg width="20" height="20" viewBox="0 0 32 32" fill="#25D366"><path d="M16.01 2.002C8.28 2.002 2 8.282 2 16.012c0 2.68.752 5.186 2.055 7.322L2.37 29.102a1 1 0 0 0 1.233 1.233l5.854-1.66A13.92 13.92 0 0 0 16.01 30.02c7.73 0 14.01-6.28 14.01-14.01 0-7.73-6.28-14.01-14.01-14.01zm0 25.518c-2.316 0-4.482-.693-6.297-1.883a1 1 0 0 0-.745-.133l-4.14 1.176 1.196-4.062a1 1 0 0 0-.154-.805A11.458 11.458 0 0 1 4.5 16.012c0-6.35 5.16-11.51 11.51-11.51s11.51 5.16 11.51 11.51-5.16 11.51-11.51 11.51zm6.85-8.18c-.38-.19-2.25-1.11-2.6-1.24-.35-.13-.6-.19-.86.19-.25.38-.98 1.24-1.2 1.5-.22.25-.45.28-.83.09-.38-.19-1.61-.59-3.07-1.89-1.13-1.01-1.9-2.26-2.12-2.64-.22-.38-.02-.58.17-.77.17-.17.38-.45.57-.67.19-.22.25-.38.38-.63.13-.25.06-.48-.03-.67-.09-.19-.86-2.07-1.18-2.83-.31-.74-.63-.64-.86-.65-.22-.01-.48-.01-.73-.01-.25 0-.67.09-1.02.48-.35.38-1.34 1.31-1.34 3.2 0 1.88 1.37 3.7 1.56 3.96.19.25 2.7 4.12 6.54 5.77.91.39 1.63.63 2.18.81.92.29 1.76.25 2.42.15.74-.11 2.25-.92 2.57-1.81.32-.89.32-1.65.22-1.81-.09-.16-.35-.25-.73-.44z"/></svg>
							<?php esc_html_e( 'WhatsApp Floating Button', 'snaporder-buy-now-for-woocommerce' ); ?>
						</h2>
						<span class="kwa-badge-pill"><?php esc_html_e( 'Speed Optimized', 'snaporder-buy-now-for-woocommerce' ); ?></span>
					</div>

					<!-- Enable / Disable Toggle -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Floating WhatsApp Button Status', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
							<input type="checkbox" name="snaporder_floating_chat_enabled" id="snaporder_floating_chat_enabled" value="yes" <?php checked( $wa_enabled, true ); ?> />
							<strong><?php esc_html_e( 'Enable WhatsApp Floating Button across website', 'snaporder-buy-now-for-woocommerce' ); ?></strong>
						</label>
						<p class="kwa-desc"><?php esc_html_e( 'Uses ultra-lightweight inline/cached CSS with zero impact on Google PageSpeed & Core Web Vitals.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Position: Right / Left -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Button Position on Screen', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<div class="kwa-radio-group">
							<label class="kwa-radio-card <?php echo ( 'right' === $wa_position ) ? 'active-wa' : ''; ?>">
								<input type="radio" name="snaporder_floating_chat_position" value="right" <?php checked( $wa_position, 'right' ); ?> onchange="kwaUpdateWaRadioCard(this);" />
								<span>👉 <?php esc_html_e( 'Right Side (Bottom Right)', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							</label>
							<label class="kwa-radio-card <?php echo ( 'left' === $wa_position ) ? 'active-wa' : ''; ?>">
								<input type="radio" name="snaporder_floating_chat_position" value="left" <?php checked( $wa_position, 'left' ); ?> onchange="kwaUpdateWaRadioCard(this);" />
								<span>👈 <?php esc_html_e( 'Left Side (Bottom Left)', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							</label>
						</div>
					</div>

					<!-- Show Text Option & Text Input -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Floating Button Text', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<div style="margin-bottom:10px;">
							<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
								<input type="checkbox" name="snaporder_floating_chat_show_text" id="snaporder_floating_chat_show_text" value="yes" <?php checked( $show_text, true ); ?> onchange="kwaUpdatePreview();" />
								<span><?php esc_html_e( 'Show text label next to WhatsApp icon', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							</label>
						</div>
						<div id="kwa-text-input-wrap" style="<?php echo ( ! $show_text ) ? 'display:none;' : ''; ?>">
							<input type="text" name="snaporder_floating_chat_text" id="snaporder_floating_chat_text" value="<?php echo esc_attr( $btn_text ); ?>" class="regular-text" placeholder="Need help?" oninput="kwaUpdatePreview();" />
							<p class="kwa-desc"><?php esc_html_e( 'e.g. "Need help?", "Chat with Us", "WhatsApp Us"', 'snaporder-buy-now-for-woocommerce' ); ?></p>
						</div>
					</div>

					<!-- Custom Phone Number for Floating Button -->
					<div class="kwa-form-row">
						<label for="snaporder_floating_chat_number" class="kwa-label"><?php esc_html_e( 'Floating Button WhatsApp Number (Optional)', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<input type="text" name="snaporder_floating_chat_number" id="snaporder_floating_chat_number" value="<?php echo esc_attr( get_option( 'snaporder_floating_chat_number', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( self::get_whatsapp_number() ); ?>" />
						<p class="kwa-desc"><?php esc_html_e( 'Leave empty to automatically use the main store WhatsApp number.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Default WhatsApp Message for Floating Button -->
					<div class="kwa-form-row">
						<label for="snaporder_floating_chat_default_msg" class="kwa-label"><?php esc_html_e( 'Floating Button WhatsApp Message', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<textarea name="snaporder_floating_chat_default_msg" id="snaporder_floating_chat_default_msg" rows="3" class="large-text code"><?php echo esc_textarea( self::get_floating_wa_default_msg() ); ?></textarea>
						<p class="kwa-desc"><?php esc_html_e( 'Placeholders available: {page_url}, {page_title}, {site_name}, {product_name}', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<!-- Where to Show (Display Rules) -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Where to Show Floating WhatsApp Button', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<div class="kwa-radio-group" style="margin-bottom:12px;">
							<label class="kwa-radio-card <?php echo ( 'all' === $display_rule ) ? 'active-wa' : ''; ?>">
								<input type="radio" name="snaporder_floating_chat_display_rule" value="all" <?php checked( $display_rule, 'all' ); ?> onchange="kwaToggleDisplayRule(this.value);" />
								<strong>🌐 <?php esc_html_e( 'Entire Website (All Pages)', 'snaporder-buy-now-for-woocommerce' ); ?></strong>
							</label>
							<label class="kwa-radio-card <?php echo ( 'specific' === $display_rule ) ? 'active-wa' : ''; ?>">
								<input type="radio" name="snaporder_floating_chat_display_rule" value="specific" <?php checked( $display_rule, 'specific' ); ?> onchange="kwaToggleDisplayRule(this.value);" />
								<strong>🎯 <?php esc_html_e( 'Specific Pages / Sections Only', 'snaporder-buy-now-for-woocommerce' ); ?></strong>
							</label>
						</div>

						<!-- Specific Pages Box (when "Specific Pages Only" is selected) -->
						<div id="kwa-include-pages-wrap" style="<?php echo ( 'specific' !== $display_rule ) ? 'display:none;' : ''; ?> margin-bottom:16px;">
							<label class="kwa-label" style="font-size:12.5px;color:#0369a1;"><?php esc_html_e( 'Select Pages / Sections to SHOW Floating Button:', 'snaporder-buy-now-for-woocommerce' ); ?></label>
							<div class="kwa-pages-box">
								<!-- Special Section Checkboxes -->
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_include_pages[]" value="_special_home" <?php checked( in_array( '_special_home', $include_pages, true ), true ); ?> />
									<span>🏠 <?php esc_html_e( 'Homepage / Front Page', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_include_pages[]" value="_special_products" <?php checked( in_array( '_special_products', $include_pages, true ), true ); ?> />
									<span>🛍️ <?php esc_html_e( 'All Single Product Pages', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_include_pages[]" value="_special_shop" <?php checked( in_array( '_special_shop', $include_pages, true ), true ); ?> />
									<span>🏪 <?php esc_html_e( 'Shop & Category Archive Pages', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_include_pages[]" value="_special_blog" <?php checked( in_array( '_special_blog', $include_pages, true ), true ); ?> />
									<span>📰 <?php esc_html_e( 'Blog & Article Pages', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>

								<!-- Individual Published Pages -->
								<?php if ( ! empty( $all_pages ) ) : ?>
									<?php foreach ( $all_pages as $page_item ) : ?>
										<div class="kwa-page-checkbox-item">
											<input type="checkbox" name="snaporder_floating_chat_include_pages[]" value="<?php echo esc_attr( $page_item->ID ); ?>" <?php checked( in_array( (string) $page_item->ID, $include_pages, true ), true ); ?> />
											<span>📄 <?php echo esc_html( $page_item->post_title ); ?> <small style="color:#94a3b8;">(ID: <?php echo esc_html( $page_item->ID ); ?>)</small></span>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>

						<!-- Exclude Pages Box -->
						<div id="kwa-exclude-pages-wrap" style="margin-top:12px;">
							<label class="kwa-label" style="font-size:12.5px;color:#b91c1c;"><?php esc_html_e( 'Exclude Pages (Do NOT show on these pages):', 'snaporder-buy-now-for-woocommerce' ); ?></label>
							<div class="kwa-pages-box">
								<!-- Special Section Checkboxes for Exclusion -->
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_exclude_pages[]" value="_special_checkout" <?php checked( in_array( '_special_checkout', $exclude_pages, true ), true ); ?> />
									<span>💳 <?php esc_html_e( 'Checkout Page', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_exclude_pages[]" value="_special_cart" <?php checked( in_array( '_special_cart', $exclude_pages, true ), true ); ?> />
									<span>🛒 <?php esc_html_e( 'Cart Page', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>
								<div class="kwa-page-checkbox-item" style="font-weight:600;border-bottom:1px dashed #cbd5e1;padding-bottom:6px;margin-bottom:6px;">
									<input type="checkbox" name="snaporder_floating_chat_exclude_pages[]" value="_special_products" <?php checked( in_array( '_special_products', $exclude_pages, true ), true ); ?> />
									<span>🛍️ <?php esc_html_e( 'Single Product Pages', 'snaporder-buy-now-for-woocommerce' ); ?></span>
								</div>

								<!-- Individual Published Pages -->
								<?php if ( ! empty( $all_pages ) ) : ?>
									<?php foreach ( $all_pages as $page_item ) : ?>
										<div class="kwa-page-checkbox-item">
											<input type="checkbox" name="snaporder_floating_chat_exclude_pages[]" value="<?php echo esc_attr( $page_item->ID ); ?>" <?php checked( in_array( (string) $page_item->ID, $exclude_pages, true ), true ); ?> />
											<span>📄 <?php echo esc_html( $page_item->post_title ); ?> <small style="color:#94a3b8;">(ID: <?php echo esc_html( $page_item->ID ); ?>)</small></span>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<p class="kwa-desc"><?php esc_html_e( 'Selected pages will never display the floating WhatsApp button.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
						</div>
					</div>

					<!-- Live Preview -->
					<div class="kwa-form-row">
						<label class="kwa-label"><?php esc_html_e( 'Live WhatsApp Button Preview', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<div class="kwa-preview-box">
							<span style="color:#166534;font-weight:500;font-size:13px;"><?php esc_html_e( 'Official WhatsApp Design:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
							<div class="kwa-preview-badge" id="kwa-preview-badge">
								<svg viewBox="0 0 32 32"><path d="M16.01 2.002C8.28 2.002 2 8.282 2 16.012c0 2.68.752 5.186 2.055 7.322L2.37 29.102a1 1 0 0 0 1.233 1.233l5.854-1.66A13.92 13.92 0 0 0 16.01 30.02c7.73 0 14.01-6.28 14.01-14.01 0-7.73-6.28-14.01-14.01-14.01zm0 25.518c-2.316 0-4.482-.693-6.297-1.883a1 1 0 0 0-.745-.133l-4.14 1.176 1.196-4.062a1 1 0 0 0-.154-.805A11.458 11.458 0 0 1 4.5 16.012c0-6.35 5.16-11.51 11.51-11.51s11.51 5.16 11.51 11.51-5.16 11.51-11.51 11.51zm6.85-8.18c-.38-.19-2.25-1.11-2.6-1.24-.35-.13-.6-.19-.86.19-.25.38-.98 1.24-1.2 1.5-.22.25-.45.28-.83.09-.38-.19-1.61-.59-3.07-1.89-1.13-1.01-1.9-2.26-2.12-2.64-.22-.38-.02-.58.17-.77.17-.17.38-.45.57-.67.19-.22.25-.38.38-.63.13-.25.06-.48-.03-.67-.09-.19-.86-2.07-1.18-2.83-.31-.74-.63-.64-.86-.65-.22-.01-.48-.01-.73-.01-.25 0-.67.09-1.02.48-.35.38-1.34 1.31-1.34 3.2 0 1.88 1.37 3.7 1.56 3.96.19.25 2.7 4.12 6.54 5.77.91.39 1.63.63 2.18.81.92.29 1.76.25 2.42.15.74-.11 2.25-.92 2.57-1.81.32-.89.32-1.65.22-1.81-.09-.16-.35-.25-.73-.44z"/></svg>
								<span id="kwa-preview-text" style="<?php echo ( ! $show_text ) ? 'display:none;' : ''; ?>"><?php echo esc_html( $btn_text ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- CARD 3: Product Page Buttons & Checkout Settings -->
				<div class="kwa-card">
					<div class="kwa-card-header">
						<h2>
							<span>🛒</span>
							<?php esc_html_e( 'Main Product Page Buttons & Modal Checkout', 'snaporder-buy-now-for-woocommerce' ); ?>
						</h2>
					</div>

					<div class="kwa-form-row">
						<label for="snaporder_chat_number" class="kwa-label"><?php esc_html_e( 'Main Store WhatsApp Number', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<input name="snaporder_chat_number" type="text" id="snaporder_chat_number" value="<?php echo esc_attr( self::get_whatsapp_number() ); ?>" class="regular-text" placeholder="+1234567890" />
						<p class="kwa-desc"><?php esc_html_e( 'Include country code (e.g. +1234567890). Used by the product page "Order via WhatsApp" button.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<div class="kwa-form-row">
						<label for="snaporder_chat_message_template" class="kwa-label"><?php esc_html_e( 'Product Page WhatsApp Message Template', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<textarea name="snaporder_chat_message_template" id="snaporder_chat_message_template" rows="4" class="large-text code"><?php echo esc_textarea( self::get_whatsapp_template() ); ?></textarea>
						<p class="kwa-desc"><?php esc_html_e( 'Placeholders available: {product_name}, {qty}, {price}, {link}', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<div class="kwa-form-row">
						<label for="snaporder_cod_order_status" class="kwa-label"><?php esc_html_e( 'Default COD Order Status', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<select name="snaporder_cod_order_status" id="snaporder_cod_order_status">
							<option value="processing" <?php selected( self::get_cod_status(), 'processing' ); ?>><?php esc_html_e( 'Processing (Default for physical goods)', 'snaporder-buy-now-for-woocommerce' ); ?></option>
							<option value="on-hold" <?php selected( self::get_cod_status(), 'on-hold' ); ?>><?php esc_html_e( 'On Hold', 'snaporder-buy-now-for-woocommerce' ); ?></option>
							<option value="pending" <?php selected( self::get_cod_status(), 'pending' ); ?>><?php esc_html_e( 'Pending Payment', 'snaporder-buy-now-for-woocommerce' ); ?></option>
						</select>
						<p class="kwa-desc"><?php esc_html_e( 'Status assigned to orders placed via Quick Order modal using Cash on Delivery.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>

					<div class="kwa-form-row">
						<label for="snaporder_meta_pixel_id" class="kwa-label"><?php esc_html_e( 'Optional Meta Pixel ID', 'snaporder-buy-now-for-woocommerce' ); ?></label>
						<input name="snaporder_meta_pixel_id" type="text" id="snaporder_meta_pixel_id" value="<?php echo esc_attr( self::get_pixel_id() ); ?>" class="regular-text" placeholder="e.g. 123456789012345" />
						<p class="kwa-desc"><?php esc_html_e( 'Leave empty if Meta Pixel or Google Tag Manager is already configured elsewhere.', 'snaporder-buy-now-for-woocommerce' ); ?></p>
					</div>
				</div>

				<?php submit_button( __( 'Save All Changes', 'snaporder-buy-now-for-woocommerce' ), 'primary', 'submit', false, array( 'style' => 'padding: 8px 24px; font-size: 15px;' ) ); ?>
			</form>
		</div>

		<script>
		function kbnwUpdateRadioCard(radio) {
			document.querySelectorAll('input[name="' + radio.name + '"]').forEach(function(r) {
				const card = r.closest('.kwa-radio-card');
				if (card) {
					card.classList.toggle('active', r.checked);
				}
			});
		}

		function kwaUpdateWaRadioCard(radio) {
			document.querySelectorAll('input[name="' + radio.name + '"]').forEach(function(r) {
				const card = r.closest('.kwa-radio-card');
				if (card) {
					card.classList.toggle('active-wa', r.checked);
				}
			});
		}

		function kwaToggleDisplayRule(rule) {
			const includeWrap = document.getElementById('kwa-include-pages-wrap');
			if (includeWrap) {
				includeWrap.style.display = (rule === 'specific') ? 'block' : 'none';
			}
			document.querySelectorAll('input[name="snaporder_floating_chat_display_rule"]').forEach(function(radio) {
				const card = radio.closest('.kwa-radio-card');
				if (card) {
					card.classList.toggle('active-wa', radio.checked);
				}
			});
		}

		function kbnwUpdateStickyPreview() {
			const textInput = document.getElementById('snaporder_sticky_buy_now_text');
			const previewText = document.getElementById('kbnw-sticky-preview-text');
			if (previewText && textInput) {
				previewText.textContent = textInput.value.trim() !== '' ? textInput.value.trim() : 'Buy Now';
			}
		}

		function kwaUpdatePreview() {
			const showTextCheck = document.getElementById('snaporder_floating_chat_show_text');
			const textInput = document.getElementById('snaporder_floating_chat_text');
			const textInputWrap = document.getElementById('kwa-text-input-wrap');
			const previewText = document.getElementById('kwa-preview-text');
			const previewBadge = document.getElementById('kwa-preview-badge');

			const isChecked = showTextCheck ? showTextCheck.checked : true;
			const textVal = (textInput && textInput.value.trim() !== '') ? textInput.value.trim() : 'Need help?';

			if (textInputWrap) {
				textInputWrap.style.display = isChecked ? 'block' : 'none';
			}
			if (previewText) {
				previewText.textContent = textVal;
				previewText.style.display = isChecked ? 'inline' : 'none';
			}
			if (previewBadge) {
				if (isChecked) {
					previewBadge.style.padding = '9px 18px 9px 12px';
					previewBadge.style.borderRadius = '50px';
				} else {
					previewBadge.style.padding = '12px';
					previewBadge.style.borderRadius = '50%';
				}
			}
		}
		</script>
		<?php
	}
}
