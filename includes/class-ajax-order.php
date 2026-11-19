<?php
/**
 * AJAX Order Processing Controller
 *
 * @package snaporder-buy-now-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SnapOrder_Ajax_Order {

	/**
	 * Valid UAE Emirates
	 */
	const VALID_CITIES = array(
		'Dubai',
		'Abu Dhabi',
		'Sharjah',
		'Ajman',
		'Ras Al Khaimah',
		'Fujairah',
		'Umm Al Quwain',
	);

	/**
	 * Initialize AJAX hooks
	 */
	public static function init() {
		add_action( 'wp_ajax_snaporder_quick_order_submit', array( __CLASS__, 'handle_quick_order_submit' ) );
		add_action( 'wp_ajax_nopriv_snaporder_quick_order_submit', array( __CLASS__, 'handle_quick_order_submit' ) );
		add_action( 'wp_ajax_snaporder_calculate_shipping', array( __CLASS__, 'handle_calculate_shipping' ) );
		add_action( 'wp_ajax_nopriv_snaporder_calculate_shipping', array( __CLASS__, 'handle_calculate_shipping' ) );
		add_action( 'wp_ajax_snaporder_validate_coupon', array( __CLASS__, 'handle_validate_coupon' ) );
		add_action( 'wp_ajax_nopriv_snaporder_validate_coupon', array( __CLASS__, 'handle_validate_coupon' ) );
	}

	/**
	 * Helper: Authoritative Coupon Validation & Discount Calculation
	 *
	 * @param string     $coupon_code Raw coupon code.
	 * @param WC_Product $product     Target product or variation.
	 * @param float      $subtotal    Subtotal amount.
	 * @param int        $qty         Quantity.
	 * @return array
	 */
	public static function validate_and_calculate_coupon( $coupon_code, $product, $subtotal, $qty = 1 ) {
		$code = wc_format_coupon_code( $coupon_code );
		if ( empty( $code ) ) {
			return array(
				'valid'         => false,
				'error_message' => __( 'Please enter a valid coupon code.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return array(
				'valid'         => false,
				'error_message' => sprintf( __( 'Coupon "%s" does not exist.', 'snaporder-buy-now-for-woocommerce' ), esc_html( $code ) ),
			);
		}

		// 1. Check Expiration Date
		$date_expires = $coupon->get_date_expires();
		if ( $date_expires && $date_expires->getTimestamp() < current_time( 'timestamp', true ) ) {
			return array(
				'valid'         => false,
				'error_message' => __( 'This coupon has expired.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		// 2. Check Minimum Spend
		$min_spend = (float) $coupon->get_minimum_amount();
		if ( $min_spend > 0 && $subtotal < $min_spend ) {
			return array(
				'valid'         => false,
				'error_message' => sprintf( __( 'The minimum spend for this coupon is %s.', 'snaporder-buy-now-for-woocommerce' ), wc_price( $min_spend ) ),
			);
		}

		// 3. Check Maximum Spend
		$max_spend = (float) $coupon->get_maximum_amount();
		if ( $max_spend > 0 && $subtotal > $max_spend ) {
			return array(
				'valid'         => false,
				'error_message' => sprintf( __( 'The maximum spend for this coupon is %s.', 'snaporder-buy-now-for-woocommerce' ), wc_price( $max_spend ) ),
			);
		}

		// 4. Check Usage Limits
		$usage_limit = $coupon->get_usage_limit();
		$usage_count = $coupon->get_usage_count();
		if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
			return array(
				'valid'         => false,
				'error_message' => __( 'Coupon usage limit has been reached.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		// 5. Check Product Restrictions
		$target_product_id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$target_variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;

		$product_ids = $coupon->get_product_ids();
		if ( ! empty( $product_ids ) ) {
			$match = in_array( $target_product_id, $product_ids, true ) || ( $target_variation_id && in_array( $target_variation_id, $product_ids, true ) );
			if ( ! $match ) {
				return array(
					'valid'         => false,
					'error_message' => __( 'Sorry, this coupon is not applicable to the selected product.', 'snaporder-buy-now-for-woocommerce' ),
				);
			}
		}

		$excluded_product_ids = $coupon->get_excluded_product_ids();
		if ( ! empty( $excluded_product_ids ) ) {
			$excluded = in_array( $target_product_id, $excluded_product_ids, true ) || ( $target_variation_id && in_array( $target_variation_id, $excluded_product_ids, true ) );
			if ( $excluded ) {
				return array(
					'valid'         => false,
					'error_message' => __( 'Sorry, this coupon cannot be used with this product.', 'snaporder-buy-now-for-woocommerce' ),
				);
			}
		}

		// 6. Check Category Restrictions
		$category_ids = $coupon->get_product_categories();
		$product_cats = wc_get_product_term_ids( $target_product_id, 'product_cat' );
		if ( ! empty( $category_ids ) && empty( array_intersect( $product_cats, $category_ids ) ) ) {
			return array(
				'valid'         => false,
				'error_message' => __( 'Sorry, this coupon is not applicable to this product category.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		$excluded_category_ids = $coupon->get_excluded_product_categories();
		if ( ! empty( $excluded_category_ids ) && ! empty( array_intersect( $product_cats, $excluded_category_ids ) ) ) {
			return array(
				'valid'         => false,
				'error_message' => __( 'Sorry, this coupon is excluded from this product category.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		// 7. Check Sale Items Exclusion
		if ( $coupon->get_exclude_sale_items() && $product->is_on_sale() ) {
			return array(
				'valid'         => false,
				'error_message' => __( 'Sorry, this coupon is not valid on sale items.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		// 8. Calculate Discount Amount
		$discount_type = $coupon->get_discount_type();
		$coupon_amount = (float) $coupon->get_amount();
		$discount      = 0.0;

		if ( 'percent' === $discount_type ) {
			$discount = ( $subtotal * $coupon_amount ) / 100.0;
		} elseif ( 'fixed_cart' === $discount_type ) {
			$discount = min( $subtotal, $coupon_amount );
		} elseif ( 'fixed_product' === $discount_type ) {
			$discount = min( $subtotal, $coupon_amount * $qty );
		} else {
			$discount = min( $subtotal, $coupon_amount );
		}

		$discount = round( max( 0.0, min( $discount, $subtotal ) ), 2 );

		return array(
			'valid'    => true,
			'coupon'   => $coupon,
			'discount' => $discount,
		);
	}

	/**
	 * Helper: Validate and retrieve Add-on items for product (anti-tampering)
	 *
	 * @param int   $product_id
	 * @param array $raw_addons
	 * @return array
	 */
	public static function get_validated_addons( $product_id, $raw_addons ) {
		if ( empty( $raw_addons ) || ! is_array( $raw_addons ) ) {
			return array(
				'items' => array(),
				'total' => 0.0,
			);
		}

		$validated_items = array();
		$total_price     = 0.0;

		if ( class_exists( 'WCA_Addon_Groups' ) ) {
			foreach ( $raw_addons as $addon_id ) {
				$addon_id   = sanitize_key( $addon_id );
				$addon_item = WCA_Addon_Groups::get_addon_item_by_id( $product_id, $addon_id );
				if ( ! empty( $addon_item ) ) {
					$price             = (float) $addon_item['price'];
					$validated_items[] = array(
						'id'    => $addon_item['id'],
						'name'  => sanitize_text_field( $addon_item['name'] ),
						'price' => $price,
					);
					$total_price += $price;
				}
			}
		}

		return array(
			'items' => $validated_items,
			'total' => $total_price,
		);
	}

	/**
	 * Handle AJAX Coupon Validation
	 */
	public static function handle_validate_coupon() {
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : ( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce, 'snaporder_quick_order_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh the page.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		$coupon_code  = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$qty          = isset( $_POST['qty'] ) ? max( 1, absint( $_POST['qty'] ) ) : 1;
		$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : 'Dubai';
		$raw_addons   = isset( $_POST['wca_addons'] ) ? (array) $_POST['wca_addons'] : array();

		if ( empty( $coupon_code ) ) {
			wp_send_json_error( array(
				'message' => __( 'Please enter a coupon code.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		$target_product = null;
		if ( $variation_id > 0 ) {
			$target_product = wc_get_product( $variation_id );
		} elseif ( $product_id > 0 ) {
			$target_product = wc_get_product( $product_id );
		}

		if ( ! $target_product || ! $target_product->exists() ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid product.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		$parent_id    = $target_product->is_type( 'variation' ) ? $target_product->get_parent_id() : $target_product->get_id();
		$addons_info  = self::get_validated_addons( $parent_id, $raw_addons );
		$addons_total = (float) $addons_info['total'] * $qty;

		$unit_price = (float) $target_product->get_price();
		$subtotal   = ( $unit_price * $qty ) + $addons_total;

		$coupon_res = self::validate_and_calculate_coupon( $coupon_code, $target_product, $subtotal, $qty );
		if ( empty( $coupon_res['valid'] ) ) {
			wp_send_json_error( array(
				'message' => $coupon_res['error_message'],
			) );
		}

		$discount            = (float) $coupon_res['discount'];
		$discounted_subtotal = max( 0.0, $subtotal - $discount );

		// Calculate Shipping with coupon-discounted subtotal
		$shipping_res  = SnapOrder_Shipping_Calculator::calculate( $target_product, $qty, $city, $discounted_subtotal );
		$shipping_cost = ! empty( $shipping_res['available'] ) ? (float) $shipping_res['cost'] : 0.0;
		$total         = $discounted_subtotal + $shipping_cost;
		$symbol        = get_woocommerce_currency_symbol() ?: 'AED';

		$shipping_formatted = ( ! empty( $shipping_res['is_free'] ) )
			? __( 'FREE', 'snaporder-buy-now-for-woocommerce' )
			: ( $symbol . ' ' . number_format( $shipping_cost, 2 ) );

		wp_send_json_success( array(
			'available'          => true,
			'coupon_code'        => $coupon_res['coupon']->get_code(),
			'discount'           => $discount,
			'discount_formatted' => '- ' . $symbol . ' ' . number_format( $discount, 2 ),
			'subtotal'           => $subtotal,
			'subtotal_formatted' => $symbol . ' ' . number_format( $subtotal, 2 ),
			'addons_total'       => $addons_total,
			'addons_formatted'   => $addons_total > 0 ? ( '+ ' . $symbol . ' ' . number_format( $addons_total, 2 ) ) : '',
			'shipping_cost'      => $shipping_cost,
			'shipping_formatted' => $shipping_formatted,
			'total'              => $total,
			'total_formatted'    => $symbol . ' ' . number_format( $total, 2 ),
			'message'            => __( 'Coupon applied successfully!', 'snaporder-buy-now-for-woocommerce' ),
		) );
	}

	/**
	 * Handle live AJAX Shipping Calculation
	 */
	public static function handle_calculate_shipping() {
		// Verify Nonce
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : ( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce, 'snaporder_quick_order_action' ) ) {
			wp_send_json_error( array(
				'available' => false,
				'message'   => __( 'Security check failed. Please refresh the page.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$qty          = isset( $_POST['qty'] ) ? max( 1, absint( $_POST['qty'] ) ) : 1;
		$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$coupon_code  = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$raw_addons   = isset( $_POST['wca_addons'] ) ? (array) $_POST['wca_addons'] : array();

		$target_product = null;
		if ( $variation_id > 0 ) {
			$target_product = wc_get_product( $variation_id );
		} elseif ( $product_id > 0 ) {
			$target_product = wc_get_product( $product_id );
		}

		if ( ! $target_product || ! $target_product->exists() ) {
			wp_send_json_error( array(
				'available' => false,
				'message'   => __( 'Invalid product.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		if ( ! in_array( $city, self::VALID_CITIES, true ) ) {
			wp_send_json_error( array(
				'available' => false,
				'message'   => __( 'Please select a valid UAE Emirate.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		$parent_id    = $target_product->is_type( 'variation' ) ? $target_product->get_parent_id() : $target_product->get_id();
		$addons_info  = self::get_validated_addons( $parent_id, $raw_addons );
		$addons_total = (float) $addons_info['total'] * $qty;

		$price    = (float) $target_product->get_price();
		$subtotal = ( $price * $qty ) + $addons_total;
		$discount = 0.0;

		// Calculate coupon discount if present
		if ( ! empty( $coupon_code ) ) {
			$coupon_res = self::validate_and_calculate_coupon( $coupon_code, $target_product, $subtotal, $qty );
			if ( ! empty( $coupon_res['valid'] ) ) {
				$discount = (float) $coupon_res['discount'];
			}
		}

		$discounted_subtotal = max( 0.0, $subtotal - $discount );
		$shipping_res        = SnapOrder_Shipping_Calculator::calculate( $target_product, $qty, $city, $discounted_subtotal );

		if ( empty( $shipping_res['available'] ) ) {
			wp_send_json_error( array(
				'available' => false,
				'message'   => ! empty( $shipping_res['error_message'] ) ? $shipping_res['error_message'] : __( 'Delivery is currently not available for this location.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		$shipping_cost = (float) $shipping_res['cost'];
		$total         = $discounted_subtotal + $shipping_cost;
		$currency      = get_woocommerce_currency() ?: 'AED';
		$symbol        = get_woocommerce_currency_symbol() ?: 'AED';

		$cost_formatted = $shipping_res['is_free']
			? __( 'FREE', 'snaporder-buy-now-for-woocommerce' )
			: ( $symbol . ' ' . number_format( $shipping_cost, 2 ) );

		wp_send_json_success( array(
			'available'          => true,
			'rate_id'            => $shipping_res['rate_id'],
			'method_id'          => $shipping_res['method_id'],
			'instance_id'        => $shipping_res['instance_id'],
			'label'              => $shipping_res['label'],
			'cost'               => $shipping_cost,
			'cost_formatted'     => $cost_formatted,
			'is_free'            => $shipping_res['is_free'],
			'subtotal'           => $subtotal,
			'subtotal_formatted' => $symbol . ' ' . number_format( $subtotal, 2 ),
			'addons_total'       => $addons_total,
			'addons_formatted'   => $addons_total > 0 ? ( '+ ' . $symbol . ' ' . number_format( $addons_total, 2 ) ) : '',
			'discount'           => $discount,
			'discount_formatted' => $discount > 0 ? ( '- ' . $symbol . ' ' . number_format( $discount, 2 ) ) : '',
			'total'              => $total,
			'total_formatted'    => $symbol . ' ' . number_format( $total, 2 ),
			'currency'           => $currency,
		) );
	}

	/**
	 * Handle Quick Order AJAX POST
	 */
	public static function handle_quick_order_submit() {
		// Verify Nonce
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : ( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce, 'snaporder_quick_order_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}

		try {
			// 1. Sanitize & Validate Inputs
			$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
			$qty          = isset( $_POST['qty'] ) ? max( 1, absint( $_POST['qty'] ) ) : 1;
			$raw_phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$name         = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
			$address      = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
			$method       = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'cod';
			$coupon_code  = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
			$raw_addons   = isset( $_POST['wca_addons'] ) ? (array) $_POST['wca_addons'] : array();

			// Validate Name
			if ( empty( $name ) || mb_strlen( $name ) < 2 ) {
				wp_send_json_error( array(
					'field'   => 'name',
					'message' => __( 'Please enter your full name (at least 2 characters).', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			// Validate Phone (UAE Format)
			$clean_phone = preg_replace( '/[\s\-\(\)\.]/', '', $raw_phone );
			$uae_regex   = '/^(?:(?:\+971|00971|971|0)?)(5[0-9]{8})$/';
			if ( ! preg_match( $uae_regex, $clean_phone, $matches ) ) {
				wp_send_json_error( array(
					'field'   => 'phone',
					'message' => __( 'Please enter a valid UAE mobile number (e.g. 050 123 4567 or +971 50 123 4567).', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}
			$normalized_phone = '+971' . $matches[1];

			// Validate City
			if ( empty( $city ) || ! in_array( $city, self::VALID_CITIES, true ) ) {
				wp_send_json_error( array(
					'field'   => 'city',
					'message' => __( 'Please select a valid UAE city/emirate.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			// Validate Address
			if ( empty( $address ) || mb_strlen( $address ) < 3 ) {
				wp_send_json_error( array(
					'field'   => 'address',
					'message' => __( 'Please enter your complete delivery address.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			// 2. Validate Product & Variation from Database (Never Trust Client Price)
			$parent_product = wc_get_product( $product_id );
			if ( ! $parent_product || ! $parent_product->exists() ) {
				wp_send_json_error( array(
					'message' => __( 'The selected product does not exist.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			$target_product = $parent_product;
			if ( $parent_product->is_type( 'variable' ) ) {
				if ( empty( $variation_id ) ) {
					wp_send_json_error( array(
						'message' => __( 'Please select all required product options before placing your order.', 'snaporder-buy-now-for-woocommerce' ),
					) );
				}
				$variation_product = wc_get_product( $variation_id );
				if ( ! $variation_product || ! $variation_product->exists() || ! $variation_product->is_type( 'variation' ) || (int) $variation_product->get_parent_id() !== (int) $product_id ) {
					wp_send_json_error( array(
						'message' => __( 'Invalid product variation selected.', 'snaporder-buy-now-for-woocommerce' ),
					) );
				}
				$target_product = $variation_product;
			}

			if ( ! $target_product->is_purchasable() ) {
				wp_send_json_error( array(
					'message' => __( 'This product is currently not available for purchase.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			if ( ! $target_product->is_in_stock() ) {
				wp_send_json_error( array(
					'message' => __( 'Sorry, this product is currently out of stock.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			if ( ! $target_product->has_enough_stock( $qty ) ) {
				wp_send_json_error( array(
					'message' => sprintf(
						/* translators: %d: available stock quantity */
						__( 'Sorry, only %d item(s) available in stock for this product.', 'snaporder-buy-now-for-woocommerce' ),
						$target_product->get_stock_quantity()
					),
				) );
			}

			// Validate Add-ons Authoritatively from DB (Anti-Tampering)
			$parent_id    = $target_product->is_type( 'variation' ) ? $target_product->get_parent_id() : $target_product->get_id();
			$addons_info  = self::get_validated_addons( $parent_id, $raw_addons );
			$addons_total = (float) $addons_info['total'] * $qty;

			// 3. Validate Shipping Availability Authoritatively on Server (Anti-Tampering)
			$raw_subtotal = ( (float) $target_product->get_price() * $qty ) + $addons_total;
			$shipping_res = SnapOrder_Shipping_Calculator::calculate( $target_product, $qty, $city, $raw_subtotal );
			if ( empty( $shipping_res['available'] ) ) {
				wp_send_json_error( array(
					'message' => ! empty( $shipping_res['error_message'] ) ? $shipping_res['error_message'] : __( 'Delivery is currently not available for the selected location.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			// 4. Create Order Directly via wc_create_order()
			$order = wc_create_order( array(
				'status'        => 'pending',
				'customer_note' => __( 'Placed via SnapOrder Quick Order Modal', 'snaporder-buy-now-for-woocommerce' ),
			) );

			if ( is_wp_error( $order ) || ! $order ) {
				wp_send_json_error( array(
					'message' => __( 'Unable to create order. Please try again.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			// Build Product Line Item with variation attributes and addons metadata pre-attached
			$item = new WC_Order_Item_Product();
			$item->set_product( $target_product );
			$item->set_quantity( $qty );
			$item->set_subtotal( $raw_subtotal );
			$item->set_total( $raw_subtotal );
			if ( $target_product->is_type( 'variation' ) ) {
				$item->set_variation_id( $target_product->get_id() );
				$item->set_name( $target_product->get_name() );
				foreach ( $target_product->get_variation_attributes() as $attr_key => $attr_val ) {
					$item->add_meta_data( $attr_key, $attr_val, true );
				}
			}

			// Attach Add-on metadata directly to item before attaching to order (ensures in-memory email rendering has metadata)
			if ( ! empty( $addons_info['items'] ) ) {
				$currency = get_woocommerce_currency() ?: 'AED';
				$addon_strings = array();
				foreach ( $addons_info['items'] as $addon ) {
					$addon_name  = sanitize_text_field( $addon['name'] ?? '' );
					$addon_price = isset( $addon['price'] ) ? (float) $addon['price'] : 0.0;
					if ( ! empty( $addon_name ) ) {
						$formatted_price = number_format( $addon_price, 2, '.', ',' );
						$addon_strings[] = sprintf( '%s (+%s %s)', $addon_name, $currency, $formatted_price );
					}
				}
				if ( ! empty( $addon_strings ) ) {
					$meta_label = ( count( $addon_strings ) === 1 ) ? __( 'Add-on', 'snaporder-buy-now-for-woocommerce' ) : __( 'Add-ons', 'snaporder-buy-now-for-woocommerce' );
					$meta_value = implode( ', ', $addon_strings );
					$item->add_meta_data( $meta_label, $meta_value, true );
				}
				$item->add_meta_data( '_wca_addons', $addons_info['items'], true );
				$item->add_meta_data( '_wca_addons_total', (float) $addons_info['total'], true );
			}

			$order->add_item( $item );

			// Split Name into First / Last Name
			$name_parts = explode( ' ', trim( $name ), 2 );
			$first_name = $name_parts[0];
			$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';
			$state_code = SnapOrder_Shipping_Calculator::get_state_code( $city );

			// Generate deterministic valid email to prevent Nomod 400 blank customer errors
			if ( ! empty( $_POST['email'] ) && is_email( $_POST['email'] ) ) {
				$billing_email = sanitize_email( wp_unslash( $_POST['email'] ) );
			} else {
				$phone_digits  = preg_replace( '/[^0-9]/', '', $normalized_phone );
				$billing_email = 'guest_' . $phone_digits . '@example.com';
			}

			$address_data = array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'company'    => '',
				'address_1'  => $address,
				'address_2'  => '',
				'city'       => $city,
				'state'      => $state_code,
				'postcode'   => '',
				'country'    => 'AE',
				'email'      => $billing_email,
				'phone'      => $normalized_phone,
			);

			$order->set_address( $address_data, 'billing' );
			$order->set_address( $address_data, 'shipping' );

			// Add Authoritative Shipping Line Item
			$shipping_item = new WC_Order_Item_Shipping();
			$shipping_item->set_method_title( $shipping_res['label'] );
			$shipping_item->set_method_id( $shipping_res['method_id'] );
			if ( ! empty( $shipping_res['instance_id'] ) ) {
				$shipping_item->set_instance_id( $shipping_res['instance_id'] );
			}
			$shipping_item->set_total( (float) $shipping_res['cost'] );
			$order->add_item( $shipping_item );

			// 5. Apply Coupon Server-Side (Anti-Tampering)
			if ( ! empty( $coupon_code ) ) {
				$clean_code = wc_format_coupon_code( $coupon_code );
				$coupon_val = self::validate_and_calculate_coupon( $clean_code, $target_product, $raw_subtotal, $qty );
				if ( ! empty( $coupon_val['valid'] ) ) {
					$order->apply_coupon( $clean_code );
				}
			}

			// Calculate Totals on Server
			$order->calculate_totals();

			// 6. Handle Gateway Routing
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			$order_id           = $order->get_id();
			$order_number       = $order->get_order_number();
			$order_total        = (float) $order->get_total();
			$unit_price         = (float) $target_product->get_price() + $addons_info['total'];
			$currency           = get_woocommerce_currency() ?: 'AED';
			// Track order source as quick_order to prevent thank-you double-fire
			$order->update_meta_data( '_snaporder_order_source', 'quick_order' );
			$order->update_meta_data( '_snaporder_cod_event_origin', 'quick_order_success' );

			$product_brand = class_exists( 'SnapOrder_Ecommerce_Tracking' ) ? SnapOrder_Ecommerce_Tracking::get_product_brand( $target_product ) : '';
			$product_cats  = class_exists( 'SnapOrder_Ecommerce_Tracking' ) ? SnapOrder_Ecommerce_Tracking::get_product_categories( $target_product ) : array( 'cat1' => '', 'cat2' => '' );

			// Case A: Cash on Delivery (COD)
			if ( 'cod' === $method || empty( $method ) ) {
				$order->set_payment_method( 'cod' );
				$order->set_payment_method_title( __( 'Cash on Delivery', 'snaporder-buy-now-for-woocommerce' ) );
				
				$cod_status = SnapOrder_Settings::get_cod_status();
				$order->update_status( $cod_status, __( 'Quick Order placed via Cash on Delivery.', 'snaporder-buy-now-for-woocommerce' ) );
				$order->save();

				// Render Clean Confirmation HTML
				$html_success = self::render_success_html( $order, $target_product, $qty, $addons_info['items'] );

				wp_send_json_success( array(
					'order_id'         => $order_id,
					'order_number'     => $order_number,
					'total'            => $order_total,
					'unit_price'       => $unit_price,
					'currency'         => $currency,
					'product_id'       => $target_product->get_id(),
					'product_name'     => $target_product->get_name(),
					'product_brand'    => $product_brand,
					'product_category' => $product_cats['cat1'] ?? '',
					'qty'              => $qty,
					'payment_type'     => 'cod',
					'redirect_url'     => $order->get_checkout_order_received_url(),
					'html_success'     => $html_success,
				) );
			}

			// Case B: Online Payment Gateway (Nomod, Tabby, etc.)
			$gateway = isset( $available_gateways[ $method ] ) ? $available_gateways[ $method ] : null;
			if ( ! $gateway ) {
				// Fallback if requested gateway is not active
				$order->set_payment_method( 'cod' );
				$order->set_payment_method_title( __( 'Cash on Delivery', 'snaporder-buy-now-for-woocommerce' ) );
				$order->update_status( SnapOrder_Settings::get_cod_status(), __( 'Quick Order (fallback to COD).', 'snaporder-buy-now-for-woocommerce' ) );
				$order->save();

				$html_success = self::render_success_html( $order, $target_product, $qty, $addons_info['items'] );
				wp_send_json_success( array(
					'order_id'         => $order_id,
					'order_number'     => $order_number,
					'total'            => $order_total,
					'unit_price'       => $unit_price,
					'currency'         => $currency,
					'product_id'       => $target_product->get_id(),
					'product_name'     => $target_product->get_name(),
					'product_brand'    => $product_brand,
					'product_category' => $product_cats['cat1'] ?? '',
					'qty'              => $qty,
					'payment_type'     => 'cod',
					'redirect_url'     => $order->get_checkout_order_received_url(),
					'html_success'     => $html_success,
				) );
			}

			$order->set_payment_method( $gateway );
			$order->set_payment_method_title( $gateway->get_title() );
			$order->update_status( 'pending', __( 'Quick Order created. Awaiting online payment.', 'snaporder-buy-now-for-woocommerce' ) );
			$order->save();

			// Initialize WooCommerce Session if not already active
			if ( ! did_action( 'woocommerce_init' ) && function_exists( 'WC' ) && ! WC()->session ) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}

			$redirect_url = '';
			try {
				$result = $gateway->process_payment( $order_id );
				if ( is_array( $result ) && isset( $result['result'] ) && 'success' === $result['result'] && ! empty( $result['redirect'] ) ) {
					$redirect_url = $result['redirect'];
				}
			} catch ( Exception $e ) {
				$logger = wc_get_logger();
				$logger->error( 'Gateway process_payment error: ' . $e->getMessage(), array( 'gateway' => $method, 'order_id' => $order_id ) );
				wp_send_json_error( array(
					'message' => $e->getMessage() ?: __( 'Online payment initialization failed. Please try again or select Cash on Delivery.', 'snaporder-buy-now-for-woocommerce' ),
				) );
			}

			// Robust Fallback: Standard WooCommerce Pay for Order URL
			if ( empty( $redirect_url ) ) {
				$redirect_url = $order->get_checkout_payment_url( true );
			}

			wp_send_json_success( array(
				'order_id'       => $order_id,
				'order_number'   => $order_number,
				'total'          => $order_total,
				'unit_price'     => $unit_price,
				'currency'       => $currency,
				'product_id'     => $target_product->get_id(),
				'product_name'   => $target_product->get_name(),
				'qty'            => $qty,
				'payment_type'   => 'online',
				'redirect_url'   => $redirect_url,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => __( 'An unexpected error occurred while placing your order. Please try again.', 'snaporder-buy-now-for-woocommerce' ),
			) );
		}
	}

	/**
	 * Render confirmation screen HTML
	 *
	 * @param WC_Order   $order
	 * @param WC_Product $product
	 * @param int        $qty
	 * @param array      $addons
	 * @return string HTML
	 */
	public static function render_success_html( $order, $product, $qty, $addons = array() ) {
		ob_start();
		$discount_total = (float) $order->get_discount_total();
		$currency       = get_woocommerce_currency() ?: 'AED';
		?>
		<div class="snaporder-success-icon-wrap">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
				<polyline points="20 6 9 17 4 12"></polyline>
			</svg>
		</div>

		<h3 class="snaporder-success-title"><?php esc_html_e( 'Thank You! Order Confirmed', 'snaporder-buy-now-for-woocommerce' ); ?></h3>
		<p class="snaporder-success-message">
			<?php
			printf(
				/* translators: %s: customer name */
				esc_html__( 'We have received your order, %s! Our team is preparing it for express delivery.', 'snaporder-buy-now-for-woocommerce' ),
				'<strong>' . esc_html( $order->get_billing_first_name() ) . '</strong>'
			);
			?>
		</p>

		<div class="snaporder-success-order-box">
			<div>
				<span><?php esc_html_e( 'Order Number:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
			</div>
			<div>
				<span><?php esc_html_e( 'Product:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span><?php echo esc_html( $product->get_name() ); ?> &times; <?php echo esc_html( $qty ); ?></span>
			</div>
			<?php if ( ! empty( $addons ) && is_array( $addons ) ) : ?>
			<div>
				<span><?php esc_html_e( 'Selected Add-ons:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span style="font-weight:600;color:#0F172A;text-align:right;">
					<?php
					$addon_strings = array();
					foreach ( $addons as $a ) {
						$addon_strings[] = sprintf( '%s (+%s %s)', esc_html( $a['name'] ), esc_html( $currency ), number_format( (float) $a['price'], 2 ) );
					}
					echo implode( '<br/>', $addon_strings );
					?>
				</span>
			</div>
			<?php endif; ?>
			<div>
				<span><?php esc_html_e( 'Delivery City:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span><?php echo esc_html( $order->get_billing_city() ); ?></span>
			</div>
			<div>
				<span><?php esc_html_e( 'Payment Method:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
			</div>
			<?php if ( $discount_total > 0 ) : ?>
			<div>
				<span><?php esc_html_e( 'Coupon Discount:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span style="color:#16A34A;font-weight:600;">- <?php echo wp_kses_post( wc_price( $discount_total ) ); ?></span>
			</div>
			<?php endif; ?>
			<div>
				<span><?php esc_html_e( 'Delivery:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span><?php echo (float) $order->get_shipping_total() > 0 ? wp_kses_post( wc_price( $order->get_shipping_total() ) ) : esc_html__( 'Free', 'snaporder-buy-now-for-woocommerce' ); ?></span>
			</div>
			<div>
				<span><?php esc_html_e( 'Total Amount:', 'snaporder-buy-now-for-woocommerce' ); ?></span>
				<span><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></span>
			</div>
		</div>

		<button type="button" class="snaporder-success-close-btn" id="snaporder-success-done-btn">
			<?php esc_html_e( 'Continue Shopping', 'snaporder-buy-now-for-woocommerce' ); ?>
		</button>
		<?php
		return ob_get_clean();
	}
}
