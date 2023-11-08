<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Abstract_Google_Analytics_JS class
 *
 * Abstract JS for recording Google Analytics/Gtag info
 */
abstract class WC_Abstract_Google_Analytics_JS {

	/** @var WC_Abstract_Google_Analytics_JS $instance Class Instance */
	protected static $instance;

	/** @var array $options Inherited Analytics options */
	protected static $options;

	/** @var string Developer ID */
	public const DEVELOPER_ID = 'dOGY3NW';

	/**
	 * Get the class instance
	 *
	 * @param  array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	abstract public static function get_instance( $options = array() );

	/**
	 * Return one of our options
	 *
	 * @param string $option Key/name for the option
	 *
	 * @return string|null Value of the option or null if not found
	 */
	protected static function get( $option ) {
		return self::$options[ $option ] ?? null;
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	abstract public static function tracker_var();

	/**
	 * Generic GA snippet for opt out
	 */
	public static function load_opt_out() {
		$code = "
			var gaProperty = '" . esc_js( self::get( 'ga_id' ) ) . "';
			var disableStr = 'ga-disable-' + gaProperty;
			if ( document.cookie.indexOf( disableStr + '=true' ) > -1 ) {
				window[disableStr] = true;
			}
			function gaOptout() {
				document.cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
				window[disableStr] = true;
			}";

		wp_register_script( 'google-analytics-opt-out', '', array(), null, false );
		wp_add_inline_script( 'google-analytics-opt-out', $code );
		wp_enqueue_script( 'google-analytics-opt-out' );
	}

	/**
	 * Get item identifier from product data
	 *
	 * @param  WC_Product $product WC_Product Object
	 * @return string
	 */
	public static function get_product_identifier( $product ) {
		$identifier = $product->get_id();

		if ( 'product_sku' === self::get( 'ga_product_identifier' ) ) {
			if ( ! empty( $product->get_sku() ) ) {
				$identifier = $product->get_sku();
			} else {
				$identifier = '#' . ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
			}
		}

		return apply_filters( 'woocommerce_ga_product_identifier', $identifier, $product );
	}

	/**
	 * Returns a 'category' JSON line based on $product
	 *
	 * @param  WC_Product $_product  Product to pull info for
	 * @return string                Line of JSON
	 */
	public static function product_get_category_line( $_product ) {
		$out            = [];
		$variation_data = $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : false;
		$categories     = get_the_terms( $_product->get_id(), 'product_cat' );

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$parent_product = wc_get_product( $_product->get_parent_id() );
			$categories     = get_the_terms( $parent_product->get_id(), 'product_cat' );
		}

		if ( $categories ) {
			foreach ( $categories as $category ) {
				$out[] = $category->name;
			}
		}

		return "'" . esc_js( join( '/', $out ) ) . "',";
	}

	/**
	 * Returns a 'variant' JSON line based on $product
	 *
	 * @param  WC_Product $_product  Product to pull info for
	 * @return string                Line of JSON
	 */
	public static function product_get_variant_line( $_product ) {
		$out            = '';
		$variation_data = $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : false;

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$out = "'" . esc_js( wc_get_formatted_variation( $variation_data, true ) ) . "',";
		}

		return $out;
	}
}
