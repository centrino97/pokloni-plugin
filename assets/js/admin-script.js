jQuery(document).ready(function($) {
    const $ruleCards = $('.pnp-rule-card');
    const $ruleList = $('.pnp-rule-cards');
    const $emptyState = $('.pnp-filter-empty');

    // Tab switching
    $('.pnp-tab').on('click', function() {
        var tabId = $(this).data('tab');

        $('.pnp-tab').removeClass('active');
        $(this).addClass('active');

        $('.pnp-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    function updateStatus($card, isActive) {
        $card.attr('data-active', isActive ? '1' : '0');
        $card.data('active', isActive ? 1 : 0);
        const $pill = $card.find('.pnp-status-pill');
        if (isActive) {
            $pill.text('Aktivno').removeClass('pnp-status-inactive').addClass('pnp-status-active');
        } else {
            $pill.text('Onemogućeno').removeClass('pnp-status-active').addClass('pnp-status-inactive');
        }
    }

    // Toggle rule active status
    $('.pnp-toggle-switch input').on('change', function() {
        var $switch = $(this);
        var $card = $switch.closest('.pnp-rule-card');
        var ruleId = $switch.data('rule-id');
        var isActive = $switch.is(':checked');

        $switch.prop('disabled', true);
        $card.addClass('pnp-is-saving');

        $.ajax({
            url: pnpAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'pnp_toggle_active',
                nonce: pnpAdmin.nonce,
                id: ruleId
            },
            success: function(response) {
                if (response && response.success === false) {
                    $switch.prop('checked', !isActive);
                    updateStatus($card, !isActive);
                    alert(pnpAdmin.translations.saveError);
                } else {
                    updateStatus($card, isActive);
                }
                applyRuleFilters();
            },
            error: function() {
                $switch.prop('checked', !isActive);
                updateStatus($card, !isActive);
                alert(pnpAdmin.translations.saveError);
            },
            complete: function() {
                $switch.prop('disabled', false);
                $card.removeClass('pnp-is-saving');
            }
        });
    });

    // Filters & sorting
    function applyRuleFilters() {
        const search = ($('.pnp-rules-search').val() || '').toLowerCase();
        const status = $('#pnp-filter-status').val();
        const reward = $('#pnp-filter-reward').val();
        const condition = $('#pnp-filter-condition').val();

        let visibleCount = 0;

        $ruleCards.each(function() {
            const $card = $(this);
            const matchesSearch = !search || ($card.data('search') || '').includes(search);
            const matchesStatus = status === 'all' || (status === 'active' && $card.data('active') === 1) || (status === 'inactive' && $card.data('active') === 0);
            const matchesReward = reward === 'all' || $card.data('reward-type') === reward;
            const matchesCondition = condition === 'all' || $card.data('condition-type') === condition;

            const shouldShow = matchesSearch && matchesStatus && matchesReward && matchesCondition;
            $card.toggle(shouldShow);
            if (shouldShow) {
                visibleCount += 1;
            }
        });

        const total = parseInt($('.pnp-rules-count').data('total'), 10) || 0;
        $('.pnp-rules-count').text('Prikazano ' + visibleCount + ' od ' + total);
        $emptyState.toggle(visibleCount === 0);
    }

    function sortRuleCards() {
        const sortValue = $('#pnp-sort-rules').val();
        const $cards = $ruleCards.toArray();

        $cards.sort(function(a, b) {
            const $a = $(a);
            const $b = $(b);
            const priorityA = parseInt($a.data('priority'), 10) || 0;
            const priorityB = parseInt($b.data('priority'), 10) || 0;
            const idA = parseInt($a.data('rule-id'), 10) || 0;
            const idB = parseInt($b.data('rule-id'), 10) || 0;

            if (sortValue === 'priority_asc') return priorityA - priorityB;
            if (sortValue === 'priority_desc') return priorityB - priorityA;
            if (sortValue === 'id_asc') return idA - idB;
            if (sortValue === 'id_desc') return idB - idA;
            return (parseInt($a.data('initial-index'), 10) || 0) - (parseInt($b.data('initial-index'), 10) || 0);
        });

        $ruleList.append($cards);
    }

    $('.pnp-rules-search, .pnp-rules-filter').on('input change', function() {
        applyRuleFilters();
    });

    $('#pnp-sort-rules').on('change', function() {
        sortRuleCards();
        applyRuleFilters();
    });

    $('.pnp-rules-reset').on('click', function() {
        $('.pnp-rules-search').val('');
        $('#pnp-filter-status').val('all');
        $('#pnp-filter-reward').val('all');
        $('#pnp-filter-condition').val('all');
        $('#pnp-sort-rules').val('priority_desc');
        sortRuleCards();
        applyRuleFilters();
    });

    if ($ruleCards.length) {
        sortRuleCards();
        applyRuleFilters();
    }

    // Delete confirmation modal
    let pendingDeleteForm = null;
    const $modal = $('#pnp-delete-modal');

    function closeModal() {
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        pendingDeleteForm = null;
    }

    $(document).on('click', '.pnp-delete-trigger', function() {
        pendingDeleteForm = $(this).closest('form');
        $modal.addClass('is-open').attr('aria-hidden', 'false');
    });

    $modal.on('click', '[data-modal-close], .pnp-modal-cancel', function() {
        closeModal();
    });

    $modal.on('click', '.pnp-modal-confirm', function() {
        if (pendingDeleteForm) {
            pendingDeleteForm.submit();
        }
        closeModal();
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && $modal.hasClass('is-open')) {
            closeModal();
        }
    });
});
