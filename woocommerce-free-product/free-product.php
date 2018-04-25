<?php
/*
Plugin Name: Coupon Free Product
Plugin URI: https://github.com/RenewedPlains/WooCommerce-Product-Coupon
Description: Requires WooCommerce. Adds a tab in the coupon menu for a linked product assignment. As soon as the given coupon code is added to the shopping cart, the product is automatically added to the shopping cart.
Version: 1.0
Author: RenewedPlains
Author URI: http://webcoder.ch
License: GPL
Text Domain: woocommerce-freeproduct
Domain: woocommerce-freeproduct
*/


// Load the text domain from plugin in php
function freeproduct_textdomain() {
	load_plugin_textdomain( 'woocommerce-freeproduct', false, basename( dirname( __FILE__ ) ) . '/lang' );
}
add_action( 'plugins_loaded', 'freeproduct_textdomain' );


// Modify WC-Tab navigation on coupon backend
function filter_woocommerce_coupon_data_tabs( $array ) {
	$array = array(
		'general' => array(
			'label'  => __( 'General', 'woocommerce' ),
			'target' => 'general_coupon_data',
			'class'  => 'general_coupon_data',
		),
		'usage_restriction' => array(
			'label'  => __( 'Usage restriction', 'woocommerce' ),
			'target' => 'usage_restriction_coupon_data',
			'class'  => '',
		),
		'usage_limit' => array(
			'label'  => __( 'Usage limits', 'woocommerce' ),
			'target' => 'usage_limit_coupon_data',
			'class'  => '',
		),
		'carrot' => array(
			'label'  => __( 'Free product', 'woocommerce-freeproduct' ),
			'icon' => 'carrot',
			'target' => 'add_free_product',
			'class'  => '',
		),
	);
	return $array;
};
add_filter( 'woocommerce_coupon_data_tabs', 'filter_woocommerce_coupon_data_tabs', 10, 1 );


// Add stylesheet and javascript in backend
function freeproduct_scripts( ) {
	wp_enqueue_style( 'admin-styles', plugin_dir_url( __FILE__ ) . 'css/freeproduct.css' );
	wp_enqueue_script( 'freeproduct', plugin_dir_url( __FILE__ ) . 'js/freeproduct.js' );
}
add_action( 'admin_enqueue_scripts', 'freeproduct_scripts' );


// Define panel script with productsearch
function action_woocommerce_coupon_options_usage_limit( $coupon_get_id ) {
	echo '<div id="add_free_product" class="freeproductpanel woocommerce_options_panel panel">
	<div class="options_group">
		<p>'. __( 'Select one of your products. This product will be automatically added to the shopping cart as soon as the corresponding voucher code is used in the shopping cart.', 'woocommerce-freeproduct' ) .'</p>'; ?>
		<p class="form-field">
			<label for="freeproductid"><?php esc_html_e( 'Product', 'woocommerce' ); ?></label>
			<select class="wc-product-search"  style="width: 50%;" id="freeproductid" name="freeproduct" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations">
				<?php
				$product_ids = array( get_post_meta( $coupon_get_id, 'freeproductid', true ) );
				foreach ( $product_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( is_object( $product ) ) {
						echo '<option value="' . esc_attr( $product_id ) . '" ' . selected( esc_attr( $product_id ), get_post_meta( $coupon_get_id, 'freeproductid', true ), false ) . '>' . wp_kses_post( $product->get_formatted_name( ) ) . '</option>';
					}
				}
				?>
			</select>
			<span class="dashicons dashicons-no-alt deletefreeproduct"></span>
		</p>
	</div>
	<p><?php _e( '<strong>Note:</strong> The selected product is not automatically modified. Make your settings under <a href="http://compute.local/wp-admin/edit.php?post_type=product">Products</a> (Hide in searchresults, set prices to 0...).', 'woocommerce-freeproduct' ); ?></p></div><?php
}
add_filter( 'woocommerce_coupon_data_panels', 'action_woocommerce_coupon_options_usage_limit', 10, 2 );


// Save the selectboxvalue in database in post_meta
function save_code( $coupon_get_id ) {
	$freeproductid = $_POST['freeproduct'];
	update_post_meta( $coupon_get_id, 'freeproductid', $freeproductid );
}
add_action( 'woocommerce_coupon_options_save', 'save_code' );


// Function for spellcheck (matchcase)
function in_arrayi( $needle, $haystack ) {
	return in_array( strtolower( $needle ), array_map('strtolower', $haystack ) );
}


// Show information on coupon overview in type column
function custom_columns( $column, $post_id ) {
	switch ( $column ) {
		case 'type':
			$terms = get_post_meta( $post_id, 'freeproductid', true );
			if ( $terms != '' ) {
				$freeproducter = wc_get_product( $terms );
				echo $freeproducter->get_formatted_name( );
				echo '<br />';
			} else {
				_e( 'No free product', 'woocommerce-freeproduct' );
				echo '<br />';
			}
			break;
	}
}
add_action( 'manage_posts_custom_column' , 'custom_columns', 10, 2 );


// Output from plugin by adding a coupon to cart
function cart_frontend( ) {
	global $woocommerce;
	$args = array(
		'posts_per_page' => -1,
		'post_type' => 'shop_coupon',
		'post_status' => 'publish'
	);
	$coupons = get_posts( $args );
	foreach ( $coupons as $coupon ) {
		if ( get_post_meta( $coupon->ID, 'freeproductid', true ) != '' ) {
			$coupontitle = $coupon->post_title;
			$product_id = get_post_meta( $coupon->ID, 'freeproductid', true );
			if( in_arrayi( $coupontitle, $woocommerce->cart->applied_coupons ) ){
				//check if product already in cart
				if ( sizeof( $woocommerce->cart->get_cart( ) ) > 0 ) {
					foreach ( $woocommerce->cart->get_cart( ) as $cart_item_key => $values ) {
						$_product = $values['data'];
						if ( $_product->id == $product_id )
							$found = true;
					}
					// if product not found, add it
					if ( ! $found )
						$woocommerce->cart->add_to_cart( $product_id );
				} else {
					// if no products in cart, add it
					$woocommerce->cart->add_to_cart( $product_id );
				}
			}
		} else {
			continue;
		}
	}
}
add_action('woocommerce_check_cart_items', 'cart_frontend');