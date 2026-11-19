<?php
/**
 * ShopForge Labs — Dynamic Shipping Rate Calculator
 * Connects directly to WooCommerce Shipping Zones and Methods Engine.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SnapOrder_Shipping_Calculator {

	/**
	 * Map UAE Emirate/City names to WooCommerce State Codes
	 *
	 * @param string $city_or_emirate
	 * @return string
	 */
	public static function get_state_code( $city_or_emirate ) {
		$map = array(
			'Dubai'          => 'DU',
			'Abu Dhabi'      => 'AZ',
			'Sharjah'        => 'SH',
			'Ajman'          => 'AJ',
			'Ras Al Khaimah' => 'RK',
			'Fujairah'       => 'FU',
			'Umm Al Quwain'  => 'UQ',
		);

		return isset( $map[ $city_or_emirate ] ) ? $map[ $city_or_emirate ] : $city_or_emirate;
	}

	/**
	 * Build a complete WooCommerce Shipping Package for a single product & quantity
	 *
	 * @param WC_Product $product
	 * @param int        $qty
	 * @param string     $city
	 * @param string     $address
	 * @param float|null $custom_subtotal
	 * @return array
	 */
	public static function build_package( $product, $qty, $city, $address = '', $custom_subtotal = null ) {
		$product_id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$qty          = max( 1, intval( $qty ) );
		$price        = (float) $product->get_price();
		$subtotal     = null !== $custom_subtotal ? max( 0, (float) $custom_subtotal ) : ( $price * $qty );
		$state_code   = self::get_state_code( $city );

		$cart_item_key = md5( $product_id . '_' . $variation_id . '_' . $qty );

		$contents = array(
			$cart_item_key => array(
				'key'               => $cart_item_key,
				'product_id'        => $product_id,
				'variation_id'      => $variation_id,
				'variation'         => $product->is_type( 'variation' ) ? $product->get_attributes() : array(),
				'quantity'          => $qty,
				'data'              => $product,
				'line_total'        => $subtotal,
				'line_subtotal'     => $price * $qty,
				'line_tax'          => 0,
				'line_subtotal_tax' => 0,
			),
		);

		return array(
			'contents'        => $contents,
			'contents_cost'   => $subtotal,
			'applied_coupons' => array(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'     => array(
				'country'   => 'AE',
				'state'     => $state_code,
				'postcode'  => '',
				'city'      => $city,
				'address'   => $address ? $address : $city,
				'address_2' => '',
			),
		);
	}

	/**
	 * Calculate shipping rates from WooCommerce shipping zones for product, qty, and city
	 *
	 * @param WC_Product|int $product
	 * @param int            $qty
	 * @param string         $city
	 * @param float|null     $custom_subtotal
	 * @return array
	 */
	public static function calculate( $product, $qty, $city, $custom_subtotal = null ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return array(
				'available'     => false,
				'error_message' => __( 'Product not found.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		// Ensure shipping methods are initialized
		if ( function_exists( 'WC' ) && WC()->shipping() ) {
			WC()->shipping()->load_shipping_methods();
		}

		$package = self::build_package( $product, $qty, $city, '', $custom_subtotal );

		// 1. Resolve matching WooCommerce shipping zone
		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );

		// Attach free shipping filter for standalone package evaluation
		add_filter( 'woocommerce_shipping_free_shipping_is_available', array( __CLASS__, 'filter_free_shipping_availability' ), 10, 3 );

		$available_rates = array();

		// 2. Evaluate enabled shipping methods in the matched zone
		if ( $zone ) {
			$shipping_methods = $zone->get_shipping_methods( true );
			foreach ( $shipping_methods as $shipping_method ) {
				if ( $shipping_method->is_available( $package ) ) {
					$rates = $shipping_method->get_rates_for_package( $package );
					if ( ! empty( $rates ) && is_array( $rates ) ) {
						foreach ( $rates as $rate ) {
							$available_rates[ $rate->get_id() ] = $rate;
						}
					}
				}
			}
		}

		// 3. If no rates in matched zone, try Rest of the World (Zone 0) as fallback
		if ( empty( $available_rates ) && ( ! $zone || 0 !== $zone->get_id() ) ) {
			$default_zone     = new WC_Shipping_Zone( 0 );
			$shipping_methods = $default_zone->get_shipping_methods( true );
			foreach ( $shipping_methods as $shipping_method ) {
				if ( $shipping_method->is_available( $package ) ) {
					$rates = $shipping_method->get_rates_for_package( $package );
					if ( ! empty( $rates ) && is_array( $rates ) ) {
						foreach ( $rates as $rate ) {
							$available_rates[ $rate->get_id() ] = $rate;
						}
					}
				}
			}
			if ( ! empty( $available_rates ) ) {
				$zone = $default_zone;
			}
		}

		remove_filter( 'woocommerce_shipping_free_shipping_is_available', array( __CLASS__, 'filter_free_shipping_availability' ), 10 );

		// 4. No rates available anywhere for this location
		if ( empty( $available_rates ) ) {
			return array(
				'available'     => false,
				'error_message' => __( 'Delivery is currently not available for this location.', 'snaporder-buy-now-for-woocommerce' ),
			);
		}

		// 5. Select the best rate (prioritize Free Shipping or lowest cost rate)
		$chosen_rate = null;
		$min_cost    = null;

		foreach ( $available_rates as $rate ) {
			$cost = (float) $rate->get_cost();
			// If Free Shipping (0 cost), prioritize immediately
			if ( 0.0 === $cost || $cost <= 0.0 ) {
				$chosen_rate = $rate;
				break;
			}
			if ( null === $min_cost || $cost < $min_cost ) {
				$min_cost    = $cost;
				$chosen_rate = $rate;
			}
		}

		if ( ! $chosen_rate ) {
			$chosen_rate = reset( $available_rates );
		}

		$cost = (float) $chosen_rate->get_cost();

		return array(
			'available'   => true,
			'rate_id'     => $chosen_rate->get_id(),
			'method_id'   => $chosen_rate->get_method_id(),
			'instance_id' => $chosen_rate->get_instance_id(),
			'label'       => $chosen_rate->get_label(),
			'cost'        => $cost,
			'is_free'     => ( $cost <= 0.0 ),
			'zone_id'     => $zone ? $zone->get_id() : 0,
			'zone_name'   => $zone ? $zone->get_zone_name() : '',
		);
	}

	/**
	 * Filter free shipping availability for standalone packages
	 *
	 * @param bool                      $is_available
	 * @param array                     $package
	 * @param WC_Shipping_Free_Shipping $shipping_method
	 * @return bool
	 */
	public static function filter_free_shipping_availability( $is_available, $package, $shipping_method ) {
		if ( ! is_a( $shipping_method, 'WC_Shipping_Free_Shipping' ) ) {
			return $is_available;
		}

		$requires = $shipping_method->requires;
		if ( in_array( $requires, array( 'min_amount', 'either' ), true ) ) {
			$cost = isset( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0.0;
			$min  = (float) $shipping_method->min_amount;
			if ( $cost >= $min ) {
				return true;
			}
		}

		return $is_available;
	}
}
