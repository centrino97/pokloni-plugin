<?php
/**
 * Pokloni & Popusti – class-pnp-rules.php
 * FINAL build 2025-06-17 00:20
 *
 * ➊ DB table install/upgrade
 * ➋ Rule evaluation → add gifts / queue pickers
 * ➌ Fix “4-item recursion” & stale-gift issues
 * ➍ Instant re-evaluation when a cart item is removed
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ========================================================================== */
/*  MAIN CLASS                                                                */
/* ========================================================================== */
class PNP_Rules {

    /* ───────────────────────────────────────────────────────────────
     *  Small helpers
     * ──────────────────────────────────────────────────────────── */
    private static function reward_already_in_cart( $cart, $rule_id ) {
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['pnp_flag'] )
                 && intval( $item['pnp_rule'] ) === intval( $rule_id ) ) {
                return true;
            }
        }
        return false;
    }

    private static function mark_rule_done( array &$done, $rule_id ) {
        if ( ! in_array( $rule_id, $done, true ) ) {
            $done[] = $rule_id;
            WC()->session->set( 'pnp_done_rules', array_unique( $done ) );
        }
    }

    /* ====================================================================== */
    /*  1) INSTALL / UPGRADE TABLES (unchanged from plugin v1.0.21)           */
    /* ====================================================================== */
    public static function install_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table1  = $wpdb->prefix . PNP_TABLE;
        $table2  = $wpdb->prefix . 'pnp_user_gifts';

        /* rules table */
        dbDelta( "CREATE TABLE {$table1} (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            buy_x_qty INT(5) NOT NULL,
            buy_x_scope VARCHAR(20) NOT NULL,
            buy_x_term VARCHAR(20) NOT NULL,
            buy_x_ids TEXT,
            enable_buy_y TINYINT(1) NOT NULL DEFAULT 0,
            buy_y_qty INT(5),
            buy_y_scope VARCHAR(20),
            buy_y_term VARCHAR(20),
            buy_y_ids TEXT,
            enable_cart TINYINT(1) NOT NULL DEFAULT 0,
            cart_val DECIMAL(10,2),
            cart_scope VARCHAR(20),
            cart_term VARCHAR(20),
            cart_ids TEXT,
            exclude_sale TINYINT(1) NOT NULL DEFAULT 0,
            reward_qty INT(5) NOT NULL DEFAULT 1,
            reward_type VARCHAR(20) NOT NULL,
            reward_ids TEXT,
            reward_scope VARCHAR(20),
            reward_term VARCHAR(20),
            reward_max_price DECIMAL(10,2),
            no_time_limit TINYINT(1) NOT NULL DEFAULT 0,
            schedule_start DATETIME,
            schedule_end DATETIME,
            active TINYINT(1) NOT NULL DEFAULT 1,
            priority SMALLINT NOT NULL DEFAULT 10,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY active_priority (active,priority)
        ) ENGINE=InnoDB {$charset};" );

        /* which gift was assigned to which order */
        dbDelta( "CREATE TABLE {$table2} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) NOT NULL,
            user_id BIGINT(20),
            rule_id MEDIUMINT(9) NOT NULL,
            gift_product_id BIGINT(20) NOT NULL,
            date_assigned DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'assigned',
            PRIMARY KEY (id),
            KEY order_idx (order_id),
            KEY user_idx  (user_id),
            KEY rule_idx  (rule_id),
            KEY gift_idx  (gift_product_id)
        ) ENGINE=InnoDB {$charset};" );
    }

    /* ====================================================================== */
    /*  2) GENERIC HELPERS (scope checks, counting, sums)                     */
    /* ====================================================================== */
    public static function product_in_scope( $pid, $scope, $term, $ids_csv ) {
        $pid = absint( $pid );
        $prod = wc_get_product( $pid );
        if ( $prod && $prod->is_type( 'variation' ) ) {
            $pid = $prod->get_parent_id();
        }
        $ids = array_filter( array_map( 'absint', explode( ',', $ids_csv ) ) );

        switch ( $scope ) {
            case 'product':
                return $ids ? in_array( $pid, $ids, true )
                            : ( $pid === absint( $term ) );

            case 'product_cat':
            case 'brend':
            case 'linija':
                return $term ? has_term( absint( $term ), $scope, $pid ) : false;

            default:
                return false;
        }
    }

    public static function count_items_in_scope( $cart, $scope, $term,
                                                 $ids_csv, $allow_sale ) {
        $cnt = 0;
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['pnp_flag'] ) ) continue;
            $p = $item['data'];
            if ( ! $allow_sale && $p->is_on_sale() ) continue;

            if ( self::product_in_scope( $item['product_id'],
                                         $scope, $term, $ids_csv ) ) {
                $cnt += (int) $item['quantity'];
            }
        }
        return $cnt;
    }

    public static function item_keys_in_scope( $cart, $scope, $term, $ids_csv, $allow_sale ) {
    $keys = [];
    foreach ( $cart->get_cart() as $ckey => $item ) {
        if ( ! empty( $item['pnp_flag'] ) ) continue;
        $p = $item['data'];
        if ( ! $p ) continue;
        if ( $p->is_on_sale() && ! $allow_sale ) continue;

        if ( self::product_in_scope( $item['product_id'], $scope, $term, $ids_csv ) ) {
            $qty  = max( 1, (int) $item['quantity'] );
            // “proširi” po količini radi lakšeg oduzimanja preklapanja
            $keys = array_merge( $keys, array_fill( 0, $qty, $ckey ) );
        }
    }
    return $keys;
}


    public static function sum_items_value_in_scope( $cart, $scope, $term,
                                                     $ids_csv, $allow_sale ) {
        $cents = 0;
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['pnp_flag'] ) ) continue;
            $p = $item['data'];
            if ( ! $allow_sale && $p->is_on_sale() ) continue;

            if ( self::product_in_scope( $item['product_id'],
                                         $scope, $term, $ids_csv ) ) {
                $line_total = isset( $item['line_total'] )
                ? (float) $item['line_total']
                : (float) $p->get_price() * (int) $item['quantity'];

            $cents += (int) round( $line_total * 100 );

            }
        }
        return $cents / 100;
    }

    /* ====================================================================== */
    /*  3) BEFORE any rule logic – reset prices to original                   */
    /* ====================================================================== */
    public static function reset_prices( $cart ) {
        foreach ( $cart->get_cart() as &$item ) {
            if ( isset( $item['_orig_price'] ) ) {
                $item['data']->set_price( (float) $item['_orig_price'] );
            } else {
                $item['_orig_price'] = (float) $item['data']->get_price();
            }
        }
    }

    /* ====================================================================== */
    /*  4) MAIN RULE ENGINE (patched)                                         */
    /* ====================================================================== */
    public static function apply_rules( $cart ) {

        if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . PNP_TABLE;

        /* map any existing gift/free lines in the cart */
        $flagged_by_rule = [];
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( ! empty( $item['pnp_flag'] ) && ! empty( $item['pnp_rule'] ) ) {
                $flagged_by_rule[ intval( $item['pnp_rule'] ) ][] = $key;
            }
        }

        $done   = (array) WC()->session->get( 'pnp_done_rules', [] );
        $rules  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE active = 1
             ORDER BY priority DESC, id ASC",
            ARRAY_A
        );
        if ( ! $rules ) return;

        $gifts_by_rule = [];
        $now = current_time( 'mysql', 0 );

        foreach ( $rules as $r ) {
            $rid = intval( $r['id'] );

            /* ----- time window check ----- */
            if ( ! intval( $r['no_time_limit'] ) ) {
                if ( ( $r['schedule_start'] && $r['schedule_start'] > $now ) ||
                     ( $r['schedule_end']   && $r['schedule_end']   < $now ) ) {
                    continue;
                }
            }

            $allow_sale = ! intval( $r['exclude_sale'] );

            if ( intval( $r['enable_buy_y'] ) ) {
                // set-based račun: prvo rezerviši X, pa ostatak za Y (nema duplog brojanja)
                $x_keys = self::item_keys_in_scope( $cart, $r['buy_x_scope'], $r['buy_x_term'], $r['buy_x_ids'], $allow_sale );
                $y_keys = self::item_keys_in_scope( $cart, $r['buy_y_scope'], $r['buy_y_term'], $r['buy_y_ids'], $allow_sale );

                // ukloni preklapajuće stavke iz Y
                $y_keys = array_values( array_diff( $y_keys, $x_keys ) );

                $qty_x = count( $x_keys );
                $qty_y = count( $y_keys );

                $x_ok = ( $qty_x >= (int) $r['buy_x_qty'] );
                $y_ok = ( $qty_y >= (int) $r['buy_y_qty'] );
            } else {
                $x_ok = self::count_items_in_scope(
                            $cart,
                            $r['buy_x_scope'], $r['buy_x_term'], $r['buy_x_ids'],
                            $allow_sale
                        ) >= (int) $r['buy_x_qty'];
                $y_ok = true;
            }

            // ostaje dalje postojeća logika:
            $cart_ok = true;

            if ( intval( $r['enable_cart'] ) ) {
                $cart_ok = self::sum_items_value_in_scope(
                               $cart,
                               $r['cart_scope'], $r['cart_term'], $r['cart_ids'],
                               ! intval( $r['exclude_sale'] )
                           ) >= floatval( $r['cart_val'] );
            }

            $fulfilled = intval( $r['enable_cart'] ) ? $cart_ok : ( $x_ok && $y_ok );

            /* -------------- IF RULE FULFILLED -------------- */
            if ( $fulfilled ) {

                /* reward already in cart? keep it & mark done */
                if ( ! empty( $flagged_by_rule[ $rid ] ) ) {
                    self::mark_rule_done( $done, $rid );
                    continue;
                }

                /* build candidate list */
                $candidate_ids = ( $r['reward_type']==='gift' && $r['reward_ids']==='' )
                    ? get_posts( [
                        'post_type'   => 'product',
                        'numberposts' => -1,
                        'fields'      => 'ids',
                        'tax_query'   => [[
                            'taxonomy' => 'product_cat',
                            'field'    => 'term_id',
                            'terms'    => PNP_GIFT_CAT,
                        ]],
                      ] )
                    : array_filter( array_map( 'absint',
                                 explode( ',', $r['reward_ids'] ) ) );

                $max_price = ( $r['reward_type']==='free' )
                    ? ( (float) $r['reward_max_price'] ?: PHP_INT_MAX )
                    : PHP_INT_MAX;

                $available = array_filter( $candidate_ids, function ( $pid ) use ( $max_price ) {
                    $p = wc_get_product( $pid );
                    return $p && ( $p->is_in_stock() || $p->backorders_allowed() )
                           && (float) $p->get_price() <= $max_price;
                } );

                /* exactly one valid → auto-add */
                if ( count( $available ) === 1 ) {
                    $pid = reset( $available );
                    $qty = max( 1, intval( $r['reward_qty'] ) );

                    self::mark_rule_done( $done, $rid );
                    for ( $i = 0; $i < $qty; $i++ ) {
                        $cart->add_to_cart(
                            $pid,
                            1,
                            0,
                            [],
                            [
                                'pnp_flag' => true,
                                'pnp_rule' => $rid,
                                'pnp_type' => $r['reward_type'],
                            ]
                        );
                    }
                }
                /* several candidates → queue a picker */
                elseif ( $available ) {
                    $gifts_by_rule[ $rid ] = [
                        'ids'       => array_values( $available ),
                        'qty'       => max( 1, intval( $r['reward_qty'] ) ),
                        'type'      => $r['reward_type'],
                        'max_price' => $max_price,
                    ];
                }

                continue;  /* go to next rule */
            }

            /* -------------- IF RULE NOT FULFILLED -------------- */

            /* purge any stale gift lines from cart */
            if ( ! empty( $flagged_by_rule[ $rid ] ) ) {
                foreach ( $flagged_by_rule[ $rid ] as $ckey ) {
                    $cart->remove_cart_item( $ckey );
                }
            }
            /* also make sure rule is not marked done */
            $done = array_diff( $done, [ $rid ] );
        }

        /* save session + adjust prices on flagged lines */
        WC()->session->set( 'pnp_done_rules',    array_values( $done ) );
        WC()->session->set( 'pnp_gifts_by_rule', $gifts_by_rule );

        foreach ( $cart->get_cart() as &$item ) {
            if ( empty( $item['pnp_flag'] ) ) continue;
            $item['data']->set_price(
                ( $item['pnp_type']==='free' ) ? 0.01 : 0
            );
        }
    }

    /* ====================================================================== */
    /*  5) AJAX – user picks a gift from the picker                           */
    /* ====================================================================== */
    public static function ajax_pick_gift() {
    try {
        // 1) Nonce check
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', PNP_NONCE ) ) {
            return wp_send_json_error( __( 'Nevažeći zahtev.', 'pokloni-popusti' ) );
        }

        // 2) Ensure cart is loaded
        if ( ! WC()->cart ) {
            wc_load_cart();
        }

        // 3) Validate parameters
        $pid = absint( $_POST['pid'] ?? 0 );
        $rid = absint( $_POST['rule_id'] ?? 0 );
        if ( ! $pid || ! $rid ) {
            return wp_send_json_error( __( 'Nedostaju parametri.', 'pokloni-popusti' ) );
        }

        // 4) Make sure this gift was queued by apply_rules()
        $gifts = (array) WC()->session->get( 'pnp_gifts_by_rule', [] );
        if ( empty( $gifts[ $rid ] ) || ! in_array( $pid, $gifts[ $rid ]['ids'], true ) ) {
            return wp_send_json_error( __( 'Nije dozvoljeno.', 'pokloni-popusti' ) );
        }

        // 5) Stock check
        $product = wc_get_product( $pid );
        if ( ! $product || ( ! $product->is_in_stock() && ! $product->backorders_allowed() ) ) {
            return wp_send_json_error( __( 'Proizvod nije na stanju.', 'pokloni-popusti' ) );
        }

        // 6) Add to cart
        $qty  = max( 1, intval( $gifts[ $rid ]['qty'] ) );
        $type = sanitize_text_field( $gifts[ $rid ]['type'] );
        for ( $i = 0; $i < $qty; $i++ ) {
            WC()->cart->add_to_cart(
                $pid,
                1,
                0,
                [],
                [
                    'pnp_flag' => true,
                    'pnp_rule' => $rid,
                    'pnp_type' => $type,
                ]
            );
        }

        $done = (array) WC()->session->get( 'pnp_done_rules', [] );
        self::mark_rule_done( $done, $rid );

        // 8) All good
        return wp_send_json_success();
    } catch ( \Throwable $e ) {
        // Log server‑side exception for debugging:
        error_log( 'PNP ajax_pick_gift exception: ' . $e->getMessage() );
        // Return a JSON error payload, but HTTP 200 so the browser won’t log a 500
        return wp_send_json_error( __( 'Greška na serveru.', 'pokloni-popusti' ) );
    }
}

    /* ====================================================================== */
    /*  6) ITEM REMOVED – purge stale gifts, recalc totals                    */
    /* ====================================================================== */
    public static function cart_item_removed( $key, $cart ) {

        /* unlock rule if shopper removed a gift line */
        $removed = $cart->removed_cart_contents[ $key ] ?? [];
        if ( ! empty( $removed['pnp_rule'] ) ) {
            $done = array_diff(
                (array) WC()->session->get( 'pnp_done_rules', [] ),
                [ intval( $removed['pnp_rule'] ) ]
            );
            WC()->session->set( 'pnp_done_rules', $done );
        }

        /* run engine again */
        self::reset_prices( $cart );
        self::apply_rules ( $cart );

        /* refresh totals so mini-cart is correct */
        $cart->calculate_totals();
    }

    /* ====================================================================== */
    /*  7) Misc. session helpers & filters                                    */
    /* ====================================================================== */
    public static function reset_session() {
        WC()->session->__unset( 'pnp_gifts_by_rule' );
        WC()->session->__unset( 'pnp_done_rules' );
    }

    public static function filter_purchasable( $bool, $product ) {
        return has_term( PNP_GIFT_CAT, 'product_cat', $product->get_id() )
            ? true : $bool;
    }

    public static function replace_price_html( $html, $product ) {
        return has_term( PNP_GIFT_CAT, 'product_cat', $product->get_id() )
            ? '<span class="pnp-poklon">' . esc_html__( 'Poklon', 'pokloni-popusti' ) . '</span>'
            : $html;
    }

    public static function show_poklon_message() {
        global $product;
        if ( has_term( PNP_GIFT_CAT, 'product_cat', $product->get_id() ) ) {
            echo '<p class="pnp-poklon-message">'
               . esc_html__( 'Ovaj proizvod je dostupan samo kao poklon.', 'pokloni-popusti' )
               . '</p>';
        }
    }
}
