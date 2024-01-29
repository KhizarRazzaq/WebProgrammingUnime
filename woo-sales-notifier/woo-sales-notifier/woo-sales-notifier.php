<?php

/**
 * Plugin Name: Woo Sales Notifier
 * Description: Plugin that notifies users when the price of a selected item drops 
 * Version: 1.0
 * Author: Khizar
 * License: GPL-2.0+
 * Domain Path: /languages
 * Text Domain: woo-sales-notifier
 */



if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}



// Enqueue your script
function my_enqueue_scripts()
{
    // Check for existing jQuery first
    if (!wp_script_is('jquery', 'registered')) {
        // Register the latest jQuery from the Google CDN
        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js', array(), '3.6.4', true);
    }

    // Enqueue your custom script, ensuring jQuery is loaded first
    wp_enqueue_script('my-custom-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), '1.0', true);
    // Register the script
    wp_register_script('my-custom-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), '1.0', true);

    // Localize the script
    $script_data_array = array(
        'ajax_url' => admin_url('admin-ajax.php'),
    );
    wp_localize_script('my-custom-script', 'my_ajax_object', $script_data_array);

    // Enqueue the script
    wp_enqueue_script('my-custom-script');
}

add_action('wp_enqueue_scripts', 'my_enqueue_scripts');



register_activation_hook(__FILE__, 'my_create_subscriptions_table');

function my_create_subscriptions_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'my_price_drop_subscriptions';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id int(11) NOT NULL AUTO_INCREMENT,
      user_id int(11) NOT NULL,
      product_id int(11) NOT NULL,
      regular_price int(11) NOT NULL,
      sale_price int(11) NOT NULL,
      email varchar(255) NOT NULL,
      created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY unique_subscription (user_id, product_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}





add_action('woocommerce_single_product_summary', 'my_add_subscribe_button', 35);

function my_add_subscribe_button()
{
    global $product;

    $product_id = $product->get_id();

    // Check if user is logged in
    if (is_user_logged_in()) {
        // Display subscription button
        echo '<button id="subscribe-button"  data-product-id="' . $product_id . '">Subscribe for Price Alerts</button>';
    } else {
        // Display link to register/login page
        echo '<a href="' . wp_login_url(get_permalink()) . '">Log in or Register to Subscribe</a>';
    }
}


// PHP function to handle subscription processing
add_action('wp_ajax_my_process_subscription', 'my_process_subscription');
add_action('wp_ajax_nopriv_my_process_subscription', 'my_process_subscription'); // Allow non-logged-in users to access the AJAX function

function my_process_subscription()
{
    $product_id = $_POST['product_id'];
    $user_id = get_current_user_id(); // Get user ID for logged-in users
    // Get user email from the users table
    global $wpdb;
    $table_name = $wpdb->prefix . 'users';
    $email = $wpdb->get_var($wpdb->prepare("SELECT user_email FROM $table_name WHERE ID = %d", $user_id));

    my_save_subscription($user_id, $product_id, $email); // Call the function to save subscription data

    wp_send_json_success('Subscription successful!');
}


// Function triggered by button click or subscription form submission
function my_save_subscription($user_id, $product_id, $email)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'my_price_drop_subscriptions';

    // Get the product object
    $product = wc_get_product($product_id);

    // Retrieve regular and sale prices
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();  // Adjust accordingly if using custom sale logic

    // Add prices to the data array
    $data = array(
        'user_id' => $user_id,
        'product_id' => $product_id,
        'email' => $email,
        'regular_price' => $regular_price,
        'sale_price' => $sale_price,  // Only include if relevant
    );

    $wpdb->insert($table_name, $data);

    // ... Handle successful subscription logic
}



add_action('woocommerce_update_product', 'check_for_price_drop', 10, 2);

function check_for_price_drop($product, $data_store)
{

    $regular_price = get_post_meta($product, '_regular_price', true);
    $sale_price = get_post_meta($product, '_sale_price', true);
    error_log('Sale Price ' . $sale_price); // Add this line
    if ($sale_price != null && $sale_price > 0) {
        $price = $sale_price;
    } else {
        $price = $regular_price;
    }



    // Access saved data from the database using product ID
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_price_drop_subscriptions';

    $all_subscriptions = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = $product");

    foreach ($all_subscriptions as $subscription) {
        // Get product details based on subscription data
        $old_price = $subscription->regular_price;
        if ($old_price > $price) {
            $user = $subscription->user_id;
            error_log('Function completed for' . $user); // Add this line

            $counter_value = get_option("counter_value" . $user, 0);

            if ($counter_value == 1) {
                $product_name = get_the_title($product);

                // Prepare email content with product information and price drop details
                $email_content = "... Compose your email message using product details and price comparison...";


                $subject = "On Sale Update";


                // ob_start();
                $message = "Price Drop Alert for " . $product_name. "<br>Price is now: " . $price. "<br>Purchase it before it gets out of stock visit our website https://pdadesigns.com/";

                $headers = array('Content-Type: text/html; charset=UTF-8');

                wp_mail($subscription->email, $subject, $message, $headers);


                // Send email to the subscriber's email address
                // wp_mail(
                // $subscription->email,
                // "Price Drop Alert for " . $product_name,
                // $email_content
                // );
                error_log('Email' . $email_content);
                $counter_value = 0;
                update_option("counter_value" . $user, $counter_value);
            } else {
                $counter_value = 1;
                update_option("counter_value" . $user, $counter_value);
            }

        }


    }

}
