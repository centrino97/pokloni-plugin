<?php
/**
 * File: class-pnp-shortcode.php
 *
 * Shortcodes + front-end layer for "Pokloni & Popusti".
 * ----------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PNP_Shortcode {

    public function __construct() {
    add_shortcode( 'pnp_uslovi',      [ $this, 'render' ] );
    add_shortcode( 'pnp_gift_picker', [ $this, 'gift_picker' ] );
    add_shortcode( 'pnp_gift_info',   [ $this, 'gift_info' ] );
    add_shortcode( 'pnp_offer_cards', [ $this, 'offer_cards' ] );

    // AJAX samo za refresh offers (pick_gift je u glavnom fajlu)
    add_action( 'wp_ajax_pnp_refresh_offers',        [ $this, 'ajax_refresh_offers' ] );
    add_action( 'wp_ajax_nopriv_pnp_refresh_offers', [ $this, 'ajax_refresh_offers' ] );

    add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'refresh_picker' ] );
    add_action( 'wp_enqueue_scripts',                [ $this, 'enqueue_frontend_scripts' ] );
}

    public function enqueue_frontend_scripts() {
        wp_register_script( 'pnp-frontend', false, [ 'jquery' ], PNP_VERSION, true );
        wp_enqueue_script( 'pnp-frontend' );

        $nonce    = wp_create_nonce( PNP_NONCE );
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );

        $js = <<<JS
(function($){
    function refreshPNPBlock(){
        const pid = $('#pnp-offer-container').data('product_id') || 0;
        $.post('{$ajax_url}', { action:'pnp_refresh_offers', product_id:pid })
         .done(html => {
             const wrap   = $('<div>').html(html),
                   offers = wrap.find('#pnp-offer-container'),
                   picker = wrap.find('#pnp-gift-picker-wrap');
             if (offers.length) $('#pnp-offer-container').replaceWith(offers);
             if (picker.length) $('#pnp-gift-picker-wrap').replaceWith(picker);
         })
         .fail((xhr,status,error) => {
             console.warn('PNP refresh-offers error:', status, error);
         });
    }

    $(document).on('click', '.pnp-add-to-cart', function(e){
        e.preventDefault();
        const pid = $(this).data('product_id');
        if (!pid) return;

        if (window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url) {
            const url = wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%','add_to_cart');
            $.post(url, { product_id:pid, quantity:1 })
             .done(resp => {
                 $(document.body).trigger('added_to_cart', [resp.fragments, resp.cart_hash, $(this)]);
                 setTimeout(refreshPNPBlock, 300);
             })
             .fail((xhr,status,error) => {
                 console.warn('PNP add-to-cart error:', status, error);
             });
        } else {
            location.href = '?add-to-cart=' + pid;
        }
    });

    $(document.body).on(
      'added_to_cart removed_from_cart updated_cart_totals updated_wc_div checkout_cart_removed_item',
      () => setTimeout(refreshPNPBlock, 300)
    );

    $(document).on('click', '.pnp-confirm', function(e){
        e.preventDefault();
        const rid = $(this).data('rid'),
              pid = $('input[name="pnp_pid_'+rid+'"]:checked').val();

        if (!pid) {
            alert('Morate izabrati proizvod.');
            return;
        }

        $.post('{$ajax_url}', {
            action:    'pnp_pick_gift',
            nonce:     '{$nonce}',
            pid:       pid,
            rule_id:   rid
        })
        .done(res => {
            if (!res.success) {
                alert(res.data || 'Greška');
                return;
            }
            $(document.body)
              .trigger('wc_fragment_refresh')
              .one('wc_fragments_refreshed', () => $(document.body).trigger('update_checkout'));
            if (document.body.classList.contains('woocommerce-checkout')) {
                location.reload();
            }
        })
        .always(() => {
            $(document.body).trigger('wc_fragment_refresh');
        });
    });
})(jQuery);
JS;

        wp_add_inline_script( 'pnp-frontend', $js );

        wp_enqueue_style(
            'pnp-carousel-style',
            plugin_dir_url( __FILE__ ) . '../assets/css/pnp-carousel.css',
            [],
            PNP_VERSION
        );
    }



    public function ajax_refresh_offers() {
        try {
            if ( ! WC()->cart ) {
                wc_load_cart();
            }
            WC()->cart->calculate_totals();

            echo do_shortcode( '[pnp_uslovi]' );
            echo '<div id="pnp-gift-picker-wrap">'
                 . do_shortcode( '[pnp_gift_picker]' )
                 . '</div>';
        } catch ( \Throwable $e ) {
            error_log( 'PNP AJAX refresh error: ' . $e->getMessage() );
            echo '<div id="pnp-offer-container"></div>';
            echo '<div id="pnp-gift-picker-wrap"></div>';
        }
        wp_die();
    }


    public function render( $atts = [], $content = '' ) {
        if ( ! WC()->cart ) {
            return '';
        }
        global $wpdb, $product;
        if ( ! empty( $_POST['product_id'] ) ) {
            $product = wc_get_product( absint( $_POST['product_id'] ) );
        }
        $pid   = is_object( $product ) ? $product->get_id() : 0;
        $cart  = WC()->cart;
        $done  = (array) WC()->session->get( 'pnp_done_rules', [] );
        $table = $wpdb->prefix . PNP_TABLE;
        $rules = $wpdb->get_results( "SELECT * FROM {$table} WHERE active=1 ORDER BY priority DESC,id ASC", ARRAY_A );
        if ( ! $rules ) {
            return '';
        }

        $out = '<div id="pnp-offer-container" data-product_id="'.esc_attr($pid).'">';
        $now = current_time( 'mysql', 0 );

        foreach ( $rules as $r ) {
            $rid = (int) $r['id'];
            if ( in_array( $rid, $done, true ) ) {
                continue;
            }
            if ( ! (int) $r['no_time_limit'] ) {
                if ( ($r['schedule_start'] && $r['schedule_start'] > $now)
                  || ($r['schedule_end']   && $r['schedule_end']   < $now) ) {
                    continue;
                }
            }

            $applies = ( (int)$r['enable_cart']
              ? self::product_contributes_to_cart_rule( $pid, $r )
              : ($pid && PNP_Rules::product_in_scope($pid,$r['buy_x_scope'],$r['buy_x_term'],$r['buy_x_ids']))
            );

            if ( ! $applies ) {
                continue;
            }

            $qty_x    = PNP_Rules::count_items_in_scope( $cart, $r['buy_x_scope'], $r['buy_x_term'], $r['buy_x_ids'],   ! (int)$r['exclude_sale'] );
            $qty_y    = (int)$r['enable_buy_y']
                        ? PNP_Rules::count_items_in_scope( $cart, $r['buy_y_scope'], $r['buy_y_term'], $r['buy_y_ids'], ! (int)$r['exclude_sale'] )
                        : 0;
            $cart_sum = (int)$r['enable_cart']
                        ? PNP_Rules::sum_items_value_in_scope( $cart, $r['cart_scope'], $r['cart_term'], $r['cart_ids'], ! (int)$r['exclude_sale'] )
                        : 0;

            $fulfilled = (int)$r['enable_cart']
                       ? ($cart_sum >= (float)$r['cart_val'])
                       : ($qty_x >= (int)$r['buy_x_qty'] && (!$r['enable_buy_y'] || $qty_y >= (int)$r['buy_y_qty']));

            if ( $fulfilled ) {
                $out .= '<div class="pnp-fulfilled-heading">'
                      . esc_html( PNP_Text_Manager::get(
                            $rid, 
                            'offer_heading_fulfilled',
                            __('Uslov ispunjen! Izaberite nagradu', 'pokloni-popusti'),
                            []
                        ))
                      . '</div>';
                $out .= '<a href="'.esc_url(wc_get_checkout_url()).'" class="pnp-finish-button">'
                      . esc_html( PNP_Text_Manager::get(
                            $rid,
                            'offer_cta_checkout',
                            __('Završi kupovinu', 'pokloni-popusti'),
                            []
                        ))
                      . '</a>';
                continue;
            }

            $out .= $this->build_offer_block( $r, $qty_x, $qty_y, $cart_sum );
        }

        $out .= '</div>';
        return $out;
    }

private function build_offer_block( array $r, int $qty_x_in_cart, int $qty_y_in_cart, float $cart_sum ) {
    global $wpdb;
    $cart = WC()->cart;

    if ( $r['buy_x_scope'] === 'product' ) {
        $buy_ids       = array_filter( array_map( 'absint', explode( ',', $r['buy_x_ids'] ) ) );
        $reward_ids    = array_filter( array_map( 'absint', explode( ',', $r['reward_ids'] ) ) );

        $available_rewards = array_filter( $reward_ids, function( $gift_id ) {
            $g = wc_get_product( $gift_id );
            return $g && ( $g->is_in_stock() || $g->backorders_allowed() );
        } );

        if ( count( $buy_ids ) === 1 && count( $available_rewards ) === 1 ) {
            $gift_id = reset( $available_rewards );
            $p       = wc_get_product( $gift_id );
            if ( $p ) {
                ob_start(); ?>
                <div class="pnp-single-offer">
                  <p><?php echo PNP_Text_Manager::get(
                      $r['id'], 
                      'single_offer_text',
                      __('🎁 Kupovinom proizvoda dobijate poklon', 'pokloni-popusti'),
                      ['product_name' => $p->get_name()]
                  ); ?></p>
                  <div class="pnp-single-item" style="text-align:left;">
                      <a href="<?php echo esc_url(get_permalink($gift_id)); ?>">
                          <?php echo wp_get_attachment_image( $p->get_image_id(), 'medium' ); ?>
                          <span><?php echo esc_html( $p->get_name() ); ?></span>
                      </a>
                  </div>
                </div>
                <?php
                return ob_get_clean();
            }
        }

        if ( count( $available_rewards ) > 1 ) {
            ob_start(); ?>
            <div class="pnp-multi-offer">
              <p><?php echo PNP_Text_Manager::get(
                  $r['id'],
                  'multi_offer_text',
                  __('🎁 Kupovinom proizvoda birate poklon na kraju kupovine', 'pokloni-popusti'),
                  []
              ); ?></p>
              <div class="pnp-multi-list" style="text-align:left;">
                <?php foreach ( $available_rewards as $gift_id ) :
                    $gift = wc_get_product( $gift_id );
                    if ( ! $gift || ( ! $gift->is_in_stock() && ! $gift->backorders_allowed() ) ) {
                        continue;
                    }
                    $img  = wp_get_attachment_image( $gift->get_image_id(), 'woocommerce_thumbnail' );
                ?>
                  <div class="pnp-multi-item">
                      <a href="<?php echo esc_url(get_permalink($gift_id)); ?>">
                          <?php echo $img; ?>
                          <div class="pnp-multi-title"><?php echo esc_html( $gift->get_name() ); ?></div>
                      </a>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    if ( (int) $r['enable_cart'] ) {
        $threshold           = floatval( $r['cart_val'] );
        $formatted_threshold = number_format_i18n( $threshold, 2 ) . ' ' . get_woocommerce_currency_symbol();
        $term_name           = '';

        if ( ! empty( $r['cart_term'] ) ) {
            $t = get_term( (int) $r['cart_term'], $r['cart_scope'] );
            if ( $t && ! is_wp_error( $t ) ) {
                $term_name = $t->name;
            }
        }

        if ( ! $term_name ) {
            $term_name = esc_html__( 'proizvoda', 'pokloni-popusti' );
        }

        if ( $cart_sum < $threshold ) {

            // Osnovni tekst: "dodaj najmanje {price} {scope_name}..."
            $offer_text = PNP_Text_Manager::get(
                $r['id'],
                'offer_text_cart',
                __( 'Dodaj u korpu najmanje {price} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti' ),
                [
                    'price'      => $formatted_threshold,
                    'scope_name' => $term_name,
                ]
            );

            if ( $cart_sum > 0 ) {
                $needed           = max( 0, $threshold - $cart_sum );
                $formatted_needed = number_format_i18n( $needed, 2 ) . ' ' . get_woocommerce_currency_symbol();

                // Progres varijanta: "dodaj još {remaining}..."
                $offer_text = PNP_Text_Manager::get(
                    $r['id'],
                    'offer_text_progress',
                    __( 'Dodaj u korpu još {remaining} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti' ),
                    [
                        'remaining'  => $formatted_needed,
                        'scope_name' => $term_name,
                    ]
                );
            }
        } else {
            $offer_text = esc_html__( 'Uslov za gratis proizvod je ispunjen!', 'pokloni-popusti' );
        }


    } else {
        $qty_x       = intval( $r['buy_x_qty'] );
        $term_x_name = '';
        if ( ! empty( $r['buy_x_term'] ) ) {
            $t = get_term( (int)$r['buy_x_term'], $r['buy_x_scope'] );
            if ( $t && ! is_wp_error( $t ) ) {
                $term_x_name = $t->name;
            }
        }
        if ( ! $term_x_name ) {
            $term_x_name = esc_html__( 'proizvodi', 'pokloni-popusti' );
        }

        if ( (int)$r['enable_buy_y'] ) {
            $qty_y       = intval( $r['buy_y_qty'] );
            $term_y_name = '';
            if ( ! empty( $r['buy_y_term'] ) ) {
                $t = get_term( (int)$r['buy_y_term'], $r['buy_y_scope'] );
                if ( $t && ! is_wp_error( $t ) ) {
                    $term_y_name = $t->name;
                }
            }
            if ( ! $term_y_name ) {
                $term_y_name = esc_html__( 'proizvodi', 'pokloni-popusti' );
            }

            if ( $qty_x_in_cart === 0 && $qty_y_in_cart === 0 ) {
                $offer_text = PNP_Text_Manager::get(
                    $r['id'],
                    'offer_text_buy_xy',
                    __( 'Kupi {qty_x} {scope_x} i {qty_y} {scope_y} i biraj poklon proizvod.', 'pokloni-popusti' ),
                    [
                        'qty_x' => $qty_x,
                        'scope_x' => $term_x_name,
                        'qty_y' => $qty_y,
                        'scope_y' => $term_y_name
                    ]
                );
            } elseif ( $qty_x_in_cart < $qty_x ) {
                $remain     = $qty_x - $qty_x_in_cart;
                $offer_text = PNP_Text_Manager::get(
                    $r['id'],
                    'offer_text_progress',
                    __( 'Dodaj u korpu još {remaining} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti' ),
                    [
                        'remaining' => $remain,
                        'scope_name' => $term_x_name
                    ]
                );
            } else {
                $remain     = $qty_y - $qty_y_in_cart;
                $offer_text = PNP_Text_Manager::get(
                    $r['id'],
                    'offer_text_progress',
                    __( 'Dodaj u korpu još {remaining} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti' ),
                    [
                        'remaining' => $remain,
                        'scope_name' => $term_y_name
                    ]
                );
            }

        } else {
            if ( $qty_x_in_cart === 0 ) {
                $offer_text = PNP_Text_Manager::get(
                    $r['id'],
                    'offer_text_buy_x',
                    __( 'Kupi {qty} {scope_name} proizvoda i biraj poklon proizvod.', 'pokloni-popusti' ),
                    [
                        'qty' => $qty_x,
                        'scope_name' => $term_x_name
                    ]
                );
            } else {
                $remain     = $qty_x - $qty_x_in_cart;
                $offer_text = PNP_Text_Manager::get(
                    $r['id'],
                    'offer_text_progress',
                    __( 'Dodaj u korpu još {remaining} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti' ),
                    [
                        'remaining' => $remain,
                        'scope_name' => $term_x_name
                    ]
                );
            }
        }
    }

    $offer_html = sprintf( '<p>🎁 %s</p>', wp_kses_post( $offer_text ) );

    $carousel_x = [];
    if ( (int)$r['enable_cart'] ) {
        if ( in_array( $r['cart_scope'], ['product_cat','brend','linija'], true ) && $r['cart_term'] ) {
            $carousel_x = get_posts([
                'post_type'   => 'product',
                'numberposts' => -1,
                'fields'      => 'ids',
                'tax_query'   => [[
                    'taxonomy' => $r['cart_scope'],
                    'field'    => 'term_id',
                    'terms'    => (int)$r['cart_term'],
                ]],
            ]);
        } else {
            $carousel_x = array_filter( array_map( 'absint', explode( ',', $r['cart_ids'] ) ) );
            if ( ! $carousel_x && $r['cart_term'] ) {
                $carousel_x = [ (int)$r['cart_term'] ];
            }
        }
    } else {
        switch ( $r['buy_x_scope'] ) {
            case 'product':
                $carousel_x = array_filter( array_map( 'absint', explode( ',', $r['buy_x_ids'] ) ) );
                if ( ! $carousel_x && $r['buy_x_term'] ) {
                    $carousel_x = [ (int)$r['buy_x_term'] ];
                }
                break;
            case 'product_cat':
            case 'brend':
            case 'linija':
                $carousel_x = get_posts([
                    'post_type'   => 'product',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'tax_query'   => [[
                        'taxonomy' => $r['buy_x_scope'],
                        'field'    => 'term_id',
                        'terms'    => (int)$r['buy_x_term'],
                    ]],
                ]);
                break;
        }
    }

    $carousel_y = [];
    if ( (int)$r['enable_buy_y'] ) {
        switch ( $r['buy_y_scope'] ) {
            case 'product':
                $carousel_y = array_filter( array_map( 'absint', explode( ',', $r['buy_y_ids'] ) ) );
                if ( ! $carousel_y && $r['buy_y_term'] ) {
                    $carousel_y = [ (int)$r['buy_y_term'] ];
                }
                break;
            case 'product_cat':
            case 'brend':
            case 'linija':
                $carousel_y = get_posts([
                    'post_type'   => 'product',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'tax_query'   => [[
                        'taxonomy' => $r['buy_y_scope'],
                        'field'    => 'term_id',
                        'terms'    => (int)$r['buy_y_term'],
                    ]],
                ]);
                break;
        }
    }

    ob_start();
    $current_id = is_object( $GLOBALS['product'] ) ? $GLOBALS['product']->get_id() : 0;
    ?>
    <div class="pnp-offer-block">
      <?php echo $offer_html; ?>

      <?php if ( $carousel_x ) : ?>
        <div class="pnp-carousel pnp-carousel-x">
          <?php foreach ( $carousel_x as $pid ) :
              if ( $pid == $current_id ) continue;
              $p = wc_get_product( $pid );
              if ( ! $p || ! $p->is_visible() || ( ! $p->is_in_stock() && ! $p->backorders_allowed() ) ) {
                  continue;
              }
          ?>
            <div class="pnp-carousel-item">
              <div class="pnp-carousel-image">
                <?php echo wp_get_attachment_image( $p->get_image_id(), 'medium' ); ?>
              </div>
              <div class="pnp-carousel-title"><?php echo esc_html( $p->get_name() ); ?></div>
              <div class="pnp-carousel-price"><?php echo wc_price( $p->get_price() ); ?></div>
              <button class="button pnp-add-to-cart"
                      data-product_id="<?php echo esc_attr( $pid ); ?>">
                <?php esc_html_e( 'Dodaj u korpu', 'pokloni-popusti' ); ?>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ( (int)$r['enable_buy_y'] && $carousel_y ) : ?>
        <hr class="pnp-hr" />
        <?php
          $term_y_name = '';
          if ( $r['buy_y_term'] ) {
              $t = get_term( (int)$r['buy_y_term'], $r['buy_y_scope'] );
              if ( $t && ! is_wp_error( $t ) ) {
                  $term_y_name = $t->name;
              }
          }
          if ( ! $term_y_name ) {
              $term_y_name = esc_html__( 'proizvodi', 'pokloni-popusti' );
          }
        ?>
        <p class="pnp-plus-label"><strong>
          <?php printf(
            esc_html__( 'PLUS dodatno kupi %1$s %2$d proizvoda:', 'pokloni-popusti' ),
            esc_html( $term_y_name ),
            intval( $r['buy_y_qty'] )
          ); ?>
        </strong></p>

        <div class="pnp-carousel pnp-carousel-y">
          <?php foreach ( $carousel_y as $pid ) :
              if ( $pid == $current_id ) continue;
              $p = wc_get_product( $pid );
              if ( ! $p || ! $p->is_visible() || ( ! $p->is_in_stock() && ! $p->backorders_allowed() ) ) {
                  continue;
              }
          ?>
            <div class="pnp-carousel-item">
              <div class="pnp-carousel-image">
                <?php echo wp_get_attachment_image( $p->get_image_id(), 'medium' ); ?>
              </div>
              <div class="pnp-carousel-title"><?php echo esc_html( $p->get_name() ); ?></div>
              <button class="button pnp-add-to-cart"
                      data-product_id="<?php echo esc_attr( $pid ); ?>">
                <?php esc_html_e( 'Dodaj u korpu', 'pokloni-popusti' ); ?>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


public function gift_picker( $atts = [], $content = '' ) {
        if ( ! WC()->cart ) {
            return '';
        }
        WC()->cart->calculate_totals();
        $gifts = (array) WC()->session->get( 'pnp_gifts_by_rule', [] );
        $done  = (array) WC()->session->get( 'pnp_done_rules',    [] );
        if ( ! $gifts ) {
            return '';
        }
        ob_start(); ?>
        <div id="pnp-gift-picker-wrap">
          <div id="pnp-gift-picker" style="margin-bottom:40px;">
            <?php
            global $wpdb;
            $table = $wpdb->prefix . PNP_TABLE;

            foreach ( $gifts as $rid => $dat ) :
                if ( in_array( $rid, $done, true ) ) {
                    continue;
                }
                $rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $rid ), ARRAY_A );
                if ( ! $rule ) {
                    continue;
                }

                $qty = (int) $rule['reward_qty'];

                if ( $rule['buy_x_scope'] === 'product' ) {
                    $ids = array_filter( array_map( 'absint', explode( ',', $rule['buy_x_ids'] ) ) );
                    if ( count( $ids ) === 1 ) {
                        $p = wc_get_product( reset( $ids ) );
                        $scope_txt = $p ? $p->get_name() : '';
                    } else {
                        $names = [];
                        foreach ( $ids as $pid ) {
                            $p = wc_get_product( $pid );
                            if ( $p ) {
                                $names[] = $p->get_name();
                            }
                        }
                        $scope_txt = implode( ', ', $names );
                    }
                } else {
                    $term       = get_term( (int) $rule['buy_x_term'], $rule['buy_x_scope'] );
                    $scope_name = $term && ! is_wp_error( $term ) ? $term->name : '';

                    $all_posts = get_posts( [
                        'post_type'   => 'product',
                        'numberposts' => -1,
                        'fields'      => 'ids',
                        'tax_query'   => [[
                            'taxonomy' => $rule['buy_x_scope'],
                            'field'    => 'term_id',
                            'terms'    => (int) $rule['buy_x_term'],
                        ]],
                    ] );
                    $all_ids = array_map( 'absint', $all_posts );

                    $ids = array_filter( array_map( 'absint', explode( ',', $rule['buy_x_ids'] ) ) );

                    if ( $ids && count( $ids ) < count( $all_ids ) ) {
                        $scope_txt = sprintf(
                            _n( 'izabrani proizvod iz %s', 'izabrani proizvodi iz %s', count( $ids ), 'pokloni-popusti' ),
                            $scope_name
                        );
                    } else {
                        $scope_txt = sprintf( __( 'iz %s', 'pokloni-popusti' ), $scope_name );
                    }
                }
            ?>
                <h3 style="text-align:center;"><?php echo PNP_Text_Manager::get(
                    $rid,
                    'picker_heading',
                    __('Izaberi poklon', 'pokloni-popusti'),
                    ['qty' => $qty, 'scope' => $scope_txt]
                ); ?></h3>

              <div class="pnp-carousel pnp-carousel-gift-picker">
                <?php foreach ( $dat['ids'] as $pid ) :
                    $p        = wc_get_product( $pid );
                    $stock_ok = $p && ( $p->is_in_stock() || $p->backorders_allowed() );
                    $price_ok = $stock_ok && (float) $p->get_price() <= (float) $dat['max_price'];
                    if ( ! $price_ok ) {
                        continue;
                    }
                    $thumb = $p ? wp_get_attachment_image_url( $p->get_image_id(), 'medium' ) : '';
                ?>
                  <div class="pnp-carousel-item">
                    <label style="display:flex;flex-direction:column;align-items:center;cursor:pointer;">
                      <input
                        type="radio"
                        name="pnp_pid_<?php echo esc_attr( $rid ); ?>"
                        value="<?php echo esc_attr( $pid ); ?>"
                        style="margin-bottom:8px;"
                      >
                      <?php if ( $thumb ) : ?>
                        <img
                          src="<?php echo esc_url( $thumb ); ?>"
                          style="max-width:100px;max-height:100px;object-fit:contain;margin-bottom:8px;"
                        >
                      <?php endif; ?>
                      <span><?php echo esc_html( $p->get_name() ); ?></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>

              <div style="text-align:center;margin-top:15px;">
                <button class="button pnp-confirm" data-rid="<?php echo esc_attr( $rid ); ?>">
                  <?php echo PNP_Text_Manager::get(
                      $rid,
                      'picker_cta',
                      __('Potvrdi izbor', 'pokloni-popusti'),
                      []
                  ); ?>
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function refresh_picker( $fragments ) {
        $fragments['#pnp-gift-picker-wrap'] = $this->gift_picker();
        return $fragments;
    }

    public function render_gift_info( $atts = [] ) {
        if ( ! is_product() || ! function_exists( 'WC' ) ) {
            return '';
        }

        $product = wc_get_product();
        if ( ! $product ) {
            return '';
        }

        global $wpdb;
        $pid   = $product->get_id();
        $now   = current_time( 'mysql', 0 );
        $table = $wpdb->prefix . PNP_TABLE;

        $rules = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM {$table}
             WHERE active = 1
               AND reward_type = 'gift'
               AND FIND_IN_SET( %d, reward_ids ) > 0
            ORDER BY priority DESC, id ASC
        ", $pid ), ARRAY_A );

        if ( empty( $rules ) ) {
            return '';
        }

        $out = '<div class="pnp-gift-info">';

        foreach ( $rules as $r ) {
            if ( ! (int) $r['no_time_limit'] ) {
                if ( ( $r['schedule_start'] && $r['schedule_start'] > $now )
                  || ( $r['schedule_end']   && $r['schedule_end']   < $now ) ) {
                    continue;
                }
            }

            $qty_x   = intval( $r['buy_x_qty'] );
            $scope_x = strip_tags( PNP_Helpers::describe_scope(
                $r['buy_x_scope'], $r['buy_x_term'], $r['buy_x_ids']
            ) );

            if ( (int) $r['enable_buy_y'] ) {
                $qty_y   = intval( $r['buy_y_qty'] );
                $scope_y = strip_tags( PNP_Helpers::describe_scope(
                    $r['buy_y_scope'], $r['buy_y_term'], $r['buy_y_ids']
                ) );
                
                $out .= '<p>' . PNP_Text_Manager::get(
                    $r['id'],
                    'info_text_xy',
                    __('🎁 Poklon proizvod dobijate ako kupite {qty_x} {scope_x} i {qty_y} {scope_y}.', 'pokloni-popusti'),
                    [
                        'qty_x' => $qty_x,
                        'scope_x' => $scope_x,
                        'qty_y' => $qty_y,
                        'scope_y' => $scope_y
                    ]
                ) . '</p>';
            }
            elseif ( (int) $r['enable_cart'] ) {
                $val  = number_format_i18n( floatval( $r['cart_val'] ), 2 );
                $sym  = get_woocommerce_currency_symbol();
                
                $out .= '<p>' . PNP_Text_Manager::get(
                    $r['id'],
                    'info_text_cart',
                    __('🎁 Poklon proizvod dobijate ako vrednost korpe pređe {price}.', 'pokloni-popusti'),
                    ['price' => $val . $sym]
                ) . '</p>';
            }
            else {
                $out .= '<p>' . PNP_Text_Manager::get(
                    $r['id'],
                    'info_text_single',
                    __('🎁 Poklon proizvod dobijate ako kupite {qty} {scope}.', 'pokloni-popusti'),
                    ['qty' => $qty_x, 'scope' => $scope_x]
                ) . '</p>';
            }

            $cond_ids = [];
            if ( $r['enable_cart'] ) {
                $cond_ids = [];
            }
            else {
                if ( $r['buy_x_scope'] === 'product' ) {
                    $cond_ids = array_filter( array_map( 'absint', explode( ',', $r['buy_x_ids'] ) ) );
                } else {
                    $cond_ids = get_posts([
                        'post_type'   => 'product',
                        'numberposts' => -1,
                        'fields'      => 'ids',
                        'tax_query'   => [[
                            'taxonomy' => $r['buy_x_scope'],
                            'field'    => 'term_id',
                            'terms'    => (int)$r['buy_x_term'],
                        ]],
                    ]);
                }
            }

            if ( $cond_ids ) {
                $out .= '<div class="pnp-gift-items" style="display:flex;gap:1rem;flex-wrap:wrap;">';

                foreach ( $cond_ids as $cx_id ) {
                    $p = wc_get_product( $cx_id );
                    if ( ! $p || ( ! $p->is_in_stock() && ! $p->backorders_allowed() ) ) {
                        continue;
                    }

                    $img     = wp_get_attachment_image( $p->get_image_id(), 'woocommerce_thumbnail' );
                    $add_url = esc_url( $p->add_to_cart_url() );
                    $add_txt = esc_html( $p->add_to_cart_text() );

                    $out .= '<div class="pnp-gift-item" style="text-align:center;">'
                          .   '<a href="' . esc_url( get_permalink( $cx_id ) ) . '">' . $img . '</a><br>'
                          .   '<a href="' . $add_url . '" class="button">' . $add_txt . '</a>'
                          . '</div>';
                }

                $out .= '</div>';
            }
        }

        $out .= '</div>';
        return $out;
    }

    private static function product_contributes_to_cart_rule( $pid, $rule ) {
        if ( ! $pid ) return false;
        return PNP_Rules::product_in_scope(
            $pid,
            $rule['cart_scope'],
            $rule['cart_term'],
            $rule['cart_ids']
        );
    }
}