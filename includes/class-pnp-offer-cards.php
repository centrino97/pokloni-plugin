<?php
/**
 * PNP – Offer Cards (tekst‑baneri) v1.17 – 18 Jul 2025
 * Shortcode: [pnp_offer_cards]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'PNP_Offer_Cards_Text' ) ) :

class PNP_Offer_Cards_Text {

    const STYLE = 'pnp-offer-cards-inline';
    const JS    = 'pnp-offer-cards-inline-js';

    public function __construct() {
        add_shortcode( 'pnp_offer_cards',   [ $this, 'render' ] );
        add_action(   'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /* ----------  ASSETS  ---------- */
    public function enqueue_assets() {
        wp_register_style ( self::STYLE, false, [], null );
        wp_register_script( self::JS,    false, [], null, true );
        wp_enqueue_style ( self::STYLE );
        wp_enqueue_script( self::JS );

        // INLINE CSS
        $css = <<<CSS
/* wrap */
.pnp-rail-wrap { margin: 30px auto; text-align: center; }
/* inner */
.pnp-rail-inner { display: flex; align-items: center; justify-content: center; margin: 0 auto; }
/* viewport */
.pnp-rail-viewport { overflow: hidden; }
/* rail */
.pnp-offer-rail { display: flex; gap: 20px; overflow-x: auto; scroll-snap-type: x mandatory; padding: 10px 0; }
.pnp-offer-rail::-webkit-scrollbar { display: none; }
/* card */
.pnp-card {
  flex: 0 0 260px;
  background: #fff;
  border: 1px solid #e1e1e1;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  text-align: center;
  scroll-snap-align: start;
  transition: box-shadow .2s;
  padding: 24px 18px;
  justify-content: space-between;
}
.pnp-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.pnp-title { font-size:16px; line-height:1.35; margin:0 0 18px; }
.pnp-btn {
  background:#007cba; color:#fff; border:none; border-radius:4px;
  padding:10px 16px; font-size:14px; text-decoration:none;
}
.pnp-btn:hover { background:#005a8c; }

/* clickable product image */
.pnp-card > a.pnp-img-link {
  display: block;
  margin-bottom: 12px;
}
.pnp-card > a.pnp-img-link > img {
  display: block;
  height: 150px !important;   /* half of original */
  width: auto !important;
  margin: 0 auto;
  object-fit: contain;
  border-radius: 4px;
  cursor: pointer;
  transition: transform .2s;
}
.pnp-card > a.pnp-img-link:hover > img {
  transform: scale(1.05);
}

/* arrows */
.pnp-rail-arrow {
  width: 32px; height: 32px;
  border-radius:50%; background:#00324e; color:#fff;
  border:none; display:flex; align-items:center; justify-content:center;
  cursor:pointer; opacity:.9; font-size:20px; line-height:1;
}
.pnp-rail-arrow:hover { background:#002439; opacity:1; }
.pnp-rail-arrow[hidden] { display:none; }

/* dots */
.pnp-dots { display:flex; gap:8px; justify-content:center; margin-top:14px; }
.pnp-dot {
  width:8px; height:8px; border-radius:50%; background:#c5c5c5;
  cursor:pointer;
}
.pnp-dot.active { background:#007cba; }

/* MOBILE */
@media(max-width:767px) {
  .pnp-rail-viewport { width:82vw; }
  .pnp-rail-arrow.left  { margin-right:8px; }
  .pnp-rail-arrow.right { margin-left:8px; }
  .pnp-rail-arrow { width:28px; height:28px; }
  .pnp-card { flex:0 0 82vw; }
}

/* DESKTOP */
@media(min-width:768px) {
  .pnp-rail-viewport { width: calc(260px * 3 + 20px * 2); }
  .pnp-rail-arrow.left  { margin-right:12px; }
  .pnp-rail-arrow.right { margin-left:12px; }
}
CSS;
        wp_add_inline_style( self::STYLE, $css );

        // INLINE JS (unchanged)
        $js = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  const mq = matchMedia('(max-width:767px)');
  document.querySelectorAll('.pnp-rail-wrap').forEach(wrap => {
    const inner    = wrap.querySelector('.pnp-rail-inner');
    const viewport = inner.querySelector('.pnp-rail-viewport');
    const rail     = viewport.querySelector('.pnp-offer-rail');
    const cards    = rail.querySelectorAll('.pnp-card');
    const prev     = inner.querySelector('.pnp-rail-arrow.left');
    const next     = inner.querySelector('.pnp-rail-arrow.right');
    const dotsCt   = wrap.querySelector('.pnp-dots');

    function applyPadding() {
      if (mq.matches && cards.length) {
        const cw  = cards[0].offsetWidth;
        const pad = (viewport.clientWidth - cw) / 2;
        rail.style.paddingLeft  = pad + 'px';
        rail.style.paddingRight = pad + 'px';
      } else {
        rail.style.paddingLeft  = '';
        rail.style.paddingRight = '';
      }
    }

    function scrollToCard(i) {
      const cw     = cards[0].offsetWidth;
      const gap    = 20;
      const step   = cw + gap;
      const offset = step * i;
      if (mq.matches) {
        const centerOff = (viewport.clientWidth - cw) / 2;
        rail.scrollTo({ left: offset - centerOff, behavior: 'smooth' });
      } else {
        rail.scrollTo({ left: offset, behavior: 'smooth' });
      }
    }

    cards.forEach((_, i) => {
      const dot = document.createElement('span');
      dot.className = 'pnp-dot' + (i === 0 ? ' active' : '');
      dot.addEventListener('click', () => scrollToCard(i));
      dotsCt.appendChild(dot);
    });

    function syncDot() {
      const cw  = cards[0].offsetWidth;
      const idx = Math.round(rail.scrollLeft / (cw + 20));
      dotsCt.querySelectorAll('.pnp-dot').forEach((d, i) => {
        d.classList.toggle('active', i === idx);
      });
    }

    prev.addEventListener('click', e => {
      e.preventDefault();
      const cw  = cards[0].offsetWidth;
      const idx = Math.round(rail.scrollLeft / (cw + 20)) - 1;
      scrollToCard(idx < 0 ? cards.length - 1 : idx);
    });
    next.addEventListener('click', e => {
      e.preventDefault();
      const cw  = cards[0].offsetWidth;
      const idx = Math.round(rail.scrollLeft / (cw + 20)) + 1;
      scrollToCard(idx >= cards.length ? 0 : idx);
    });

    mq.addEventListener('change', () => {
      applyPadding();
      scrollToCard(0);
    });
    rail.addEventListener('scroll', syncDot);

    applyPadding();
    scrollToCard(0);
  });
});
JS;
        wp_add_inline_script( self::JS, $js );
    }

    /* ----------  SHORTCODE ---------- */
    public function render() {
        if ( ! defined( 'PNP_TABLE' ) ) return '';
        global $wpdb;
        $rules = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . PNP_TABLE . " WHERE active=1 ORDER BY priority DESC,id ASC",
            ARRAY_A
        );
        if ( empty( $rules ) ) return '';

        ob_start(); ?>
        <div class="pnp-rail-wrap">
          <div class="pnp-rail-inner">
            <button class="pnp-rail-arrow left"  aria-label="Prethodno">‹</button>
            <div class="pnp-rail-viewport">
              <div class="pnp-offer-rail">
                <?php foreach ( $rules as $r ) :
                  // determine rule scope & IDs
                  if ( (int)$r['enable_cart'] ) {
                    $scope   = $r['cart_scope'];
                    $term_id = (int)$r['cart_term'];
                    $ids_csv = $r['cart_ids'];
                  } else {
                    $scope   = $r['buy_x_scope'];
                    $term_id = (int)$r['buy_x_term'];
                    $ids_csv = $r['buy_x_ids'];
                  }

                  // fetch one random qualifying product image
                  $image_html = '';
                  if ( $scope === 'product' && trim($ids_csv) ) {
                    $ids = array_filter( array_map('absint', explode(',', $ids_csv)) );
                    if ( $ids ) {
                      $rand = $ids[array_rand($ids)];
                      if ( $rp = wc_get_product($rand) ) {
                        $image_html = $rp->get_image();
                      }
                    }
                  } elseif ( $scope === 'product_cat' && $term_id ) {
                    $posts = get_posts([
                      'posts_per_page' => 1,
                      'post_type'      => 'product',
                      'orderby'        => 'rand',
                      'tax_query'      => [[
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $term_id
                      ]]
                    ]);
                    if ( ! empty($posts) && ($rp = wc_get_product($posts[0]->ID)) ) {
                      $image_html = $rp->get_image();
                    }
                  }

                  // build link
                  $link = wc_get_page_permalink('shop');
                  if ( in_array($scope, ['product_cat','brend','linija'], true) && $term_id ) {
                    $tmp = get_term_link($term_id, $scope);
                    if ( ! is_wp_error($tmp) ) $link = $tmp;
                  } elseif ( $scope === 'product' && ! empty($ids) ) {
                    $link = get_permalink( reset($ids) );
                  }
                ?>
                  <div class="pnp-card">
                    <?php if ( $image_html ): ?>
                      <a href="<?php echo esc_url( $link ); ?>" class="pnp-img-link">
                        <?php echo $image_html; ?>
                      </a>
                    <?php endif; ?>
                    <h4 class="pnp-title">
                      <?php esc_html_e( 'Kupi i osvoji poklon🎁', 'pokloni-popusti' ); ?>
                    </h4>
                    <a class="pnp-btn" href="<?php echo esc_url( $link ); ?>">
                      <?php esc_html_e( 'Pogledaj ponudu', 'pokloni-popusti' ); ?>
                    </a>
                  </div>
                <?php endforeach; ?>
              </div><!-- /.pnp-offer-rail -->
            </div><!-- /.pnp-rail-viewport -->
            <button class="pnp-rail-arrow right" aria-label="Sledeće">›</button>
          </div><!-- /.pnp-rail-inner -->
          <div class="pnp-dots"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new PNP_Offer_Cards_Text;

endif;
