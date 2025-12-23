<?php
/**
 * Plugin Name:  Pokloni & Popusti – BOGO / Gifts / Discounts for WooCommerce
 * Description:  Korak-po-korak BOGO, gratis i poklon logika za WooCommerce.
 * Version:      1.0.22
 * Requires PHP: 7.4
 * Author:       Suavemente
 * Licence:      GPL-2.0+
 * Text Domain:  pokloni-popusti
 * Domain Path:  /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ─────────────────────────────────────────────────────────
 *  CONSTANTS
 * ──────────────────────────────────────────────────────── */
define( 'PNP_VERSION',    '1.0.22' );
define( 'PNP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PNP_TABLE',      'pnp_rules' );
define( 'PNP_GIFT_CAT',   13112 );
define( 'PNP_NONCE',      'pnp_nonce' );

/* ─────────────────────────────────────────────────────────
 *  LOAD COMPONENTS
 * ──────────────────────────────────────────────────────── */
require_once PNP_PLUGIN_DIR . 'includes/class-pnp-text-manager.php';
require_once PNP_PLUGIN_DIR . 'includes/class-pnp-helpers.php';
require_once PNP_PLUGIN_DIR . 'includes/pnp-rules.php';
require_once PNP_PLUGIN_DIR . 'includes/class-pnp-admin.php';
require_once PNP_PLUGIN_DIR . 'includes/class-pnp-shortcode.php';
require_once PNP_PLUGIN_DIR . 'includes/class-pnp-offer-cards.php';

/* ─────────────────────────────────────────────────────────
 *  LOCALISATION
 * ──────────────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain(
        'pokloni-popusti',
        false,
        basename( __DIR__ ) . '/languages'
    );
} );

/* ─────────────────────────────────────────────────────────
 *  HOOKS (front end)
 * ──────────────────────────────────────────────────────── */
add_action( 'woocommerce_before_calculate_totals', [ 'PNP_Rules', 'reset_prices' ],  1 );
add_action( 'woocommerce_before_calculate_totals', [ 'PNP_Rules', 'apply_rules' ], 20 );
add_action( 'woocommerce_cart_loaded_from_session', [ 'PNP_Rules', 'apply_rules' ], 20, 1 );
add_action( 'woocommerce_cart_item_removed',       [ 'PNP_Rules', 'cart_item_removed' ], 10, 2 );

add_action('woocommerce_add_to_cart_validation', function($valid, $product_id) {
    if (has_term(PNP_GIFT_CAT, 'product_cat', $product_id)) {
        wc_add_notice('Ovaj proizvod može biti dodat samo kao poklon uz drugu kupovinu.', 'error');
        return false;
    }
    return $valid;
}, 10, 2);

add_filter( 'woocommerce_is_purchasable', [ 'PNP_Rules', 'filter_purchasable' ], 9, 2 );
add_filter( 'woocommerce_get_price_html', [ 'PNP_Rules', 'replace_price_html' ], 10, 2 );
add_action( 'woocommerce_single_product_summary', [ 'PNP_Rules', 'show_poklon_message' ], 6 );

add_action( 'woocommerce_cart_emptied', [ 'PNP_Rules', 'reset_session' ] );

/* ─────────────────────────────────────────────────────────
 *  AJAX ENDPOINTS (front + admin)
 * ──────────────────────────────────────────────────────── */
add_action( 'wp_ajax_pnp_pick_gift',        [ 'PNP_Rules', 'ajax_pick_gift' ] );
add_action( 'wp_ajax_nopriv_pnp_pick_gift', [ 'PNP_Rules', 'ajax_pick_gift' ] );

add_action( 'wp_ajax_pnp_get_products',  [ 'PNP_Admin', 'ajax_get_products' ] );
add_action( 'wp_ajax_pnp_toggle_active', [ 'PNP_Admin', 'ajax_toggle_active' ] );

/* ─────────────────────────────────────────────────────────
 *  DB TABLES – activation
 * ──────────────────────────────────────────────────────── */
register_activation_hook( __FILE__, [ 'PNP_Rules', 'install_tables' ] );

/* instantiate UI classes */
new PNP_Admin();
new PNP_Shortcode();
