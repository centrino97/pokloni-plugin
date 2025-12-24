<?php if (!defined('ABSPATH')) exit; ?>

<?php
// Ako imamo ?edit= u URL-u (novo ili postojeće pravilo), otvaramo drugi tab po difoltu.
$is_edit_screen  = isset( $_GET['edit'] );
$list_tab_class  = $is_edit_screen ? '' : 'active';
$form_tab_class  = $is_edit_screen ? 'active' : '';
$scope_labels = [
    'product'     => 'Proizvod',
    'product_cat' => 'Kategorija',
    'brend'       => 'Brend',
    'linija'      => 'Linija',
];
$term_names = [];
foreach ( $all_terms as $tax => $terms ) {
    foreach ( $terms as $term ) {
        $term_names[ $tax ][ $term->term_id ] = $term->name;
    }
}
?>

<div class="wrap pnp-modern-wrap">
    <h1><?php esc_html_e('Onlinea Pokloni & Popusti', 'pokloni-popusti'); ?></h1>
    
    <!-- Tabs -->
    <div class="pnp-tabs">
        <div class="pnp-tab <?php echo esc_attr( $list_tab_class ); ?>" data-tab="pnp-rules-list">
            <?php esc_html_e('Pravila', 'pokloni-popusti'); ?>
        </div>
        <div class="pnp-tab <?php echo esc_attr( $form_tab_class ); ?>" data-tab="pnp-add-rule">
            <?php esc_html_e('Dodaj/Izmeni Pravilo', 'pokloni-popusti'); ?>
        </div>
    </div>

    <!-- ========== TAB 1: RULES LIST ========== -->
    <div id="pnp-rules-list" class="pnp-tab-content <?php echo esc_attr( $list_tab_class ); ?>">
        <a href="<?php echo esc_url(add_query_arg('edit', 0, admin_url('admin.php?page=pnp_settings'))); ?>" class="pnp-add-new-btn">
            <?php esc_html_e('+ Dodaj novo pravilo', 'pokloni-popusti'); ?>
        </a>

        <div class="pnp-rules-toolbar">
            <div class="pnp-rules-toolbar-left">
                <input type="search" class="pnp-rules-search" placeholder="<?php esc_attr_e('Pretraga pravila…', 'pokloni-popusti'); ?>">
                <select class="pnp-rules-filter" id="pnp-filter-status">
                    <option value="all"><?php esc_html_e('Sva stanja', 'pokloni-popusti'); ?></option>
                    <option value="active"><?php esc_html_e('Aktivna', 'pokloni-popusti'); ?></option>
                    <option value="inactive"><?php esc_html_e('Onemogućena', 'pokloni-popusti'); ?></option>
                </select>
                <select class="pnp-rules-filter" id="pnp-filter-reward">
                    <option value="all"><?php esc_html_e('Sve nagrade', 'pokloni-popusti'); ?></option>
                    <option value="gift"><?php esc_html_e('Poklon', 'pokloni-popusti'); ?></option>
                    <option value="free"><?php esc_html_e('Gratis', 'pokloni-popusti'); ?></option>
                </select>
                <select class="pnp-rules-filter" id="pnp-filter-condition">
                    <option value="all"><?php esc_html_e('Svi uslovi', 'pokloni-popusti'); ?></option>
                    <option value="buy_x"><?php esc_html_e('Kupi X', 'pokloni-popusti'); ?></option>
                    <option value="buy_xy"><?php esc_html_e('Kupi X + Y', 'pokloni-popusti'); ?></option>
                    <option value="cart"><?php esc_html_e('Vrednost korpe', 'pokloni-popusti'); ?></option>
                </select>
                <select class="pnp-rules-filter" id="pnp-sort-rules">
                    <option value="priority_desc"><?php esc_html_e('Prioritet ↓', 'pokloni-popusti'); ?></option>
                    <option value="priority_asc"><?php esc_html_e('Prioritet ↑', 'pokloni-popusti'); ?></option>
                    <option value="id_desc"><?php esc_html_e('ID ↓', 'pokloni-popusti'); ?></option>
                    <option value="id_asc"><?php esc_html_e('ID ↑', 'pokloni-popusti'); ?></option>
                </select>
                <button type="button" class="button pnp-rules-reset"><?php esc_html_e('Reset', 'pokloni-popusti'); ?></button>
            </div>
            <div class="pnp-rules-toolbar-right">
                <span class="pnp-rules-count" data-total="<?php echo esc_attr(count($rules)); ?>">
                    <?php echo esc_html(sprintf(__('Prikazano %1$d od %2$d', 'pokloni-popusti'), count($rules), count($rules))); ?>
                </span>
            </div>
        </div>
        
        <?php if (empty($rules)) : ?>
            <p><?php esc_html_e('Nema pravila. Dodajte svoje prvo pravilo.', 'pokloni-popusti'); ?></p>
        <?php else : ?>
            <div class="pnp-rule-cards">
                <?php foreach ($rules as $index => $r) : 
                    // Determine reward type badge
                    $reward_badge = '';
                    $reward_color = '';
                    if ($r['reward_type'] === 'gift') {
                        $reward_badge = '🎁 POKLON';
                        $reward_color = '#10b981'; // green
                        $reward_title = __( 'Poklon iz posebne kategorije poklona.', 'pokloni-popusti' );
                    } else {
                        $reward_badge = '🆓 GRATIS';
                        $reward_color = '#3b82f6'; // blue
                        $reward_title = __( 'Gratis proizvod (naplata 0.01 RSD).', 'pokloni-popusti' );
                    }
                    
                    // Determine condition type
                    $cond_text = '';
                    if ($r['enable_cart']) {
                        $cond_text = sprintf('💰 Korpa ≥ %s', wc_price($r['cart_val']));
                    } elseif ($r['enable_buy_y']) {
                        $cond_text = sprintf('🛒 Kupi %d X + %d Y', $r['buy_x_qty'], $r['buy_y_qty']);
                    } else {
                        $cond_text = sprintf('🛒 Kupi %d X', $r['buy_x_qty']);
                    }

                    $condition_type = $r['enable_cart'] ? 'cart' : ( $r['enable_buy_y'] ? 'buy_xy' : 'buy_x' );
                    $status_text = (int) $r['active'] === 1 ? __( 'Aktivno', 'pokloni-popusti' ) : __( 'Onemogućeno', 'pokloni-popusti' );
                    $status_class = (int) $r['active'] === 1 ? 'pnp-status-active' : 'pnp-status-inactive';

                    $scope = $r['enable_cart'] ? $r['cart_scope'] : $r['buy_x_scope'];
                    $term_id = $r['enable_cart'] ? $r['cart_term'] : $r['buy_x_term'];
                    $ids_csv = $r['enable_cart'] ? $r['cart_ids'] : $r['buy_x_ids'];
                    $ids = array_filter( array_map( 'absint', explode( ',', $ids_csv ) ) );
                    $scope_label = $scope_labels[ $scope ] ?? ucfirst( $scope );
                    $term_label = '';
                    if ( 'product' === $scope ) {
                        $term_label = sprintf( __( 'Odabrano: %d', 'pokloni-popusti' ), count( $ids ) );
                    } else {
                        $term_label = $term_id && isset( $term_names[ $scope ][ $term_id ] )
                            ? $term_names[ $scope ][ $term_id ]
                            : '—';
                    }
                    $search_text = strtolower( sprintf(
                        'pravilo %d %s %s %s %s %s',
                        $r['id'],
                        $reward_badge,
                        $cond_text,
                        $scope_label,
                        $term_label,
                        $status_text
                    ) );
                ?>
                    <div class="pnp-rule-card"
                         data-rule-id="<?php echo esc_attr($r['id']); ?>"
                         data-active="<?php echo esc_attr((int) $r['active']); ?>"
                         data-reward-type="<?php echo esc_attr($r['reward_type']); ?>"
                         data-condition-type="<?php echo esc_attr($condition_type); ?>"
                         data-priority="<?php echo esc_attr($r['priority']); ?>"
                         data-initial-index="<?php echo esc_attr($index); ?>"
                         data-search="<?php echo esc_attr($search_text); ?>">
                        <!-- Header with badge -->
                        <div class="pnp-rule-card-header">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="pnp-rule-badge" style="background:<?php echo esc_attr($reward_color); ?>;" title="<?php echo esc_attr($reward_title); ?>">
                                    <?php echo esc_html($reward_badge); ?>
                                </span>
                                <h3 class="pnp-rule-card-title">
                                    Pravilo #<?php echo esc_html($r['id']); ?>
                                </h3>
                            </div>
                            <div class="pnp-rule-status">
                                <span class="pnp-status-pill <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                                <label class="pnp-toggle-switch">
                                    <input type="checkbox" data-rule-id="<?php echo esc_attr($r['id']); ?>" 
                                           <?php checked($r['active'], 1); ?>>
                                    <span class="pnp-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Rule details -->
                        <div class="pnp-rule-details">
                            <div class="pnp-rule-detail-row">
                                <span class="pnp-rule-detail-label">Nagrada:</span>
                                <span class="pnp-rule-detail-value">
                                    <?php echo esc_html($r['reward_qty']) . 'x ' . ($r['reward_type'] === 'gift' ? 'Poklon' : 'Gratis'); ?>
                                </span>
                            </div>
                            
                            <div class="pnp-rule-detail-row">
                                <span class="pnp-rule-detail-label">Uslov:</span>
                                <span class="pnp-rule-detail-value"><?php echo wp_kses_post($cond_text); ?></span>
                            </div>

                            <div class="pnp-rule-detail-row">
                                <span class="pnp-rule-detail-label">Opseg:</span>
                                <span class="pnp-rule-detail-value">
                                    <?php echo esc_html($scope_label); ?> • <?php echo esc_html($term_label); ?>
                                </span>
                            </div>
                            
                            <div class="pnp-rule-detail-row">
                                <span class="pnp-rule-detail-label">Prioritet:</span>
                                <span class="pnp-rule-detail-value"><?php echo esc_html($r['priority']); ?></span>
                            </div>
                            
                            <?php if (!$r['no_time_limit']) : ?>
                                <div class="pnp-rule-detail-row">
                                    <span class="pnp-rule-detail-label">Raspored:</span>
                                    <span class="pnp-rule-detail-value" style="font-size:12px;">
                                        <?php 
                                        echo $r['schedule_start'] ? date('d.m.Y H:i', strtotime($r['schedule_start'])) : '—';
                                        echo ' → ';
                                        echo $r['schedule_end'] ? date('d.m.Y H:i', strtotime($r['schedule_end'])) : '—';
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="pnp-rule-actions">
                            <a href="<?php echo esc_url(add_query_arg('edit', $r['id'])); ?>" class="pnp-btn pnp-btn-edit">
                                Izmeni
                            </a>
                            <form style="display:inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pnp-delete-form" data-rule-id="<?php echo esc_attr($r['id']); ?>">
                                <?php wp_nonce_field('pnp_delete', PNP_NONCE); ?>
                                <input type="hidden" name="action" value="pnp_delete">
                                <input type="hidden" name="id" value="<?php echo esc_attr($r['id']); ?>">
                                <button type="button" class="pnp-btn pnp-btn-delete pnp-delete-trigger">Obriši</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="pnp-filter-empty" style="display:none;">
                <?php esc_html_e('Nema pravila koja odgovaraju izabranim filterima.', 'pokloni-popusti'); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="pnp-modal" id="pnp-delete-modal" aria-hidden="true" role="dialog" aria-labelledby="pnp-delete-title">
        <div class="pnp-modal-overlay" data-modal-close="true"></div>
        <div class="pnp-modal-content">
            <h3 id="pnp-delete-title"><?php esc_html_e('Potvrda brisanja', 'pokloni-popusti'); ?></h3>
            <p class="pnp-modal-text">
                <?php esc_html_e('Da li ste sigurni da želite da obrišete ovo pravilo? Ova akcija se ne može poništiti.', 'pokloni-popusti'); ?>
            </p>
            <div class="pnp-modal-actions">
                <button type="button" class="button pnp-modal-cancel"><?php esc_html_e('Otkaži', 'pokloni-popusti'); ?></button>
                <button type="button" class="button button-primary pnp-modal-confirm"><?php esc_html_e('Obriši pravilo', 'pokloni-popusti'); ?></button>
            </div>
        </div>
    </div>

    <!-- ========== TAB 2: ADD/EDIT RULE ========== -->
    <div id="pnp-add-rule" class="pnp-tab-content <?php echo esc_attr( $form_tab_class ); ?>">
        <form id="pnp-rule-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pnp_save', PNP_NONCE); ?>
            <input type="hidden" name="action" value="pnp_save">
            <input type="hidden" name="id" value="<?php echo esc_attr($f['id']); ?>">

            <!-- ========== SECTION 1: REWARD ========== -->
            <div class="pnp-form-section">
                <h3>1️⃣ Nagrada (šta korisnik dobija)</h3>
                <div class="pnp-form-grid">
                    <div class="pnp-form-group">
                        <label>Tip nagrade</label>
                        <select id="pnp_reward_type" name="reward_type" required>
                            <option value="gift" <?php selected($f['reward_type'], 'gift'); ?>>🎁 Poklon (iz posebne kategorije)</option>
                            <option value="free" <?php selected($f['reward_type'], 'free'); ?>>🆓 Gratis (bilo koji proizvod, cena 0.01 RSD)</option>
                        </select>
                    </div>
                    
                    <div class="pnp-form-group">
                        <label>Količina</label>
                        <input type="number" min="1" name="reward_qty" value="<?php echo esc_attr($f['reward_qty']); ?>" required>
                    </div>
                </div>

                <!-- Gift products (category ID <?php echo PNP_GIFT_CAT; ?>) -->
                <div id="pnp_reward_gift_box">
                    <label style="display:block; margin:15px 0 10px; font-weight:600;">
                        <input type="checkbox" class="pnp-select-all" data-target="reward">
                        Izaberi sve poklone
                    </label>
                    <input type="text" class="pnp-search-box" placeholder="Pretraži poklone..." data-target="pnp_reward_products">
                    <div id="pnp_reward_products" class="pnp-product-selector">
                        <?php
                        $gift_ids = get_posts([
                            'post_type' => 'product',
                            'numberposts' => -1,
                            'fields' => 'ids',
                            'tax_query' => [[
                                'taxonomy' => 'product_cat',
                                'field' => 'term_id',
                                'terms' => PNP_GIFT_CAT,
                            ]],
                        ]);
                        $sel_gift = array_filter(array_map('absint', explode(',', $f['reward_ids'])));
                        foreach ($gift_ids as $pid2) {
                            $p2 = wc_get_product($pid2);
                            if (!$p2) continue;
                            echo '<div class="pnp-product-item">';
                            printf(
                                '<input type="checkbox" name="reward_ids[]" value="%1$d"%2$s>',
                                esc_attr($pid2),
                                in_array($pid2, $sel_gift, true) ? ' checked' : ''
                            );
                            echo wp_get_attachment_image($p2->get_image_id(), [40, 40]);
                            printf('<span>%s</span>', esc_html($p2->get_name()));
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Free products (scope + term based) -->
                <div id="pnp_reward_filter_box" style="display:none;">
                    <div class="pnp-form-grid">
                        <div class="pnp-form-group">
                            <label>Opseg</label>
                            <select id="reward_scope" name="reward_scope" class="pnp-scope">
                                <option value="linija" <?php selected($f['reward_scope'], 'linija'); ?>>Linija</option>
                                <option value="brend" <?php selected($f['reward_scope'], 'brend'); ?>>Brend</option>
                                <option value="product_cat" <?php selected($f['reward_scope'], 'product_cat'); ?>>Kategorija</option>
                                <option value="product" <?php selected($f['reward_scope'], 'product'); ?>>Proizvod</option>
                            </select>
                        </div>
                        <div class="pnp-form-group">
                            <label>Termin / Tag</label>
                            <select id="reward_term" name="reward_term">
                                <option value="">— Izaberite —</option>
                                <?php
                                foreach (['linija', 'brend', 'product_cat'] as $tax) {
                                    if (!isset($all_terms[$tax])) continue;
                                    foreach ($all_terms[$tax] as $t) {
                                        printf(
                                            '<option value="%1$d" data-tax="%2$s" %3$s>%4$s</option>',
                                            esc_attr($t->term_id),
                                            esc_attr($tax),
                                            selected($f['reward_term'], $t->term_id, false),
                                            esc_html($t->name)
                                        );
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="reward_ids_free" value="<?php echo esc_attr($f['reward_ids']); ?>">
                    
                    <label style="display:block; margin:15px 0 10px;">
                        <input type="checkbox" class="pnp-select-all" data-target="reward_free">
                        Izaberi sve proizvode
                    </label>
                    
                    <input type="text" class="pnp-search-box" placeholder="Pretraži proizvode..." data-target="reward_products">
                    <div id="reward_products" class="pnp-product-selector"></div>

                    <div style="margin-top:15px;">
                        <label>
                            Maks. cena proizvoda (RSD):
                            <input type="number" step="0.01" min="0" name="reward_max_price" value="<?php echo esc_attr($f['reward_max_price']); ?>">
                        </label>
                    </div>
                </div>
            </div>

            <!-- ========== SECTION 2: CONDITION ========== -->
            <div class="pnp-form-section">
                <h3>2️⃣ Uslov (šta korisnik mora da uradi)</h3>
                
                <div class="pnp-form-group">
                    <label style="font-weight:600; margin-bottom:10px; display:block;">Izaberite tip uslova:</label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="radio" name="cond_type" value="buy_x" <?php checked(!$f['enable_buy_y'] && !$f['enable_cart'], true); ?>> 
                        🛒 Kupi X proizvoda
                    </label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="radio" name="cond_type" value="buy_xy" <?php checked($f['enable_buy_y'], 1); ?>> 
                        🛒 Kupi X + Y proizvoda
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="cond_type" value="cart" <?php checked($f['enable_cart'], 1); ?>> 
                        💰 Vrednost korpe ≥ X RSD
                    </label>
                </div>

                <!-- Buy X Block -->
                <div id="buy_x_block" class="cond-block" style="margin-top:20px;">
                    <h4 style="margin-bottom:10px;">X Proizvodi</h4>
                    <div class="pnp-form-grid">
                        <div class="pnp-form-group">
                            <label>Broj X proizvoda</label>
                            <input type="number" min="1" name="buy_x_qty" value="<?php echo esc_attr($f['buy_x_qty']); ?>">
                        </div>
                        <div class="pnp-form-group">
                            <label>Opseg X</label>
                            <select id="buy_x_scope" name="buy_x_scope" class="pnp-scope">
                                <option value="linija" <?php selected($f['buy_x_scope'], 'linija'); ?>>Linija</option>
                                <option value="brend" <?php selected($f['buy_x_scope'], 'brend'); ?>>Brend</option>
                                <option value="product_cat" <?php selected($f['buy_x_scope'], 'product_cat'); ?>>Kategorija</option>
                                <option value="product" <?php selected($f['buy_x_scope'], 'product'); ?>>Proizvod</option>
                            </select>
                        </div>
                        <div class="pnp-form-group">
                            <label>Termin / Tag</label>
                            <select id="buy_x_term" name="buy_x_term">
                                <option value="">— Izaberite —</option>
                                <?php
                                foreach (['linija', 'brend', 'product_cat'] as $tax) {
                                    if (!isset($all_terms[$tax])) continue;
                                    foreach ($all_terms[$tax] as $t) {
                                        printf(
                                            '<option value="%1$d" data-tax="%2$s" %3$s>%4$s</option>',
                                            esc_attr($t->term_id),
                                            esc_attr($tax),
                                            selected($f['buy_x_term'], $t->term_id, false),
                                            esc_html($t->name)
                                        );
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="buy_x_ids" value="<?php echo esc_attr($f['buy_x_ids']); ?>">
                    <label style="display:block; margin:15px 0 10px;">
                        <input type="checkbox" class="pnp-select-all" data-target="buy_x">
                        Izaberi sve X
                    </label>
                    <input type="text" class="pnp-search-box" placeholder="Pretraži X proizvode..." data-target="buy_x_products">
                    <div id="buy_x_products" class="pnp-product-selector"></div>
                </div>

                <!-- Buy Y Block -->
                <div id="buy_y_block" class="cond-block" style="display:none; margin-top:20px;">
                    <h4 style="margin-bottom:10px;">Y Proizvodi</h4>
                    <div class="pnp-form-grid">
                        <div class="pnp-form-group">
                            <label>Broj Y proizvoda</label>
                            <input type="number" min="1" name="buy_y_qty" value="<?php echo esc_attr($f['buy_y_qty']); ?>">
                        </div>
                        <div class="pnp-form-group">
                            <label>Opseg Y</label>
                            <select id="buy_y_scope" name="buy_y_scope" class="pnp-scope">
                                <option value="linija" <?php selected($f['buy_y_scope'], 'linija'); ?>>Linija</option>
                                <option value="brend" <?php selected($f['buy_y_scope'], 'brend'); ?>>Brend</option>
                                <option value="product_cat" <?php selected($f['buy_y_scope'], 'product_cat'); ?>>Kategorija</option>
                                <option value="product" <?php selected($f['buy_y_scope'], 'product'); ?>>Proizvod</option>
                            </select>
                        </div>
                        <div class="pnp-form-group">
                            <label>Termin / Tag</label>
                            <select id="buy_y_term" name="buy_y_term">
                                <option value="">— Izaberite —</option>
                                <?php
                                foreach (['linija', 'brend', 'product_cat'] as $tax) {
                                    if (!isset($all_terms[$tax])) continue;
                                    foreach ($all_terms[$tax] as $t) {
                                        printf(
                                            '<option value="%1$d" data-tax="%2$s" %3$s>%4$s</option>',
                                            esc_attr($t->term_id),
                                            esc_attr($tax),
                                            selected($f['buy_y_term'], $t->term_id, false),
                                            esc_html($t->name)
                                        );
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="buy_y_ids" value="<?php echo esc_attr($f['buy_y_ids']); ?>">
                    <label style="display:block; margin:15px 0 10px;">
                        <input type="checkbox" class="pnp-select-all" data-target="buy_y">
                        Izaberi sve Y
                    </label>
                    <input type="text" class="pnp-search-box" placeholder="Pretraži Y proizvode..." data-target="buy_y_products">
                    <div id="buy_y_products" class="pnp-product-selector"></div>
                </div>

                <!-- Cart Value Block -->
                <div id="cart_block" class="cond-block" style="display:none; margin-top:20px;">
                    <div class="pnp-form-grid">
                        <div class="pnp-form-group">
                            <label>Minimalna vrednost (RSD)</label>
                            <input type="number" step="0.01" min="0" name="cart_val" value="<?php echo esc_attr($f['cart_val']); ?>">
                        </div>
                        <div class="pnp-form-group">
                            <label>Opseg proizvoda u korpi</label>
                            <select id="cart_scope" name="cart_scope" class="pnp-scope">
                                <option value="linija" <?php selected($f['cart_scope'], 'linija'); ?>>Linija</option>
                                <option value="brend" <?php selected($f['cart_scope'], 'brend'); ?>>Brend</option>
                                <option value="product_cat" <?php selected($f['cart_scope'], 'product_cat'); ?>>Kategorija</option>
                                <option value="product" <?php selected($f['cart_scope'], 'product'); ?>>Proizvod</option>
                            </select>
                        </div>
                        <div class="pnp-form-group">
                            <label>Termin / Tag</label>
                            <select id="cart_term" name="cart_term">
                                <option value="">— Izaberite —</option>
                                <?php
                                foreach (['linija', 'brend', 'product_cat'] as $tax) {
                                    if (!isset($all_terms[$tax])) continue;
                                    foreach ($all_terms[$tax] as $t) {
                                        printf(
                                            '<option value="%1$d" data-tax="%2$s" %3$s>%4$s</option>',
                                            esc_attr($t->term_id),
                                            esc_attr($tax),
                                            selected($f['cart_term'], $t->term_id, false),
                                            esc_html($t->name)
                                        );
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="cart_ids" value="<?php echo esc_attr($f['cart_ids']); ?>">
                    <label style="display:block; margin:15px 0 10px;">
                        <input type="checkbox" class="pnp-select-all" data-target="cart">
                        Izaberi sve
                    </label>
                    <input type="text" class="pnp-search-box" placeholder="Pretraži proizvode..." data-target="cart_products">
                    <div id="cart_products" class="pnp-product-selector"></div>
                </div>

                <label style="display:block; margin-top:20px;">
                    <input type="checkbox" name="exclude_sale" value="1" <?php checked($f['exclude_sale'], 1); ?>>
                    Ne računaj snižene artikle u uslovu
                </label>
            </div>

            <!-- ========== SECTION 3: CUSTOM TEXTS ========== -->
            <div class="pnp-form-section">
                <h3>3️⃣ Prilagođeni Tekstovi (opciono)</h3>
                <p style="margin-bottom:15px; color:#646970;">
                    Ovde možeš promeniti tekstove koje korisnici vide za <strong>OVO pravilo</strong>. Ako ostaviš prazno, koristiće se standardni tekstovi.
                </p>

                <?php
                // Load existing custom texts
                $custom_texts = [];
                if (!empty($f['custom_texts'])) {
                    $custom_texts = json_decode($f['custom_texts'], true) ?: [];
                }

                // Get all available text keys
                if (file_exists(PNP_PLUGIN_DIR . 'includes/class-pnp-text-manager.php')) {
                    require_once PNP_PLUGIN_DIR . 'includes/class-pnp-text-manager.php';
                    $text_keys = PNP_Text_Manager::get_all_keys();
                    $merge_tags = PNP_Text_Manager::get_merge_tags();
                } else {
                    $text_keys = [];
                    $merge_tags = [];
                }
                ?>

                <?php if (!empty($text_keys)) : ?>
                <div class="pnp-text-fields">
                    <?php foreach ($text_keys as $key => $default) : 
                        $current_value = isset($custom_texts[$key]) ? $custom_texts[$key] : '';
                    ?>
                        <div class="pnp-text-field" style="margin-bottom:20px;">
                            <label style="font-weight:600; display:block; margin-bottom:5px;">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>
                            </label>
                            <small style="color:#646970; display:block; margin-bottom:5px;">
                                Standardno: <em><?php echo esc_html($default); ?></em>
                            </small>
                            <textarea 
                                name="custom_texts[<?php echo esc_attr($key); ?>]" 
                                rows="2" 
                                style="width:100%; padding:8px;"
                                placeholder="<?php echo esc_attr($default); ?>"
                            ><?php echo esc_textarea($current_value); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <details style="margin-top:20px; padding:15px; background:#f9f9f9; border-radius:4px;">
                    <summary style="cursor:pointer; font-weight:600;">📋 Dostupni Merge Tagovi</summary>
                    <ul style="margin-top:10px; padding-left:20px;">
                        <?php foreach ($merge_tags as $tag => $desc) : ?>
                            <li>
                                <code style="background:#fff; padding:2px 6px; border-radius:3px;"><?php echo esc_html($tag); ?></code>
                                — <?php echo esc_html($desc); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php else : ?>
                    <p style="background:#fff3cd; padding:12px; border-left:4px solid #ffc107;">
                        ⚠️ Text Manager nije pronađen. Kreirajte <code>includes/class-pnp-text-manager.php</code> da bi ste omogućili custom tekstove.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ========== SECTION 4: SCHEDULE & STATUS ========== -->
            <div class="pnp-form-section">
                <h3>4️⃣ Raspored i Status</h3>
                
                <div class="pnp-form-grid">
                    <div class="pnp-form-group">
                        <label>
                            <input type="checkbox" id="no_time_limit" name="no_time_limit" value="1" <?php checked($f['no_time_limit'], 1); ?>>
                            Bez vremenskog ograničenja (uvek aktivno)
                        </label>
                    </div>
                    
                    <div class="pnp-form-group">
                        <label>Prioritet (veći broj = viši prioritet)</label>
                        <input type="number" min="1" name="priority" value="<?php echo esc_attr($f['priority']); ?>" required>
                    </div>
                </div>

                <div id="time_block">
                    <div class="pnp-date-range">
                        <div class="pnp-form-group">
                            <label>Početak</label>
                            <input type="datetime-local" name="schedule_start" value="<?php echo esc_attr(str_replace(' ', 'T', $f['schedule_start'])); ?>">
                        </div>
                        <div class="pnp-form-group">
                            <label>Kraj</label>
                            <input type="datetime-local" name="schedule_end" value="<?php echo esc_attr(str_replace(' ', 'T', $f['schedule_end'])); ?>">
                        </div>
                    </div>
                </div>

                <label style="display:block; margin-top:20px;">
                    <input type="checkbox" name="active" value="1" <?php checked($f['active'], 1); ?>>
                    ✅ Pravilo je aktivno
                </label>
            </div>

            <button type="submit" class="pnp-add-new-btn pnp-save-rule">
                💾 Sačuvaj pravilo
            </button>
        </form>
    </div>
</div>

<!-- ========== ADMIN JS ========== -->
<script>
jQuery(function($){
    const nonce = '<?php echo esc_js(wp_create_nonce(PNP_NONCE)); ?>';
    const pendingRequests = {};
    const hiddenFieldMap = {
        reward: 'reward_ids_free',
        buy_x: 'buy_x_ids',
        buy_y: 'buy_y_ids',
        cart: 'cart_ids'
    };

    function getHiddenIds(group) {
        const fieldName = hiddenFieldMap[group];
        if (!fieldName) {
            return [];
        }
        const raw = $('input[name="' + fieldName + '"]').val() || '';
        return raw.split(',').map(function(id){
            return parseInt(id, 10);
        }).filter(Boolean);
    }

    function syncHiddenIds(group) {
        const fieldName = hiddenFieldMap[group];
        if (!fieldName) {
            return;
        }
        const ids = [];
        $('#' + group + '_products').find('input.pnp-prod:checked').each(function(){
            ids.push($(this).val());
        });
        $('input[name="' + fieldName + '"]').val(ids.join(','));
    }
    
    // ========== Reward Type Toggle ==========
    $('#pnp_reward_type').on('change', function(){
        if (this.value === 'gift') {
            $('#pnp_reward_gift_box').show();
            $('#pnp_reward_filter_box').hide();
        } else {
            $('#pnp_reward_gift_box').hide();
            $('#pnp_reward_filter_box').show();
            loadProducts('reward');
        }
    }).trigger('change');

    // ========== Condition Type Toggle ==========
    $('input[name="cond_type"]').on('change', function(){
        $('#buy_x_block, #buy_y_block, #cart_block').hide();
        
        if (this.value === 'buy_x') {
            $('#buy_x_block').show();
        } else if (this.value === 'buy_xy') {
            $('#buy_x_block, #buy_y_block').show();
        } else if (this.value === 'cart') {
            $('#cart_block').show();
        }
        
        $('.pnp-scope:visible').trigger('change');
    });

    // Trigger initial state
    $('input[name="cond_type"]:checked').trigger('change');

    // ========== Product Loading ==========
    function loadProducts(group) {
        const scope = $('#' + group + '_scope').val();
        const term = $('#' + group + '_term').val() || 0;
        const $target = $('#' + group + '_products');
        
        if (!$target.length) return;
        
        $target.html('<em>Učitavanje…</em>');

        if (pendingRequests[group]) {
            pendingRequests[group].abort();
        }
        
        const selectedIds = getHiddenIds(group);
        pendingRequests[group] = $.post(ajaxurl, {
            action: 'pnp_get_products',
            nonce: nonce,
            tax: scope,
            ids: term
        }).done(function(res){
            if (!res.success) {
                return $target.html('<em>Nema proizvoda.</em>');
            }
            
            let html = '';
            res.data.forEach(function(p){
                const isChecked = selectedIds.includes(p.id) ? ' checked' : '';
                html += '<div class="pnp-product-item">' +
                        '<label><input type="checkbox" class="pnp-prod" data-group="' + group + '" value="' + p.id + '"' + isChecked + '> ' + 
                        p.title + '</label></div>';
            });
            $target.html(html);
            syncHiddenIds(group);
        }).fail(function(){
            $target.html('<em>Greška pri učitavanju.</em>');
        }).always(function(){
            pendingRequests[group] = null;
        });
    }

    // Scope/Term change handlers
    $('.pnp-scope').on('change', function(){
        const group = $(this).attr('id').replace('_scope', '');
        loadProducts(group);
    });

    $('select[id$="_term"]').on('change', function(){
        const group = $(this).attr('id').replace('_term', '');
        loadProducts(group);
    });

    $('.pnp-product-selector').on('change', 'input.pnp-prod', function(){
        const group = $(this).data('group');
        if (group) {
            syncHiddenIds(group);
        }
    });

    // ========== Select All ==========
    $('.pnp-select-all').on('change', function(){
        const target = $(this).data('target');
        const $container = target === 'reward' 
            ? $('#pnp_reward_products')
            : $('#' + target + '_products');
        
        $container.find('input[type="checkbox"]').prop('checked', $(this).is(':checked'));
        if (target !== 'reward') {
            syncHiddenIds(target);
        }
    });

    // ========== Time Limit Toggle ==========
    $('#no_time_limit').on('change', function(){
        $('#time_block').toggle(!this.checked);
    }).trigger('change');

    // ========== Product Search ==========
    $('.pnp-search-box').on('input', function(){
        const searchTerm = $(this).val().toLowerCase();
        const target = $(this).data('target');
        const $container = $('#' + target);
        
        $container.find('.pnp-product-item').each(function(){
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(searchTerm));
        });
    });

    // ========== Form Validation ==========
    $('.pnp-save-rule').on('click', function(e){
        const errors = [];
        
        const rewardType = $('#pnp_reward_type').val();
        if (rewardType === 'gift') {
            if ($('#pnp_reward_products input:checked').length === 0) {
                errors.push('Morate izabrati barem jedan poklon proizvod.');
            }
        }
        
        if (!$('input[name="cond_type"]:checked').val()) {
            errors.push('Morate izabrati tip uslova.');
        }

        if (errors.length) {
            e.preventDefault();
            alert(errors.join('\n'));
        }
    });
});
</script>

<style>
/* Badge styling */
.pnp-rule-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

/* Text fields section */
.pnp-text-fields {
    max-height: 500px;
    overflow-y: auto;
    padding: 10px;
    background: #fafafa;
    border: 1px solid #dcdcde;
    border-radius: 4px;
}

.pnp-text-field textarea {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 13px;
    resize: vertical;
}

/* Merge tags details */
details summary {
    user-select: none;
}

details[open] summary {
    margin-bottom: 10px;
}
</style>
