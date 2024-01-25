<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

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
	 * Constructor
	 * To be called from child classes to setup event data
	 *
	 * @return void
	 */
	public function __construct() {
		$this->attach_event_data();

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => ProductSchema::IDENTIFIER,
					'namespace'       => 'woocommerce_google_analytics_integration',
					'data_callback'   => array( $this, 'data_callback' ),
					'schema_callback' => array( $this, 'schema_callback' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Hook into various parts of WooCommerce and set the relevant
	 * script data that the frontend tracking script will use.
	 *
	 * @return void
	 */
	public function attach_event_data(): void {
		add_action(
			'woocommerce_before_cart',
			function() {
				$this->set_script_data( 'cart', $this->get_formatted_cart(), null, true );
			}
		);

		add_action(
			'woocommerce_before_checkout_form',
			function() {
				$this->set_script_data( 'cart', $this->get_formatted_cart(), null, true );
			}
		);

		add_action(
			'woocommerce_before_single_product',
			function() {
				global $product;
				$this->set_script_data( 'product', $this->get_formatted_product( $product ), null, true );
			}
		);

		add_action(
			'woocommerce_shop_loop_item_title',
			function() {
				global $product;
				$this->set_script_data( 'products', $this->get_formatted_product( $product ) );
			}
		);

		add_action(
			'woocommerce_thankyou',
			function( $order_id ) {
				$this->set_script_data( 'order', $this->get_formatted_order( $order_id ), null, true );
			}
		);
	}

	/**
	 * Return one of our options
	 *
	 * @param string $option Key/name for the option.
	 *
	 * @return string|null Value of the option or null if not found
	 */
	protected static function get( $option ): ?string {
		return self::$options[ $option ] ?? null;
	}

	/**
	 * Generic GA snippet for opt out
	 */
	public static function load_opt_out(): void {
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
	 * @param WC_Product $product WC_Product Object.
	 *
	 * @return string
	 */
	public static function get_product_identifier( WC_Product $product ): string {
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
	 * Returns an array of cart data in the required format
	 *
	 * @return array
	 */
	public function get_formatted_cart(): array {
		return array(
			'items'   => array_map(
				function( $item ) {
					return array(
						...$this->get_formatted_product( $item['data'] ),
						'quantity' => $item['quantity']
					);
				},
				array_values( WC()->cart->get_cart() )
			),
			'coupons' => WC()->cart->get_coupons(),
			'totals'  => array(
				'currency_code'       => get_woocommerce_currency(),
				'total_price'         => $this->get_formatted_price( WC()->cart->get_total( 'edit' ) ),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
		);
	}

	/**
	 * Returns an array of product data in the required format
	 *
	 * @param WC_Product $product The product to format.
	 *
	 * @return array
	 */
	public function get_formatted_product( WC_Product $product ): array {
		return array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'categories' => array_map(
				fn( $category ) => array( 'name' => $category->name ),
				wc_get_product_terms( $product->get_id(), 'product_cat', array( 'number' => 5 ) )
			),
			'prices'     => array(
				'price'               => $this->get_formatted_price( $product->get_price() ),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
			'extensions' => array(
				'woocommerce_google_analytics_integration' => array(
					'identifier' => $this->get_product_identifier( $product ),
				),
			),
		);
	}

	/**
	 * Returns an array of order data in the required format
	 *
	 * @param string $order_id The ID of the order
	 *
	 * @return array
	 */
	public function get_formatted_order( int $order_id ): array {
		$order = wc_get_order( $order_id );

		return array(
			'currency' => $order->get_currency(),
			'value'    => $this->get_formatted_product( $order->get_total() ),
			'items'    => array_map(
				function( $item ) {
					return array(
						...$this->get_formatted_product( $item->get_product() ),
						'quantity' => $item->get_quantity(),
					);
				},
				array_values( $order->get_items() ),
			),
		);
	}

	/**
	 * Formats a price the same way WooCommerce Blocks does
	 *
	 * @return int
	 */
	public function get_formatted_price( $value ): int {
		return intval(
			round(
				( (float) wc_format_decimal( $value ) ) * ( 10 ** absint( wc_get_price_decimals() ) ),
				0
			)
		);
	}

	/**
	 * Add product identifier to StoreAPI
	 *
	 * @return array
	 */
	public function data_callback( WC_Product $product ): array {
		return array(
			'identifier' => (string) $this->get_product_identifier( $product ),
		);
	}

	/**
	 * Schema for the extended StoreAPI data
	 *
	 * @return array
	 */
	public function schema_callback(): array {
		return array(
			'identifier' => array(
				'description' => __( 'The formatted product identifier to use in Google Analytics events.', 'woocommerce-google-analytics-integration' ),
				'type'        => 'string',
				'readonly'    => true,
			)
		);
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	abstract public static function tracker_var(): string;

	/**
	 * Add an event to the script data
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 * @param string       $key  If not null then the $data will be added as a new array item with this key.
	 *
	 * @return void
	 */
	abstract public function set_script_data( string $type, $data, ?string $key = null ): void;

	/**
	 * Get the class instance
	 *
	 * @param  array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	abstract public static function get_instance( $options = array() ): WC_Abstract_Google_Analytics_JS;
}
