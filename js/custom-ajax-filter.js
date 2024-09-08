jQuery(document).ready(function($) {

        // Show loader when Ajax request starts
        $(document).ajaxStart(function() {
            $('.ajax-loader').show();
        });
    
        // Hide loader when Ajax request completes
        $(document).ajaxStop(function() {
            $('.ajax-loader').hide();
        });
    
    // When a checkbox is clicked
    $('.woocommerce-attribute-list input[type="checkbox"]').on('change', function() {
        var filter = $('#attribute-filter-form').serialize();
        $.ajax({
            url: ajax_filter_params.ajax_url,
            data: filter + '&action=custom_filter_products',
            type: 'GET',
            success: function(data) {
                // Replace the content of the products loop with the filtered products
                $('.products').html(data);
            }
        });
    });
});
