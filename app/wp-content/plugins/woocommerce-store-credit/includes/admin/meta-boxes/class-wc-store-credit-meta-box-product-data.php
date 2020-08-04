<?php
/**
 * Meta Box: Product Data
 *
 * Updates the Coupon Data meta box.
 *
 * @package WC_Store_Credit/Admin/Meta_Boxes
 * @since   3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Store_Credit_Admin_Send_Credit_Page class.
 */
class WC_Store_Credit_Meta_Box_Product_Data {

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_data_tabs' ), 10, 2 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_panels' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_data' ), 10, 2 );
	}

	/**
	 * Customizes the product data tabs.
	 *
	 * @since 3.2.0
	 *
	 * @param array $tabs An array with the product data tabs.
	 * @return array
	 */
	public function product_data_tabs( $tabs ) {
		$tab_classes = array(
			'shipping' => 'hide_if_store_credit',
		);

		foreach ( $tab_classes as $tab => $class ) {
			if ( isset( $tabs[ $tab ] ) ) {
				$classes   = ( ! empty( $tabs[ $tab ]['class'] ) ? $tabs[ $tab ]['class'] : array() );
				$classes[] = $class;

				$tabs[ $tab ]['class'] = $classes;
			}
		}

		$tabs['store_credit'] = array(
			'label'    => __( 'Store Credit', 'woocommerce-store-credit' ),
			'target'   => 'store_credit_product_data',
			'class'    => array( 'show_if_store_credit' ),
			'priority' => 15,
		);

		return $tabs;
	}

	/**
	 * Gets the fields for the 'Store Credit' tab displayed in the product data meta box.
	 *
	 * @since 3.2.0
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	protected function get_store_credit_fields( $product ) {
		$values = $product->get_meta( '_store_credit_data' );

		if ( ! $values ) {
			$values = array();
		}

		$fields = array(
			'amount'     => array(
				'id'          => '_store_credit_amount',
				'label'       => __( 'Coupon amount', 'woocommerce-store-credit' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'type'        => 'text',
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'Value of the coupon. Default: regular price.', 'woocommerce-store-credit' ),
				'value'       => ( isset( $values['amount'] ) ? $values['amount'] : '' ),
			),
			'expiration' => array(
				'id'           => '_store_credit_expiration',
				'label'        => __( 'Coupon expiration', 'woocommerce-store-credit' ),
				'period_label' => __( 'Coupon expiration period', 'woocommerce-store-credit' ),
				'type'         => 'time_period',
				'desc_tip'     => true,
				'description'  => __( 'The coupon will expire passed this period. Leave empty to not expire.', 'woocommerce-store-credit' ),
				'placeholder'  => __( 'N/A', 'woocommerce-store-credit' ),
				'value'        => ( isset( $values['expiration'] ) ? $values['expiration'] : '' ),
			),
		);

		if ( wc_store_credit_coupons_can_inc_tax() ) {
			$fields['inc_tax'] = array(
				'id'          => '_store_credit_inc_tax',
				'label'       => _x( 'Include tax', 'coupon: field label', 'woocommerce-store-credit' ),
				'description' => _x( 'Check this box if the coupon amount includes taxes.', 'coupon: field desc', 'woocommerce-store-credit' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'value'       => ( isset( $values['inc_tax'] ) ? $values['inc_tax'] : '' ),
				'options'     => array(
					''    => _x( 'Default', 'setting option', 'woocommerce-store-credit' ),
					'yes' => _x( 'Yes', 'setting option', 'woocommerce-store-credit' ),
					'no'  => _x( 'No', 'setting option', 'woocommerce-store-credit' ),
				),
			);
		}

		if ( wc_shipping_enabled() ) {
			$fields['apply_to_shipping'] = array(
				'id'          => '_store_credit_apply_to_shipping',
				'label'       => _x( 'Apply to shipping', 'coupon: field label', 'woocommerce-store-credit' ),
				'description' => _x( 'Check this box to apply the remaining coupon amount to the shipping costs.', 'coupon: field desc', 'woocommerce-store-credit' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'value'       => ( isset( $values['apply_to_shipping'] ) ? $values['apply_to_shipping'] : '' ),
				'options'     => array(
					''    => _x( 'Default', 'setting option', 'woocommerce-store-credit' ),
					'yes' => _x( 'Yes', 'setting option', 'woocommerce-store-credit' ),
					'no'  => _x( 'No', 'setting option', 'woocommerce-store-credit' ),
				),
			);
		}

		$fields['allow_different_receiver'] = array(
			'id'          => '_store_credit_allow_different_receiver',
			'label'       => _x( 'Send to someone', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Allow purchasing credit for a different person.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'type'        => 'checkbox',
			'value'       => ( isset( $values['allow_different_receiver'] ) ? $values['allow_different_receiver'] : 'yes' ),
		);

		$fields['usage_restriction_section'] = array(
			'title' => _x( 'Usage restriction', 'coupon: section title', 'woocommerce-store-credit' ),
			'type'  => 'options_group',
		);

		$fields['individual_use'] = array(
			'id'          => '_store_credit_individual_use',
			'label'       => _x( 'Individual use only', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Check this box if the coupon cannot be used in conjunction with other coupons.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'type'        => 'checkbox',
			'value'       => ( isset( $values['individual_use'] ) ? $values['individual_use'] : 'no' ),
		);

		$fields['exclude_sale_items'] = array(
			'id'          => '_store_credit_exclude_sale_items',
			'label'       => _x( 'Exclude sale items', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Check this box if the coupon should not apply to items on sale.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'type'        => 'checkbox',
			'value'       => ( isset( $values['exclude_sale_items'] ) ? $values['exclude_sale_items'] : 'no' ),
		);

		$fields['products_group'] = array(
			'type' => 'options_group',
		);

		$fields['product_ids'] = array(
			'id'          => '_store_credit_product_ids',
			'label'       => _x( 'Products', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Product that the coupon will be applied to, or that need to be in the cart in order to be applied.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'desc_tip'    => true,
			'type'        => 'product_search',
			'multiple'    => true,
			'value'       => ( isset( $values['product_ids'] ) ? $values['product_ids'] : array() ),
		);

		$fields['excluded_product_ids'] = array(
			'id'          => '_store_credit_excluded_product_ids',
			'label'       => _x( 'Exclude products', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Product that the coupon will not be applied to, or that cannot be in the cart in order to be applied.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'desc_tip'    => true,
			'type'        => 'product_search',
			'multiple'    => true,
			'value'       => ( isset( $values['excluded_product_ids'] ) ? $values['excluded_product_ids'] : array() ),
		);

		$fields['product_categories_group'] = array(
			'type' => 'options_group',
		);

		$fields['product_categories'] = array(
			'id'          => '_store_credit_product_categories',
			'label'       => _x( 'Product categories', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Product categories that the coupon will be applied to, or that need to be in the cart in order to be applied.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'desc_tip'    => true,
			'type'        => 'product_categories',
			'value'       => ( isset( $values['product_categories'] ) ? $values['product_categories'] : array() ),
		);

		$fields['excluded_product_categories'] = array(
			'id'          => '_store_credit_excluded_product_categories',
			'label'       => _x( 'Exclude categories', 'coupon: field label', 'woocommerce-store-credit' ),
			'description' => _x( 'Product categories that the coupon will not be applied to, or that cannot be in the cart in order to be applied.', 'coupon: field desc', 'woocommerce-store-credit' ),
			'desc_tip'    => true,
			'type'        => 'product_categories',
			'value'       => ( isset( $values['excluded_product_categories'] ) ? $values['excluded_product_categories'] : array() ),
		);

		/**
		 * Filters the fields for the 'Store Credit' tab displayed in the product data meta box.
		 *
		 * @since 3.2.0
		 *
		 * @param array      $fields  An array with the fields data.
		 * @param WC_Product $product Product object.
		 */
		return apply_filters( 'wc_store_credit_product_data_fields', $fields, $product );
	}

	/**
	 * Outputs custom product data panels.
	 *
	 * @since 3.2.0
	 *
	 * @global WC_Product $product_object The current product object.
	 */
	public function product_data_panels() {
		global $product_object;

		$fields = $this->get_store_credit_fields( $product_object );

		include 'views/html-product-data-store-credit.php';
	}

	/**
	 * Saves additional product data.
	 *
	 * @since 3.2.0
	 *
	 * @param WC_Product $product Product object.
	 */
	public function save_product_data( $product ) {
		$fields = $this->get_store_credit_fields( $product );
		$values = array();

		foreach ( $fields as $key => $field ) {
			if ( isset( $field['type'] ) && 'options_group' === $field['type'] ) {
				continue;
			}

			$values[ $key ] = wc_store_credit_sanitize_meta_box_field( $field );
		}

		/**
		 * Filters the product data values of a 'Store Credit' product.
		 *
		 * @since 3.2.0
		 *
		 * @param array      $values  The product data values.
		 * @param array      $fields  An array with the fields data.
		 * @param WC_Product $product Product object.
		 */
		$values = apply_filters( 'wc_store_credit_product_data_values', array_filter( $values ), $fields, $product );

		if ( ! empty( $values ) ) {
			$product->update_meta_data( '_store_credit_data', $values );
		} else {
			$product->delete_meta_data( '_store_credit_data' );
		}
	}
}

return new WC_Store_Credit_Meta_Box_Product_Data();
