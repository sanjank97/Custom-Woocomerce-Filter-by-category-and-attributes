<?php
/**
 * Plugin Name: Custom WooCommerce Product Filter
 * Description: Display product attributes as checkboxes on the shop sidebar and filter products using Ajax.
 * Version: 1.1
 * Author: Gigsoft Dev
 * Text Domain: custom-woocommerce-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_WooCommerce_Product_Filter {

    public function __construct() {
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        // Add the filter form to the shop sidebar
        add_action( 'woocommerce_sidebar', array( $this, 'display_filter_form' ) );

        // Handle Ajax request
        add_action( 'wp_ajax_custom_filter_products', array( $this, 'filter_products' ) );
        add_action( 'wp_ajax_nopriv_custom_filter_products', array( $this, 'filter_products' ) );
      
    }


    public function enqueue_scripts() {
        if ( is_shop() || is_product_category() || is_product_tag() ) {

            wp_enqueue_script( 'custom-ajax-filter', plugin_dir_url( __FILE__ ) . 'js/custom-ajax-filter.js', array( 'jquery' ), '1.0', true );

            wp_localize_script( 'custom-ajax-filter', 'ajax_filter_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
            ));

             wp_enqueue_style( 'custom-ajax-filter-style', plugin_dir_url( __FILE__ ) . 'css/custom-ajax-filter.css' );
        }
    }




    public function display_filter_form() {
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return; // Only display on shop and product archive pages
        }

        echo '<div class="sidebarFilter">
        <h3>Filter</h3>
        <div class="ajax-loader"><img width="100%" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'loader.gif' ) . '" alt="Loading..."></div>';
        echo '<form method="GET" action="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" id="attribute-filter-form">';
        //Called the attributes checkbox
        $this->display_product_attributes_with_checkboxes();
        echo '</form></div>';
        //for closing custom div for archive page
        echo '</div></div>';
    }
    

    //Display the Attributes
    private function display_product_attributes_with_checkboxes() {
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ( ! empty( $attribute_taxonomies ) ) {
            foreach ( $attribute_taxonomies as $tax ) {
                $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
                $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    echo '<div class="woocommerce-attribute-list">';
                    echo '<label>' . esc_html( $tax->attribute_label ) . '</label>';
                    foreach ( $terms as $term ) {
                        echo '<div class="attribute-term">';
                        echo '<input type="checkbox" name="' . esc_attr( $taxonomy ) . '[]" value="' . esc_attr( $term->slug ) . '">';
                        echo '<span>' . esc_html( $term->name ) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
        }
    }

    //Ajax Request Handle 
    public function filter_products() {
        $tax_query = array( 'relation' => 'AND' );

        $attribute_taxonomies = wc_get_attribute_taxonomies();
        foreach ( $attribute_taxonomies as $tax ) {

            $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

            if ( isset( $_GET[$taxonomy] ) && is_array( $_GET[$taxonomy] ) ) {
                $terms = array_map( 'sanitize_text_field', $_GET[$taxonomy] );

                if ( ! empty( $terms ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    );
                }
            }
        }

        // Default to showing 16 products if no taxonomy query is applied
        if ( empty( $tax_query ) || count( $tax_query ) === 1 && isset( $tax_query[0]['relation'] ) ) {
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => 16, // Default number of products
                'orderby'       => 'date',
                'order'         => 'DESC',
            );
        } else {
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'orderby'       => 'date',
                'order'         => 'DESC',
                'tax_query'      => $tax_query,
            );
        }

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                wc_get_template_part( 'content', 'product' );
            }
            wp_reset_postdata();
        } else {
            echo '<p>No products found</p>';
        }

        wp_die(); // Necessary to terminate immediately and return a proper response
    }
}
new Custom_WooCommerce_Product_Filter();
add_action( 'woocommerce_before_main_content', function(){
    //Added custom container for the archive page
    echo '<div class="container"><div class="customShopwrap">';
} );

