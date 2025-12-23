<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class: PNP_Admin
 *
 * - Hooks into admin_menu to create a settings page
 * - Registers all AJAX callbacks (pnp_get_products, pnp_toggle_active)
 * - Registers admin_post hooks for saving/deleting rules
 * - Renders the “Add/Edit Rule” form and the table of existing rules
 */
class PNP_Admin {

    /**
     * The constructor hooks everything we need:
     *   - admin_menu (for the settings page)
     *   - wp_ajax_*  (for dynamic product lists + toggling Active/Inactive)
     *   - admin_post_* (for saving / deleting the rule)
     */
    public function __construct() {
    // 1) Settings page under WooCommerce
    add_action( 'admin_menu', [ __CLASS__, 'init' ] );

    // 2) AJAX endpoints (called from the admin‐page JavaScript)
    add_action( 'wp_ajax_pnp_get_products',   [ __CLASS__, 'ajax_get_products' ] );
    add_action( 'wp_ajax_pnp_toggle_active',  [ __CLASS__, 'ajax_toggle_active' ] );
    add_action('wp_ajax_pnp_update_gift_stock', [__CLASS__, 'ajax_update_gift_stock']);
    add_action('wp_ajax_pnp_trash_gift_product', [__CLASS__, 'ajax_trash_gift_product']);
    // 3) Form‐submission endpoints (admin_post) for “Save” and “Delete”
    add_action( 'admin_post_pnp_save',   [ __CLASS__, 'save' ] );
    add_action( 'admin_post_pnp_delete', [ __CLASS__, 'delete' ] );

    // 4) Enqueue admin assets
    add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
}

    
    public static function enqueue_assets($hook) {
        if ('toplevel_page_pnp_settings' !== $hook) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style('pnp-admin-style', plugin_dir_url(__FILE__) . '../assets/css/admin-style.css', [], PNP_VERSION);
        wp_enqueue_script('pnp-admin-script', plugin_dir_url(__FILE__) . '../assets/js/admin-script.js', ['jquery'], PNP_VERSION, true);
        
        wp_localize_script('pnp-admin-script', 'pnpAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(PNP_NONCE),
            'gift_cat_id' => PNP_GIFT_CAT,
            'translations' => [
                'saveError' => __('Greška pri čuvanju', 'pokloni-popusti'),
            ]
        ]);
    }

    /**
     * Hook into admin_menu to create our settings page.
     */
    public static function init() {
    // Glavni meni MORA prvi
    add_menu_page(
        __( 'Pokloni & Popusti', 'pokloni-popusti' ),
        __( 'Pokloni & Popusti', 'pokloni-popusti' ),
        'manage_woocommerce',
        'pnp_settings',
        [ __CLASS__, 'render' ],
        'dashicons-gift',
        56
    );
    
    // Tek SADA dodaj sub-menu
    add_submenu_page(
        'pnp_settings',
        __( 'Upravljanje Poklonima', 'pokloni-popusti' ),
        __( 'Upravljanje Poklonima', 'pokloni-popusti' ),
        'manage_woocommerce',
        'pnp_gift_manager',
        function() {
            include PNP_PLUGIN_DIR . 'includes/view-gift-manager.php';
        }
    );
}

    /**
     * Render the admin screen: form to add/edit one rule + table of existing rules.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Nemate dozvolu za ovu stranicu.', 'pokloni-popusti' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . PNP_TABLE;

        // Fetch all rules, order by priority DESC → ID ASC
        $rules = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority DESC, id ASC", ARRAY_A );

        // If ?edit=xxx is in the URL, load that rule's data
        $edit_id = absint( $_GET['edit'] ?? 0 );
        $row     = [];
        if ( $edit_id ) {
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ),
                ARRAY_A
            );
            if ( ! $row ) {
                $edit_id = 0;
            }
        }

        // Fill defaults if we're not editing an existing row
        $f = wp_parse_args( (array) $row, [
            'id'               => 0,
            'buy_x_qty'        => 1,
            'buy_x_scope'      => 'linija', // default changed to 'linija'
            'buy_x_term'       => '',
            'buy_x_ids'        => '',
            'enable_buy_y'     => 0,
            'buy_y_qty'        => 1,
            'buy_y_scope'      => 'linija', // default changed to 'linija'
            'buy_y_term'       => '',
            'buy_y_ids'        => '',
            'enable_cart'      => 0,
            'cart_val'         => '',
            'cart_scope'       => 'linija', // default changed to 'linija'
            'cart_term'        => '',
            'cart_ids'         => '',
            'exclude_sale'     => 0,
            'reward_qty'       => 1,
            'reward_type'      => 'gift',
            'reward_scope'     => 'linija', // default changed to 'linija'
            'reward_term'      => '',
            'reward_ids'       => '',
            'reward_max_price' => '',
            'no_time_limit'    => 0,
            'schedule_start'   => '',
            'schedule_end'     => '',
            'active'           => 1,
            'priority'         => intval( $row['priority'] ?? 10 ),
        ] );

        // Create a nonce for all AJAX requests in the form
        $nonce     = wp_create_nonce( PNP_NONCE );
        $all_terms = [
            'product_cat' => get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ),
            'brend'       => get_terms( [ 'taxonomy' => 'brend',       'hide_empty' => false ] ),
            'linija'      => get_terms( [ 'taxonomy' => 'linija',      'hide_empty' => false ] ),
        ];

        // Finally, include the HTML/PHP view
        include PNP_PLUGIN_DIR . 'includes/view-admin.php';
    }

    /**
     * Save (create or update) a rule. Uses POST → admin-post.php?action=pnp_save
     */
    public static function save() {
    // *** DEBUG START ***
    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        error_log( 'PNP_SAVE $_POST: ' . print_r( $_POST, true ) );
    }
    // *** DEBUG END ***

    // 1) Provera dozvola + nonce
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'Nemate dozvolu za ovu radnju.', 'pokloni-popusti' ) );
    }
    if ( ! check_admin_referer( 'pnp_save', PNP_NONCE ) ) {
        wp_die( esc_html__( 'Nevažeći zahtev.', 'pokloni-popusti' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . PNP_TABLE;
    $now   = current_time( 'mysql', 0 );
    $id    = absint( $_POST['id'] ?? 0 );
    $rtype = sanitize_text_field( $_POST['reward_type'] ?? '' );
    $cond  = sanitize_text_field( $_POST['cond_type']   ?? '' );

    // 2) Osnovna polja
    $d = [
        'reward_type'      => $rtype,
        'reward_qty'       => absint( $_POST['reward_qty'] ?? 1 ),
        'reward_max_price' => floatval( $_POST['reward_max_price'] ?? 0 ),
        'no_time_limit'    => isset( $_POST['no_time_limit'] ) ? 1 : 0,
        'schedule_start'   => self::sanitize_datetime_local( $_POST['schedule_start'] ?? '' ),
        'schedule_end'     => self::sanitize_datetime_local( $_POST['schedule_end']   ?? '' ),
        'exclude_sale'     => isset( $_POST['exclude_sale'] )   ? 1 : 0,
        'active'           => isset( $_POST['active'] )         ? 1 : 0,
        'priority'         => absint( $_POST['priority']    ?? 10 ),

        'buy_x_qty'        => absint( $_POST['buy_x_qty']   ?? 1 ),
        'buy_x_scope'      => sanitize_text_field( $_POST['buy_x_scope'] ?? '' ),
        'buy_x_term'       => absint( $_POST['buy_x_term']  ?? 0 ),

        'enable_buy_y'     => $cond === 'buy_xy' ? 1 : 0,
        'buy_y_qty'        => absint( $_POST['buy_y_qty']    ?? 0 ),
        'buy_y_scope'      => sanitize_text_field( $_POST['buy_y_scope'] ?? '' ),
        'buy_y_term'       => absint( $_POST['buy_y_term']  ?? 0 ),

        'enable_cart'      => $cond === 'cart' ? 1 : 0,
        'cart_val'         => floatval( $_POST['cart_val']   ?? 0 ),
        'cart_scope'       => sanitize_text_field( $_POST['cart_scope'] ?? '' ),
        'cart_term'        => absint( $_POST['cart_term']  ?? 0 ),
    ];

    // 3) ID-jevi liste: buy_x, buy_y, cart
    $d['buy_x_ids'] = sanitize_text_field( $_POST['buy_x_ids'] ?? '' );
    $d['buy_y_ids'] = sanitize_text_field( $_POST['buy_y_ids'] ?? '' );
    $d['cart_ids']  = sanitize_text_field( $_POST['cart_ids']  ?? '' );

    // 4) ID-jevi nagrade: checkboxovi za gift vs. hidden za free
    if ( $rtype === 'gift' ) {
        // Uzimamo samo niz iz checkboxova reward_ids[]
        $posted = $_POST['reward_ids'] ?? [];
        $ids    = is_array( $posted )
                    ? array_filter( array_map( 'absint', $posted ) )
                    : [];
        $d['reward_ids']   = implode( ',', $ids );
        $d['reward_scope'] = '';
        $d['reward_term']  = '';
    } else {
        // Za „free“ čitamo iz hidden polja reward_ids_free
        $d['reward_ids']   = sanitize_text_field( $_POST['reward_ids_free'] ?? '' );
        $d['reward_scope'] = sanitize_text_field( $_POST['reward_scope'] ?? '' );
        $d['reward_term']  = absint( $_POST['reward_term']  ?? 0 );
    }

    // 5) Timestamp i upsert
    if ( $id ) {
        $d['updated_at'] = $now;
        $wpdb->update( $table, $d, [ 'id' => $id ] );
    } else {
        $d['created_at'] = $now;
        $d['updated_at'] = $now;
        $wpdb->insert( $table, $d );
    }

    // 6) Redirect
    wp_safe_redirect( admin_url( 'admin.php?page=pnp_settings' ) );
    exit;
}


    public static function delete() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'Nemate dozvolu za ovu radnju.', 'pokloni-popusti' ) );
    }
    if ( ! check_admin_referer( 'pnp_delete', PNP_NONCE ) ) {
        wp_die( esc_html__( 'Nevažeći zahtev.', 'pokloni-popusti' ) );
    }

    $id = absint( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_die( esc_html__( 'Nedostaje ID pravila.', 'pokloni-popusti' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . PNP_TABLE;

    // 1) Obriši pravilo iz baze
    $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

    // 2) Očisti eventualne ostatke u sesiji
    if ( WC()->session ) {
        $gifts = (array) WC()->session->get( 'pnp_gifts_by_rule', [] );
        unset( $gifts[ $id ] );
        WC()->session->set( 'pnp_gifts_by_rule', $gifts );

        $done = (array) WC()->session->get( 'pnp_done_rules', [] );
        $done = array_diff( $done, [ $id ] );
        WC()->session->set( 'pnp_done_rules', array_values( $done ) );
    }

    // 3) Ukloni iz korpe sve linije povezane sa ovim pravilom
    if ( WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $ckey => $item ) {
            if ( ! empty( $item['pnp_rule'] ) && (int) $item['pnp_rule'] === $id ) {
                WC()->cart->remove_cart_item( $ckey );
            }
        }
    }

    // 4) Povratak na listu
    wp_safe_redirect( admin_url( 'admin.php?page=pnp_settings&deleted=1' ) );
    exit;
}



    /**
     * AJAX endpoint: return product list for a given taxonomy‐term (used in the “Choose products” boxes).
     */
    public static function ajax_get_products() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Nemate dozvolu.', 'pokloni-popusti' ), 403 );
        }
        if ( ! check_ajax_referer( PNP_NONCE, 'nonce', false ) ) {
            wp_send_json_error( __( 'Nevažeći zahtev.', 'pokloni-popusti' ), 403 );
        }

        $tax  = sanitize_key( $_POST['tax'] ?? '' );
        $term = absint( $_POST['ids'] ?? 0 );

        // Validate taxonomy
        if ( 'product' !== $tax && ! in_array( $tax, [ 'product_cat', 'brend', 'linija' ], true ) ) {
            wp_send_json_error( __( 'Neispravan opseg.', 'pokloni-popusti' ), 400 );
        }

        // If “product,” list all products
        if ( 'product' === $tax ) {
            $posts = get_posts( [
                'post_type'   => 'product',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
            ] );
        } else {
            // term‐based
            if ( ! $term ) {
                wp_send_json_error( __( 'Nedostaje termin.', 'pokloni-popusti' ), 400 );
            }
            $posts = get_posts( [
                'post_type'   => 'product',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
                'tax_query'   => [
                    [
                        'taxonomy' => $tax,
                        'field'    => 'term_id',
                        'terms'    => $term,
                    ],
                ],
            ] );
        }

        if ( empty( $posts ) ) {
            wp_send_json_error( __( 'Nema proizvoda.', 'pokloni-popusti' ) );
        }

        $data = array_map( function( $p ) {
            return [ 'id' => $p->ID, 'title' => $p->post_title ];
        }, $posts );

        wp_send_json_success( $data );
    }

    /**
     * AJAX endpoint: toggle rule Active/Inactive via POST.
     */
    public static function ajax_toggle_active() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Nemate dozvolu.', 'pokloni-popusti' ), '', 403 );
        }
        if ( ! check_ajax_referer( PNP_NONCE, 'nonce', false ) ) {
            wp_die( __( 'Nevažeći zahtev.', 'pokloni-popusti' ), '', 403 );
        }

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_die( __( 'Nedostaje ID.', 'pokloni-popusti' ), '', 400 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . PNP_TABLE;
        $result = $wpdb->query(
            $wpdb->prepare( "UPDATE {$table} SET active = 1 - active WHERE id = %d", $id )
        );

        if ( false === $result ) {
            wp_die( __( 'Greška baze podataka.', 'pokloni-popusti' ), '', 500 );
        }

        wp_die(); // success (no content)
    }

    /**
     * Helper: sanitize “datetime-local” input (HTML5) to MySQL DATETIME ("YYYY-MM-DD HH:II:SS").
     * Input example: "2025-06-02T14:30"
     */
    private static function sanitize_datetime_local( $input ) {
        $input = sanitize_text_field( $input );
        if ( empty( $input ) ) {
            return '';
        }
        // Expect pattern: YYYY-MM-DDThh:mm
        $dt = DateTime::createFromFormat( 'Y-m-d\TH:i', $input, wp_timezone() );
        if ( $dt instanceof DateTime ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }
        return '';
    }

    // UPDATE STOCK
public static function ajax_update_gift_stock() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Nemate dozvolu.', 403);
    }
    if (!check_ajax_referer(PNP_NONCE, 'nonce', false)) {
        wp_send_json_error('Nevažeći zahtev.', 403);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? floatval($_POST['stock']) : null;

    if (!$product_id) {
        wp_send_json_error('Nedostaje ID proizvoda.', 400);
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Proizvod ne postoji.', 404);
    }

    if ($stock !== null) {
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
    } else {
        $product->set_manage_stock(false);
    }

    $product->save();
    wp_send_json_success();
}

// TRASH PRODUCT
public static function ajax_trash_gift_product() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Nemate dozvolu.', 403);
    }
    if (!check_ajax_referer(PNP_NONCE, 'nonce', false)) {
        wp_send_json_error('Nevažeći zahtev.', 403);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) {
        wp_send_json_error('Nedostaje ID proizvoda.', 400);
    }

    $result = wp_trash_post($product_id);
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Greška pri brisanju proizvoda.');
    }
}
}