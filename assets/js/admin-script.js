jQuery(document).ready(function($) {
    // Tab switching
    $('.pnp-tab').on('click', function() {
        var tabId = $(this).data('tab');
        
        $('.pnp-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.pnp-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    // Toggle rule active status
    $('.pnp-toggle-switch input').on('change', function() {
        var $switch = $(this);
        var ruleId = $switch.data('rule-id');
        var isActive = $switch.is(':checked');
        
        $.ajax({
            url: pnpAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'pnp_toggle_active',
                nonce: pnpAdmin.nonce,
                id: ruleId
            },
            success: function(response) {
                if (!response.success) {
                    $switch.prop('checked', !isActive);
                    alert(pnpAdmin.translations.saveError);
                }
            },
            error: function() {
                $switch.prop('checked', !isActive);
                alert(pnpAdmin.translations.saveError);
            }
        });
    });

    // Product search
    $('.pnp-search-box').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        var $container = $(this).next('.pnp-product-selector');
        
        $container.find('.pnp-product-item').each(function() {
            var productName = $(this).find('span').text().toLowerCase();
            $(this).toggle(productName.includes(searchTerm));
        });
    });

    // Select all products
    $('.pnp-select-all').on('change', function() {
        $(this).closest('.pnp-form-group').find('.pnp-product-selector input[type="checkbox"]').prop('checked', $(this).is(':checked'));
    });

    // Form validation
    $('.pnp-save-rule').on('click', function(e) {
        var errors = [];
        
        if ($('#pnp_reward_type').val() === 'gift' && $('#pnp_reward_products input:checked').length === 0) {
            errors.push('Izaberite barem jedan poklon proizvod');
        }
        
        if (!$('input[name="cond_type"]:checked').val()) {
            errors.push('Izaberite tip uslova');
        }

        if (errors.length) {
            e.preventDefault();
            alert(errors.join('\n'));
        }
    });

    // Date range toggle
    $('#no_time_limit').on('change', function() {
        $('#time_block').toggle(!this.checked);
    }).trigger('change');
});