<?php
/**
 * Plugin Name: WooCommerce Mix and Match -  Random selections
 * Plugin URI: http://www.woocommerce.com/products/wc-mnm-random/
 * Description: Add to cart button fills Mix and Match container with random selection.
 * Version: 1.0.0-beta-1
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * Developer: Kathy Darling
 * Developer URI: http://kathyisawesome.com/
 * Text Domain: wc-mnm-random
 * Domain Path: /languages
 *
 * Copyright: © 2021 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace WC_MNM_Random;

define( 'WC_MNM_Random_VERSION', '1.0.0-beta-1' );

/**
 * Attach hooks and filters
 */
function init() {

	// Load translation files.
	add_action( 'init', __NAMESPACE__ . '\load_plugin_textdomain' );

	// Add extra meta.
	add_action( 'woocommerce_mnm_product_options', __NAMESPACE__ . '\additional_container_option' , 15, 2 );
	add_action( 'woocommerce_admin_process_product_object', __NAMESPACE__ . '\process_meta', 20 );

	// Shop loop.
	add_filter( 'woocommerce_product_add_to_cart_description', __NAMESPACE__ . '\loop_add_to_cart_description', 10, 2 );
	add_filter( 'woocommerce_product_add_to_cart_text', __NAMESPACE__ . '\loop_add_to_cart_text', 10, 2 );
	add_filter( 'woocommerce_product_has_options', __NAMESPACE__ . '\has_options', 10, 2 );
	add_filter( 'woocommerce_product_supports', __NAMESPACE__ . '\product_supports', 10, 3 );

	// Maybe swap the add to cart template front end.
	add_action( 'woocommerce_mix-and-match_add_to_cart', __NAMESPACE__ . '\single_add_to_cart_template', -1 );

	// Set cart config.
	add_filter( 'woocommerce_mnm_get_posted_container_configuration', __NAMESPACE__ . '\randomize_config', 10, 2 );

	// Disable "Edit" link in cart.
	add_filter( 'wc_mnm_show_edit_it_cart', __NAMESPACE__ . '\disable_edit_in_cart', 10, 2 );

}


/*-----------------------------------------------------------------------------------*/
/* Localization */
/*-----------------------------------------------------------------------------------*/


/**
 * Make the plugin translation ready
 *
 * @return void
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain( 'wc-mnm-random' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
}


/*-----------------------------------------------------------------------------------*/
/* Admin */
/*-----------------------------------------------------------------------------------*/


/**
 * Adds the "random" checkbox.
 *
 * @param int $post_id
 * @param  WC_Product_Mix_and_Match  $mnm_product_object
 */
function additional_container_option( $post_id, $mnm_product_object ) {
	woocommerce_wp_checkbox( array(
		'id'            => '_mnm_random',
		'label'       => __( 'Randomize for customers', 'wc-mnm-random' )
	) );
}

/**
 * Saves the new meta field.
 *
 * @param  WC_Product_Mix_and_Match  $product
 */
function process_meta( $product ) {
	if ( isset( $_POST[ '_mnm_random' ] ) ) {
		$product->update_meta_data( '_mnm_random', 'yes' );
	} else {
		$product->update_meta_data( '_mnm_random', 'no' );
	}
}


/*-----------------------------------------------------------------------------------*/
/* Front End Display */
/*-----------------------------------------------------------------------------------*/

/**
 * Saves the new meta field.
 *
 * @param  string $text
 * @param  WC_Product  $product
 * @return string
 */
function loop_add_to_cart_text( $text, $product ) {
	if ( is_random( $product ) ) {
		$text = esc_html__( 'Add to cart', 'woocommerce' );
	}
	return $text;
}


/**
 * Aria add to cart description.
 * Revert back to Simple product.
 *
 * @param  string $text
 * @param  WC_Product  $product
 * @return string
 */
function loop_add_to_cart_description( $text, $product ) {
	if ( is_random( $product ) ) {
		/* translators: %s: Product title */
		$text = $product->is_purchasable() && $product->is_in_stock() ? __( 'Add &ldquo;%s&rdquo; to your cart', 'woocommerce' ) : __( 'Read more about &ldquo;%s&rdquo;', 'woocommerce' );
	}
	return $text;
}


/**
 * Tells Woo this product does NOT have options.
 * Should re-enable ajax add to cart in the loop.
 *
 * @param  bool $has_options
 * @param  WC_Product  $product
 * @return bool
 */
function has_options( $has_options, $product ) {
	if ( is_random( $product ) ) {
		$has_options = false;
	}
	return $has_options;
}

