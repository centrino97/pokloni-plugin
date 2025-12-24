jQuery(document).ready(function($) {
    const $ruleCards = $('.pnp-rule-card');
    const $ruleList = $('.pnp-rule-cards');
    const $emptyState = $('.pnp-filter-empty');
    const $ruleModal = $('#pnp-add-rule');

    // Tab switching
    $('.pnp-tab').on('click', function(event) {
        var tabId = $(this).data('tab');

        if (tabId === 'pnp-add-rule' && $ruleModal.length) {
            event.preventDefault();
            openRuleModal();
            return;
        }

        $('.pnp-tab').removeClass('active');
        $(this).addClass('active');

        $('.pnp-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    function openRuleModal() {
        $ruleModal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('pnp-modal-open');
        showStep(1);
        updateSummary();
    }

    function closeRuleModal() {
        $ruleModal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('pnp-modal-open');
    }

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

    // Rule editor modal + steps
    const $steps = $('.pnp-rule-step');
    const $sections = $('.pnp-step');
    const $stepPrev = $('.pnp-step-prev');
    const $stepNext = $('.pnp-step-next');
    const $stepSave = $('.pnp-step-save');

    function showStep(step) {
        const nextStep = Math.min(Math.max(step, 1), 4);
        $sections.hide().filter('[data-step="' + nextStep + '"]').show();
        $steps.removeClass('is-active').filter('[data-step="' + nextStep + '"]').addClass('is-active');
        $stepPrev.toggle(nextStep > 1);
        $stepNext.toggle(nextStep < 4);
        $stepSave.toggle(nextStep === 4);
        $ruleModal.data('current-step', nextStep);
    }

    $steps.on('click', function() {
        showStep(parseInt($(this).data('step'), 10));
    });

    $stepPrev.on('click', function() {
        showStep(($ruleModal.data('current-step') || 1) - 1);
    });

    $stepNext.on('click', function() {
        showStep(($ruleModal.data('current-step') || 1) + 1);
    });

    $(document).on('click', '.pnp-rule-modal-close, .pnp-rule-modal .pnp-modal-overlay', function() {
        closeRuleModal();
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && $ruleModal.hasClass('is-open')) {
            closeRuleModal();
        }
    });

    function scopeSummary(scopeSelector, termSelector, containerSelector, hiddenName) {
        const scopeVal = $(scopeSelector).val();
        const scopeText = $(scopeSelector + ' option:selected').text() || '—';
        if (scopeVal === 'product') {
            let count = $(containerSelector).find('input[type="checkbox"]:checked').length;
            if (!count && hiddenName) {
                const raw = $('input[name="' + hiddenName + '"]').val() || '';
                count = raw ? raw.split(',').filter(Boolean).length : 0;
            }
            return scopeText + ' (odabrano ' + count + ')';
        }
        const termText = $(termSelector + ' option:selected').text() || '—';
        return scopeText + ': ' + termText;
    }

    function updateSummary() {
        if (!$ruleModal.length) return;

        const rewardType = $('#pnp_reward_type').val();
        const rewardQty = $('input[name="reward_qty"]').val() || '1';
        let rewardText = '';

        if (rewardType === 'gift') {
            const count = $('#pnp_reward_products input:checked').length;
            rewardText = 'Poklon × ' + rewardQty + (count ? ' (odabrano ' + count + ')' : ' (svi pokloni)');
        } else {
            const rewardScope = scopeSummary('#reward_scope', '#reward_term', '#reward_products', 'reward_ids_free');
            const maxPrice = $('input[name="reward_max_price"]').val();
            rewardText = 'Gratis × ' + rewardQty + ' (' + rewardScope + ')';
            if (maxPrice) {
                rewardText += ' • max ' + maxPrice + ' RSD';
            }
        }

        const condType = $('input[name="cond_type"]:checked').val();
        let conditionText = '—';
        let scopeText = '—';

        if (condType === 'cart') {
            const cartVal = $('input[name="cart_val"]').val() || '0';
            conditionText = 'Korpa ≥ ' + cartVal + ' RSD';
            scopeText = scopeSummary('#cart_scope', '#cart_term', '#cart_products', 'cart_ids');
        } else if (condType === 'buy_xy') {
            const qtyX = $('input[name="buy_x_qty"]').val() || '1';
            const qtyY = $('input[name="buy_y_qty"]').val() || '1';
            conditionText = 'Kupi ' + qtyX + ' X + ' + qtyY + ' Y';
            scopeText = 'X: ' + scopeSummary('#buy_x_scope', '#buy_x_term', '#buy_x_products', 'buy_x_ids') +
                ' | Y: ' + scopeSummary('#buy_y_scope', '#buy_y_term', '#buy_y_products', 'buy_y_ids');
        } else {
            const qtyX = $('input[name="buy_x_qty"]').val() || '1';
            conditionText = 'Kupi ' + qtyX + ' X';
            scopeText = scopeSummary('#buy_x_scope', '#buy_x_term', '#buy_x_products', 'buy_x_ids');
        }

        const noLimit = $('#no_time_limit').is(':checked');
        const scheduleStart = $('input[name="schedule_start"]').val();
        const scheduleEnd = $('input[name="schedule_end"]').val();
        const scheduleText = noLimit ? 'Uvek aktivno' : (scheduleStart || '—') + ' → ' + (scheduleEnd || '—');

        $('.pnp-rule-summary-text').text('Ako: ' + conditionText + ' → Dobija: ' + rewardText);
        $('[data-summary="reward"]').text(rewardText);
        $('[data-summary="condition"]').text(conditionText);
        $('[data-summary="scope"]').text(scopeText);
        $('[data-summary="schedule"]').text(scheduleText);
    }

    $(document).on('change input', '#pnp-rule-form input, #pnp-rule-form select, #pnp-rule-form textarea', function() {
        updateSummary();
    });

    if ($ruleModal.data('edit') === 1) {
        openRuleModal();
    }
});
