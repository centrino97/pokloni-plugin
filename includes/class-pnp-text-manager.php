<?php
/**
 * PNP Text Manager
 * Centralized custom text system with fallbacks + merge tags
 */

if (!defined('ABSPATH')) exit;

class PNP_Text_Manager {
    
    /**
     * Get text for a rule with fallback
     * 
     * @param int $rule_id Rule ID
     * @param string $key Text key (e.g., 'offer_heading')
     * @param string $fallback Default text
     * @param array $tokens Merge tags: {qty}, {scope_name}, {price}, etc.
     * @return string Final text with tokens replaced
     */
    public static function get($rule_id, $key, $fallback, $tokens = []) {
        global $wpdb;
        
        // 1. Load rule custom texts
        $table = $wpdb->prefix . PNP_TABLE;
        $row = wp_cache_get("pnp_rule_{$rule_id}", 'pnp_rules');
        
        if (false === $row) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT custom_texts FROM {$table} WHERE id = %d",
                $rule_id
            ), ARRAY_A);
            
            if ($row) {
                wp_cache_set("pnp_rule_{$rule_id}", $row, 'pnp_rules', 300);
            }
        }
        
        // 2. Decode JSON
        $custom = !empty($row['custom_texts']) 
            ? json_decode($row['custom_texts'], true) 
            : [];
        
        // 3. Pick custom or fallback
        $text = isset($custom[$key]) && trim($custom[$key]) !== '' 
            ? $custom[$key] 
            : $fallback;
        
        // 4. Replace merge tags
        if (!empty($tokens)) {
            foreach ($tokens as $tag => $value) {
                $text = str_replace('{' . $tag . '}', $value, $text);
            }
        }
        
        return wp_kses_post($text);
    }
    
    /**
     * Get all available text keys with defaults
     * 
     * @return array Associative array of key => default_text
     */
    public static function get_all_keys() {
        return [
            // [pnp_uslovi] - Offer Block
            'offer_heading_not_fulfilled' => __('🎁 Kupi i osvoji poklon', 'pokloni-popusti'),
            'offer_heading_fulfilled' => __('Uslov ispunjen! Izaberite nagradu', 'pokloni-popusti'),
            'offer_cta_continue' => __('Pogledaj ponudu', 'pokloni-popusti'),
            'offer_cta_checkout' => __('Završi kupovinu', 'pokloni-popusti'),
            'offer_text_buy_x' => __('Kupi {qty} {scope_name} proizvoda i biraj poklon proizvod.', 'pokloni-popusti'),
            'offer_text_buy_xy' => __('Kupi {qty_x} {scope_x} i {qty_y} {scope_y} i biraj poklon proizvod.', 'pokloni-popusti'),
            'offer_text_cart' => __('Dodaj u korpu najmanje {price} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti'),
            'offer_text_progress' => __('Dodaj u korpu još {remaining} {scope_name} proizvoda da bi ostvarili poklon.', 'pokloni-popusti'),
            
            // [pnp_gift_picker] - Gift Selection
            'picker_heading' => __('Izaberi poklon', 'pokloni-popusti'),
            'picker_cta' => __('Potvrdi izbor', 'pokloni-popusti'),
            'picker_error' => __('Morate izabrati proizvod.', 'pokloni-popusti'),
            
            // [pnp_gift_info] - Product Page Info
            'info_text_single' => __('🎁 Poklon proizvod dobijate ako kupite {qty} {scope}.', 'pokloni-popusti'),
            'info_text_xy' => __('🎁 Poklon proizvod dobijate ako kupite {qty_x} {scope_x} i {qty_y} {scope_y}.', 'pokloni-popusti'),
            'info_text_cart' => __('🎁 Poklon proizvod dobijate ako vrednost korpe pređe {price}.', 'pokloni-popusti'),
            
            // [pnp_offer_cards] - Banner Carousel
            'card_title' => __('Kupi i osvoji poklon🎁', 'pokloni-popusti'),
            'card_cta' => __('Pogledaj ponudu', 'pokloni-popusti'),
            
            // Single/Multi Offer Display
            'single_offer_text' => __('🎁 Kupovinom proizvoda dobijate poklon', 'pokloni-popusti'),
            'multi_offer_text' => __('🎁 Kupovinom proizvoda birate poklon na kraju kupovine', 'pokloni-popusti'),
        ];
    }
    
    /**
     * Get text keys grouped by section
     * 
     * @return array Grouped structure for admin UI
     */
    public static function get_grouped_keys() {
        return [
            'offer_block' => [
                'label' => __('Offer Block ([pnp_uslovi])', 'pokloni-popusti'),
                'keys' => [
                    'offer_heading_not_fulfilled',
                    'offer_heading_fulfilled',
                    'offer_cta_continue',
                    'offer_cta_checkout',
                    'offer_text_buy_x',
                    'offer_text_buy_xy',
                    'offer_text_cart',
                    'offer_text_progress',
                    'single_offer_text',
                    'multi_offer_text',
                ]
            ],
            'gift_picker' => [
                'label' => __('Gift Picker ([pnp_gift_picker])', 'pokloni-popusti'),
                'keys' => [
                    'picker_heading',
                    'picker_cta',
                    'picker_error',
                ]
            ],
            'gift_info' => [
                'label' => __('Gift Info ([pnp_gift_info])', 'pokloni-popusti'),
                'keys' => [
                    'info_text_single',
                    'info_text_xy',
                    'info_text_cart',
                ]
            ],
            'offer_cards' => [
                'label' => __('Offer Cards ([pnp_offer_cards])', 'pokloni-popusti'),
                'keys' => [
                    'card_title',
                    'card_cta',
                ]
            ],
        ];
    }
    
    /**
     * Get available merge tags
     * 
     * @return array tag => description
     */
    public static function get_merge_tags() {
        return [
            '{qty}' => __('Količina proizvoda', 'pokloni-popusti'),
            '{qty_x}' => __('Količina X proizvoda', 'pokloni-popusti'),
            '{qty_y}' => __('Količina Y proizvoda', 'pokloni-popusti'),
            '{scope}' => __('Naziv kategorije/brenda/linije', 'pokloni-popusti'),
            '{scope_name}' => __('Naziv opsega', 'pokloni-popusti'),
            '{scope_x}' => __('Naziv X opsega', 'pokloni-popusti'),
            '{scope_y}' => __('Naziv Y opsega', 'pokloni-popusti'),
            '{price}' => __('Cena sa valutom', 'pokloni-popusti'),
            '{remaining}' => __('Preostala količina', 'pokloni-popusti'),
            '{product_name}' => __('Naziv proizvoda', 'pokloni-popusti'),
        ];
    }
}