/**
 * Tells Woo this product does NOT have options.
 * Should re-enable ajax add to cart in the loop.
 *
 * @param  bool $supports
 * @param  string $feature string The name of a feature to test support for.
 * @param  WC_Product  $product
 * @return bool
 */
function product_supports( $supports, $feature, $product ) {
	if ( 'ajax_add_to_cart' === $feature && is_random( $product ) ) {
		$supports = true;
	}
	return $supports;
}

/**
 * Maybe swap add to cart template.
 */
function single_add_to_cart_template() { 

	global $product;

	if ( $product && is_random( $product ) ) {
		remove_action( 'woocommerce_mix-and-match_add_to_cart', 'wc_mnm_template_add_to_cart' );
		add_action( 'woocommerce_mix-and-match_add_to_cart', 'woocommerce_simple_add_to_cart' );
	}

}


/*-----------------------------------------------------------------------------------*/
/* Cart  */
/*-----------------------------------------------------------------------------------*/


/**
 * Randomize the container config on add to cart.
 *
 * @param array $config
 * @param WC_Mix_and_Match_Product $product
 * @return array
 */
function randomize_config( $config, $product ) {
	
	if ( is_random( $product ) ) {

		// Randomize order.
		$allowed_contents = shuffle_assoc( $product->get_available_children() );

		$min = $product->get_min_container_size();
		$max = $product->get_max_container_size();

		// Fallback for edge cases where min is 0 and max is unlimited.
		if ( $min !== $max ) {
			if ( '' === $max ) {
				$max = $min + 10;
			}
			$min = rand( $min, $max );
		}

		$counter = 0;
		$config = array();
		$qty_incr = 1;

		while( $counter < $min ) {
	
			foreach( $allowed_contents as $child_id => $child_product ) {

				// Does this product exist in the config and does it have enough stock for the new value.
				if ( isset( $config[ $child_id ] ) ) {

					$qty_check = $config[ $child_id ]['quantity'] + $qty_incr;
					$has = $child_product->has_enough_stock( $qty_check );

					// If there's enough stock for the additional quantity add it, otherwise continue.
					if ( $child_product->has_enough_stock( $config[ $child_id ]['quantity'] + $qty_incr )  ) {
						$config[ $child_id ]['quantity'] = $config[ $child_id ]['quantity'] + $qty_incr;
						$counter++;
					}
					
				} else {

					$parent_id = $child_product->get_parent_id();
					
					$config[ $child_id ] = array();
					$config[ $child_id ]['mnm_child_id'] = $child_id;
					$config[ $child_id ]['product_id']   = $parent_id > 0 ? $parent_id : $child_product->get_id();
					$config[ $child_id ]['variation_id'] = $parent_id > 0 ? $child_product->get_id() : 0;
					$config[ $child_id ]['quantity']     = $qty_incr;
					$config[ $child_id ]['variation']    = $parent_id > 0 ? $child_product->get_variation_attributes() : array();

					error_log("FIRST time around for " . $child_product->get_title() );

					$counter++;
				}

				if ( $min === $counter ) {
					break;
				}
			}
		
		}
	}

	return $config;
}


/**
 * Disable "edit" link in cart.
 *
 * @param bool $show
 * @param  array    $cart_item
 * @return bool
 */
function disable_edit_in_cart( $show, $cart_item ) {
	
	if ( is_random( $cart_item[ 'data' ] ) ) {
		$show = false;
	}

	return $show;
}

/*-----------------------------------------------------------------------------------*/
/* Helpers */
/*-----------------------------------------------------------------------------------*/

/**
 * Use random Mix and Match
 *
 * @param  obj WC_Product $product
 * @return bool
 */
function is_random( $product ) {
	return $product && $product->is_type( 'mix-and-match' ) && 'yes' == $product->get_meta( '_mnm_random', true, 'edit' );
}

/**
 * Shuffle an associative array, preserving key, value pairs
 *
 * @see: https://www.w3resource.com/php-exercises/php-array-exercise-26.php
 * 
 * @param  array $array
 * @return array
 */
function shuffle_assoc( $array ) {
	$keys = array_keys( $array );

	shuffle( $keys );

	foreach( $keys as $key ) {
		$new[ $key ] = $array[ $key ];
	}

	$array = $new;

	return $array;
}

/*-----------------------------------------------------------------------------------*/
/* Launch the whole plugin. */
/*-----------------------------------------------------------------------------------*/
add_action( 'woocommerce_mnm_loaded', __NAMESPACE__ . '\init' );
