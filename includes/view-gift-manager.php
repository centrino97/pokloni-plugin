<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Upravljanje Poklon Proizvodima</h1>
    <p style="background:#e7f5ff; padding:12px; border-left:4px solid #2271b1;">
        <strong>Važno:</strong> Ovi proizvodi su dostupni SAMO kao poklon i NE MOGU se direktno kupiti.
    </p>
    
    <div style="margin:20px 0;">
        <button id="pnp-add-gift" class="button button-primary">+ Dodaj novi poklon proizvod</button>
        <button id="pnp-reload-gifts" class="button">🔄 Osveži listu</button>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="80">Slika</th>
                <th>Naziv proizvoda</th>
                <th width="100">Cena</th>
                <th width="120">Stanje</th>
                <th width="100">Akcije</th>
            </tr>
        </thead>
        <tbody id="pnp-gift-list">
            <?php
            $gift_ids = get_posts([
                'post_type' => 'product',
                'numberposts' => -1,
                'fields' => 'ids',
                'tax_query' => [[
                    'taxonomy' => 'product_cat',
                    'terms' => PNP_GIFT_CAT,
                ]],
            ]);

            if (empty($gift_ids)) {
                echo '<tr><td colspan="5" style="text-align:center; padding:20px;">Nema poklon proizvoda. Klikni "Dodaj novi" da kreiraš.</td></tr>';
            }

            foreach ($gift_ids as $pid) {
                $p = wc_get_product($pid);
                if (!$p) continue;

                $stock = $p->get_stock_quantity();
            ?>
                <tr data-id="<?php echo $pid; ?>">
                    <td class="pnp-thumb"><?php echo $p->get_image('thumbnail'); ?></td>
                    <td class="pnp-name"><?php echo esc_html($p->get_name()); ?></td>
                    <td class="pnp-price"><?php echo $p->get_price_html() ?: '<span style="color:#999;">—</span>'; ?></td>
                    <td class="pnp-stock">
                        <input type="number" class="pnp-stock-input" data-id="<?php echo $pid; ?>" 
                               value="<?php echo esc_attr($stock); ?>" min="0" step="1" 
                               style="width:70px;" placeholder="0">
                    </td>
                    <td class="pnp-actions">
                        <a href="<?php echo get_edit_post_link($pid); ?>" class="button-link" title="Izmeni">✏️</a>
                        <button class="button-link pnp-trash-gift" data-id="<?php echo $pid; ?>" title="Obriši">🗑</button>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<style>
    .pnp-thumb img { width: 52px; height: 52px; object-fit: cover; border-radius: 4px; }
    .pnp-name { font-weight: 500; font-size: 14px; line-height: 1.3; }
    .pnp-price { font-size: 13px; color: #646970; }
    .pnp-stock input { padding: 4px 6px; height: 30px; }
    .pnp-actions .button-link { background: none; border: none; cursor: pointer; font-size: 18px; margin-right: 8px; }
    #pnp-gift-list tr:hover { background: #f6f7f7; }
</style>

<script>
jQuery(function($) {
    $('#pnp-add-gift').on('click', function() {
        window.location.href = '<?php echo admin_url("post-new.php?post_type=product&pnp_preselect_gift_cat=1"); ?>';
    });

    // Auto-save stanje kad korisnik klikne van inputa
    $(document).on('change', '.pnp-stock-input', function () {
        const $input = $(this);
        const pid = $input.data('id');
        const newStock = $input.val();

        $.post(ajaxurl, {
            action: 'pnp_update_gift_stock',
            nonce: '<?php echo wp_create_nonce(PNP_NONCE); ?>',
            product_id: pid,
            stock: newStock
        }).done(res => {
            if (!res.success) {
                alert('Greška: ' + res.data);
            }
        });
    });

    // Trash
    $(document).on('click', '.pnp-trash-gift', function () {
        if (!confirm('Da li si siguran da želiš da obrišeš ovaj poklon proizvod?')) return;
        const $row = $(this).closest('tr');
        const pid = $(this).data('id');

        $.post(ajaxurl, {
            action: 'pnp_trash_gift_product',
            nonce: '<?php echo wp_create_nonce(PNP_NONCE); ?>',
            product_id: pid
        }).done(res => {
            if (res.success) {
                $row.fadeOut(300, function () { $(this).remove(); });
                $('<div class="notice notice-success is-dismissible"><p>Proizvod je premesten u trash.</p></div>')
                    .insertAfter('h1');
            } else {
                alert('Greška: ' + res.data);
            }
        });
    });

    // Osveži listu
    $('#pnp-reload-gifts').on('click', function() {
        location.reload();
    });
});
</script>