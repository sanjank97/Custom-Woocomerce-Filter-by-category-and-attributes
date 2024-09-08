<?php
/**
 * Plugin Name: Custom WooCommerce Product Filter
 * Description: Display product category and attributes on the shop sidebar and filter products by category and attributes.
 * Version: 1.1
 * Author: Gigsoft Dev
 * Text Domain: custom-woocommerce-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_WooCommerce_Product_Filter {

    private $shortcode_category = '';
    public function __construct() {
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        // Add the filter form to the shop sidebar
        add_action( 'woocommerce_sidebar', array( $this, 'display_filter_form' ) );
        add_action( 'woocommerce_before_main_content', array( $this, 'add_container_html') );

        // Register the widget
        add_action( 'widgets_init', array( $this, 'register_custom_filter_widget' ) );

        add_action( 'pre_get_posts', array( $this, 'filter_products_by_attributes' ), 1 );

        // This hooks handle the products display by the shortcode
        add_filter( 'woocommerce_shortcode_products_query', array( $this, 'filter_shortcode_products_query' ), 10, 3 );

        // Register shortcode
        add_shortcode( 'custom_wc_product_filter', array( $this, 'render_filter_shortcode' ) );

    }
    
        // Set the category
        public function set_shortcode_category( $category ) {
            $this->shortcode_category = $category;
        }
    
        // Get the category
        public function get_shortcode_category() {
            return $this->shortcode_category;
        }

    public function enqueue_scripts() {
        if ( is_shop() || is_product_category() || is_product_tag() || is_page()) {
            wp_enqueue_style( 'custom-ajax-filter-style', plugin_dir_url( __FILE__ ) . 'css/custom-ajax-filter.css' );
        }
    }

    public function display_filter_form() {

       
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_page() ) {
            return; // Only display on shop and product archive pages
        }
        $clearall = '';
                    
        $flagClear = 0;
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ( ! empty( $attribute_taxonomies ) ) {
            foreach ( $attribute_taxonomies as $tax ) {
                $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
                if(isset($_GET[$taxonomy])) {
                    $flagClear = 1;
                }
            }
          
        }
        if(isset($_GET['product_cat'])){
            $flagClear = 1;
        }
        
        if(  $flagClear ==1 ) {
            $class ="clearall";
            $shop_url = wc_get_page_permalink('shop');
            if(is_page() ) {
                $class = 'clearall'.' clearall_on_page';
                $shop_url = get_permalink();
            }

           
            $clearall ='<a href="'. $shop_url.'" class="'.$class.'">Clear All</a>';
        }

        echo '<div class="sidebarFilter">
        <h3>Filter</h3>';
        echo '<form method="GET" action="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" id="attribute-filter-form">';
        echo $clearall;
        $this->display_product_attributes_with_checkboxes();
        echo '</form></div>';
        if ( is_shop() || is_product_category() || is_product_tag() ){
           echo '</div></div>';
        }
      
    }

    private function display_product_attributes_with_checkboxes() {

        //Initialize variable
        $selected_categories = array();
        $selected_terms = array(); 
        $product_ids = array();
        $selected_taxonomies = array();
        $category_page = $this->get_shortcode_category();

        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ( ! empty( $attribute_taxonomies ) ) {
            foreach ( $attribute_taxonomies as $tax ) {
                $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
                if(isset($_GET[$taxonomy])) {
                    $selected_terms[$taxonomy] =$_GET[$taxonomy];  
                    $selected_taxonomies[] = $taxonomy; 
                }
            }
          
        }
        if(isset($_GET['product_cat'])) {
            $selected_categories[]= $_GET['product_cat'];  
        }
        if(!empty($category_page)){
            $selected_categories[] = $category_page;
        }

        // Display product categories
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));

        // List of categories to exclude
        $excluded_categories = array('HPD', 'Merchandise', 'Promo');

        if(!empty($selected_categories) || !empty($selected_terms)) {
            $product_ids = $this->get_products_by_filters( $selected_terms, $selected_categories);
            $categories  = wp_get_object_terms($product_ids, 'product_cat', array( 'fields' => 'all' ));
        }

        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            echo '<div class="woocommerce-category-list">';
            echo '<label>' . esc_html__('Category', 'textdomain') . '</label>';
            echo '<div class="woocommerce-category-list-data">';
            foreach ( $categories as $category ) {
                 // Skip excluded categories
                if (in_array($category->name, $excluded_categories)) {
                    continue;
                }
                $current_url = add_query_arg(null, null);

                $remove_url = remove_query_arg('product_cat', $current_url);
                
                $url = add_query_arg(array('product_cat' => $category->slug), $current_url);

                if ( isset($_GET['product_cat']) && $_GET['product_cat'] === $category->slug || !empty($category_page) && $category_page === $category->slug ) {

                    if(!empty($category_page)) {
                        echo '<div class="category-term">';
                        echo esc_html( $category->name );
                        echo '</div>';
                    }else{
                        
                        echo '<div class="category-term">';
                        echo esc_html( $category->name );
                        echo ' <a href="' . esc_url( $remove_url ) . '" class="remove-filter">×</a>';
                        echo '</div>';
                    }
                   
                } else {
                    if(!isset($_GET['product_cat']) && empty($category_page) ) {
                        $url = $this->remove_page_and_product_page($url);
                        echo '<div class="category-term">';
                        echo '<a href="' . esc_url( $url ) . '">' . esc_html( $category->name ) . '</a>';
                        echo '</div>';
                    }
                 
                }
            }
            echo '</div>';
            echo '</div>';
        }

        //Display product attributes terms
        if ( ! empty( $attribute_taxonomies ) ) {

            foreach ( $attribute_taxonomies as $tax ) {

                $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
                $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

                if(!empty($selected_categories)|| !empty($selected_terms)) {
                    $product_ids = $this->get_products_by_filters( $selected_terms, $selected_categories);
                    $terms = wp_get_object_terms( $product_ids, $taxonomy, array( 'fields' => 'all' ) );               
                }

                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    echo '<div class="woocommerce-attribute-list">';
                    echo '<label>' . esc_html( $tax->attribute_label ) . '</label>';
                    echo '<div class="woocommerce-category-list-data">';
                    foreach ( $terms as $term ) {
                        // Construct the URL, keeping the existing $_GET parameters
                        $current_url = add_query_arg( null, null ); // Get the current URL with all existing query parameters
                        // Remove the current term from the query if it's already selected (to create a "remove filter" link)
                        $remove_url = remove_query_arg( $taxonomy, $current_url );               
                        // Add the term slug to the query if it's not already selected
                        $url = add_query_arg( array(
                            $taxonomy => $term->slug,
                        ), $current_url );
                
                        if (in_array($term->slug, $selected_terms)) {
                         
                            echo '<div class="attribute-term">';
                            echo esc_html( $term->name );
                            echo ' <a href="' . esc_url( $remove_url ) . '" class="remove-filter">×</a>';
                            // Close icon to remove the filter
                            echo '</div>';
                      
                        }
                       else {
                           $term_names = $this->get_term_names_by_taxonomies($selected_taxonomies);
                            if(!in_array($term->name, $term_names )){
                            $url = $this->remove_page_and_product_page($url);
                            // Display the term name with an anchor tag
                            echo '<div class="attribute-term">';
                            echo '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
                            echo '</div>';
                            }        
                        }
                      
                    }
                    echo '</div>';
                    echo '</div>';
                   
                }
                   
            }

          
        }

    }

    public function get_term_names_by_taxonomies($selected_taxonomies) {
        $term_names = array();
        foreach($selected_taxonomies as $taxo) {
            $terms = get_terms(array(
                'taxonomy'   => $taxo,
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $term_names[] = $term->name; // Store term name in the array
                }
            } 
        }
        return $term_names;
    }

     //get products by selected terms and selected categories
     public function get_products_by_filters( $selected_terms = array(), $selected_categories = array() ) {
        $args = array(
            'post_type'      => 'product',
            'fields'         => 'ids', // We only need IDs
            'posts_per_page' => -1,    // Retrieve all matching products
            'tax_query'      => array(
                'relation' => 'AND',
            ),
        );
    
        // Add attribute term filters
        foreach ( $selected_terms as $taxonomy => $term_slugs ) {
            // Ensure $term_slugs is an array
            if ( ! is_array( $term_slugs ) ) {
                $term_slugs = array( $term_slugs );
            }
    
            $args['tax_query'][] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $term_slugs,
                'operator' => 'IN',
            );
        }
    
        // Add category filters
        if ( ! empty( $selected_categories ) ) {
            // Ensure $selected_categories is an array
            if ( ! is_array( $selected_categories ) ) {
                $selected_categories = array( $selected_categories );
            }
    
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $selected_categories,
                'operator' => 'IN',
            );
        }
    
        $query = new WP_Query( $args );
    
        return $query->posts;
    }
    

   

    

// Filter products based on URL query parameters
public function filter_products_by_attributes( $query ) {
    if ( ! is_admin() && $query->is_main_query() ) {
        
        $tax_query = array( 'relation' => 'AND' ); // Initialize with 'AND' relation
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ( ! empty( $attribute_taxonomies ) ) {
            foreach ( $attribute_taxonomies as $tax ) {
                $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

                if ( isset( $_GET[$taxonomy] ) && ! empty( $_GET[$taxonomy] ) ) {
                    $term_slugs = array_map( 'sanitize_text_field', (array) $_GET[$taxonomy] ); // Handle multiple slugs
                    if ( ! empty( $term_slugs ) ) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $term_slugs,
                            'operator' => 'IN',
                        );
                    }
                }
            }
        }

        // Add category filters
        if ( isset( $_GET['product_cat'] ) && ! empty( $_GET['product_cat'] ) ) {
            $category = sanitize_text_field( $_GET['product_cat'] );
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category,
                'operator' => 'IN',
            );
        }

        if ( count( $tax_query ) > 1 ) {
            $query->set( 'tax_query', $tax_query );
        }
    }
}

  // Shortcode callback function
  public function render_filter_shortcode($atts) {
       // Extract shortcode attributes and set default values
       $atts = shortcode_atts( array(
        'category' => '', // default value
    ), $atts, 'custom_wc_product_filter' );

    // Sanitize and process the category attribute
    $category_slug = sanitize_text_field( $atts['category'] );
    if ( isset( $atts['category'] ) ) {
        $this->set_shortcode_category($category_slug );
    }


    ob_start();
     $this->display_filter_form();
    return ob_get_clean();
}

public function filter_shortcode_products_query( $query_args, $atts, $type ) {

    $tax_query = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : array( 'relation' => 'AND' );

    $attribute_taxonomies = wc_get_attribute_taxonomies();
    
    if ( ! empty( $attribute_taxonomies ) ) {

        foreach ( $attribute_taxonomies as $tax ) {
            $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

            if ( isset( $_GET[$taxonomy] ) ) {
                $term_slugs = array_map( 'sanitize_text_field', (array) $_GET[$taxonomy] );
                if ( ! empty( $term_slugs ) ) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => $term_slugs,
                        'operator' => 'IN',
                    );
                }
            }
        }
    }

    // Add category filters
    if ( isset( $_GET['product_cat'] ) && ! empty( $_GET['product_cat'] ) ) {
        $category = sanitize_text_field( $_GET['product_cat'] );
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $category,
            'operator' => 'IN',
        );

   }

    if ( count( $tax_query ) > 1 ) {
        $query_args['tax_query'] = $tax_query;
    }

    return $query_args;
}
    
function remove_page_and_product_page($url) {
    // Parse the URL into its components
    $url_parts = parse_url($url);

    $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : '';
    $host = isset($url_parts['host']) ? $url_parts['host'] : '';

    if (isset($url_parts['path'])) {
        $url_parts['path'] = preg_replace('#/page/\d+/#', '/', $url_parts['path']);
    }

    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_array);

        if (isset($query_array['product-page'])) {
            unset($query_array['product-page']);
        }

        $url_parts['query'] = http_build_query($query_array);
    }

    // Rebuild the URL
    $new_url = $scheme . $host;
  

    if (isset($url_parts['path'])) {
        $new_url .= rtrim($url_parts['path'], '/');
    }

    if (!empty($url_parts['query'])) {
        $new_url .= '?' . $url_parts['query'];
    }

    return $new_url;
}


public function register_custom_filter_widget() {
    register_widget( 'Custom_WooCommerce_Product_Filter_Widget' );
}

public function add_container_html(){
   //Added custom container for the archive page
   echo '<div class="container"><div class="customShopwrap">';
}

}

// Define the widget class
class Custom_WooCommerce_Product_Filter_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'custom_woocommerce_product_filter_widget', // Base ID
            'Custom WooCommerce Product Filter',        // Name
            array( 'description' => __( 'A widget to display WooCommerce product filters', 'custom-woocommerce-product-filter' ), )
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        
        // Display the filter form
        $custom_filter = new Custom_WooCommerce_Product_Filter();
        $custom_filter->display_filter_form();

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        // Outputs the options form in the admin
        echo '<p>' . __( 'This widget displays the WooCommerce product filter form.', 'custom-woocommerce-product-filter' ) . '</p>';
    }

    public function update( $new_instance, $old_instance ) {
        // Processes widget options to be saved
        $instance = array();
        return $instance;
    }
}

// Initialize the main plugin class
new Custom_WooCommerce_Product_Filter();




