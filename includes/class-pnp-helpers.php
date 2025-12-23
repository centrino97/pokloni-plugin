<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class: PNP_Helpers
 *
 * A few utility functions to:
 *  - Provide localized scope prefixes/labels
 *  - Render “describe_scope” for admin overview
 */
class PNP_Helpers {

    /**
     * Prefix label for “scope” text (e.g. “iz kategorije”, “od brenda”).
     */
    public static function scope_prefix( $scope ) {
        switch ( $scope ) {
            case 'product_cat':
                return __( 'iz kategorije', 'pokloni-popusti' );
            case 'brend':
                return __( 'od brenda', 'pokloni-popusti' );
            case 'linija':
                return __( 'iz linije', 'pokloni-popusti' );
            default:
                return '';
        }
    }

    /**
     * Human‐readable label for a scope (for the admin summary).
     */
    public static function scope_label( $scope ) {
        $map = [
            'product'     => __( 'proizvoda',    'pokloni-popusti' ),
            'product_cat' => __( 'kategorije',   'pokloni-popusti' ),
            'brend'       => __( 'brenda',       'pokloni-popusti' ),
            'linija'      => __( 'linije',       'pokloni-popusti' ),
        ];
        return isset( $map[ $scope ] ) ? $map[ $scope ] : esc_html( $scope );
    }

    /**
     * Describe a single product or a taxonomy‐term as an <a> link.
     * If $ids_csv is provided, returns comma‐separated product‐edit links.
     */
    public static function describe_scope( $scope, $term, $ids_csv ) {
        // If scope is “product”, list individual products:
        if ( 'product' === $scope ) {
            $list    = [];
            $raw_ids = [];

            if ( ! empty( $ids_csv ) ) {
                $raw_ids = array_filter( array_map( 'absint', explode( ',', $ids_csv ) ) );
            } elseif ( $term ) {
                $raw_ids = [ absint( $term ) ];
            }

            foreach ( $raw_ids as $pid ) {
                $p = wc_get_product( $pid );
                if ( $p ) {
                    $list[] = sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( get_edit_post_link( $pid ) ),
                        esc_html( $p->get_name() )
                    );
                }
            }
            return implode( ', ', $list );
        }

        // If scope is one of our taxonomies (category/brand/line):
        if ( in_array( $scope, [ 'product_cat', 'brend', 'linija' ], true ) && $term ) {
            $t = get_term( absint( $term ), $scope );
            if ( $t && ! is_wp_error( $t ) ) {
                return sprintf(
                    '<a href="%s">%s</a>',
                    esc_url( get_term_link( $t ) ),
                    esc_html( $t->name )
                );
            }
        }

        return '';
    }
}
