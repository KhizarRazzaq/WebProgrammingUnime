jQuery(document).ready(function(jQuery) {
    jQuery('#subscribe-button').click(function() {
        var productId = jQuery(this).data('product-id');
        var ajaxurl = my_ajax_object.ajax_url; // Get the AJAX URL


        jQuery.ajax({
            url: ajaxurl, // Use the fetched URL
            type: 'POST',
            data: {
                action: 'my_process_subscription',
                product_id: productId
            },
            success: function(response) {
                // Handle successful subscription (e.g., display a message)
                alert('Subscription successful!');
            },
            error: function(error) {
                // Handle errors
                alert('Already Subscribed');
            }
        });
    });
});
