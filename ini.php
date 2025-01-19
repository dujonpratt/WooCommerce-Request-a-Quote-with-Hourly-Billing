<?php
/**
 * Plugin Name: WooCommerce Request a Quote with Hourly Billing
 * Description: Adds an option to WooCommerce products to allow billing by hourly rates and integrates it into a request-a-quote system.
 * Version: 1.0.0
 * Author: Dujon Pratt
 * Text Domain: woocommerce-hourly-quote
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add custom checkbox for hourly rate in WooCommerce product settings
add_action('woocommerce_product_options_general_product_data', 'add_hourly_rate_checkbox');
function add_hourly_rate_checkbox() {
    woocommerce_wp_checkbox([
        'id'            => '_is_hourly_rate',
        'label'         => __('Hourly Rate ($)', 'woocommerce'),
        'description'   => __('Enable if this product is billed hourly.', 'woocommerce'),
    ]);
}

// Save the custom checkbox value
add_action('woocommerce_process_product_meta', 'save_hourly_rate_checkbox');
function save_hourly_rate_checkbox($post_id) {
    $is_hourly_rate = isset($_POST['_is_hourly_rate']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_hourly_rate', $is_hourly_rate);
}

// Add custom field for hourly rate in WooCommerce product settings
add_action('woocommerce_product_options_pricing', 'add_hourly_rate_field');
function add_hourly_rate_field() {
    echo '<div class="options_group show_if_hourly_rate">';
    woocommerce_wp_text_input([
        'id'          => '_hourly_rate',
        'label'       => __('Hourly Rate ($)', 'woocommerce'),
        'desc_tip'    => 'true',
        'description' => __('Enter the hourly rate for this product.', 'woocommerce'),
        'type'        => 'number',
        'custom_attributes' => [
            'step' => '0.01',
            'min'  => '0',
        ],
    ]);
    echo '</div>';

    // Add inline JavaScript for toggling fields
    ?>
     <script>
    jQuery(document).ready(function ($) {
        function toggleHourlyRateFields() {
            const isHourly = $('#_is_hourly_rate').is(':checked');
            $('.show_if_hourly_rate').toggle(isHourly);

            // Correctly target Regular Price and Sale Price fields
            $('._regular_price_field').closest('.form-field').toggle(!isHourly);
            $('._sale_price_field').closest('.form-field').toggle(!isHourly);
        }

        // Initialize on page load
        toggleHourlyRateFields();

        // Update on checkbox change
        $('#_is_hourly_rate').on('change', function () {
            toggleHourlyRateFields();
        });
    });
    </script>
    <?php
}

// Save the custom field value
add_action('woocommerce_process_product_meta', 'save_hourly_rate_field');
function save_hourly_rate_field($post_id) {
    $hourly_rate = isset($_POST['_hourly_rate']) ? sanitize_text_field($_POST['_hourly_rate']) : '';
    update_post_meta($post_id, '_hourly_rate', $hourly_rate);
}

// Replace regular price with hourly rate on frontend
add_filter('woocommerce_get_price_html', 'replace_price_with_hourly_rate', 10, 2);
function replace_price_with_hourly_rate($price, $product) {
    $is_hourly_rate = get_post_meta($product->get_id(), '_is_hourly_rate', true);
    $hourly_rate = get_post_meta($product->get_id(), '_hourly_rate', true);

    if ($is_hourly_rate === 'yes' && $hourly_rate) {
        return wc_price($hourly_rate) . ' <span class="woocommerce-price-suffix">' . __('per hour', 'woocommerce') . '</span>';
    }

    return $price;
}

// Replace Add to Cart button with Request a Quote button for hourly products
add_filter('woocommerce_is_purchasable', 'make_hourly_products_not_purchasable', 10, 2);
function make_hourly_products_not_purchasable($purchasable, $product) {
    $is_hourly_rate = get_post_meta($product->get_id(), '_is_hourly_rate', true);
    if ($is_hourly_rate === 'yes') {
        return false;
    }
    return $purchasable;
}



// Display hourly rate option on the product page
add_action('woocommerce_before_add_to_cart_button', 'add_hourly_rate_input');
function add_hourly_rate_input() {
    global $product;
    
    $is_hourly_rate = get_post_meta($product->get_id(), '_is_hourly_rate', true);
    $hourly_rate = get_post_meta($product->get_id(), '_hourly_rate', true);
    if ($is_hourly_rate === 'yes' && $hourly_rate) {
        echo '<div class="hourly-rate-field">';
        echo '<label for="hourly_hours">' . __('Enter Hours:', 'woocommerce') . '</label>';
        echo '<input type="number" id="hourly_hours" name="hourly_hours" min="1" step="1" value="1" />';
        echo '<input type="hidden" name="hourly_rate" value="' . esc_attr($hourly_rate) . '" />';
        echo '</div>';
    }
}

// Add hourly rate data to the cart
add_filter('woocommerce_add_cart_item_data', 'add_hourly_rate_to_cart', 10, 2);
function add_hourly_rate_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['hourly_hours']) && isset($_POST['hourly_rate'])) {
        $cart_item_data['hourly_hours'] = intval($_POST['hourly_hours']);
        $cart_item_data['hourly_rate']  = floatval($_POST['hourly_rate']);
        $cart_item_data['unique_key']   = md5(microtime() . rand());
    }
    return $cart_item_data;
}

// Update the cart item price based on hourly rate
add_action('woocommerce_before_calculate_totals', 'update_cart_item_price');
function update_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['hourly_hours']) && isset($cart_item['hourly_rate'])) {
            $new_price = $cart_item['hourly_hours'] * $cart_item['hourly_rate'];
            $cart_item['data']->set_price($new_price);
        }
    }
}

// Add settings page to the admin menu
add_action('admin_menu', 'add_request_quote_settings_page');
function add_request_quote_settings_page() {
    add_menu_page(
        __('Request a Quote Settings', 'woocommerce'),
        __('Quote Settings', 'woocommerce'),
        'manage_options',
        'request-quote-settings',
        'render_request_quote_settings_page',
        'dashicons-admin-generic',
        56
    );
}

// Render settings page with tabs
function render_request_quote_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

    echo '<div class="wrap">';
    echo '<h1>' . __('Request a Quote Settings', 'woocommerce') . '</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="?page=request-quote-settings&tab=settings" class="nav-tab ' . ($active_tab === 'settings' ? 'nav-tab-active' : '') . '">' . __('Settings', 'woocommerce') . '</a>';
    echo '<a href="?page=request-quote-settings&tab=fields" class="nav-tab ' . ($active_tab === 'fields' ? 'nav-tab-active' : '') . '">' . __('Fields', 'woocommerce') . '</a>';
    echo '</h2>';

    if ($active_tab === 'settings') {
        render_request_quote_settings_tab();
    } elseif ($active_tab === 'fields') {
        render_request_quote_fields_tab();
    }

    echo '</div>';
}

// Render the Fields tab
function render_request_quote_fields_tab() {
    if (isset($_POST['submit_fields'])) {
        $fields = [];
        if (isset($_POST['fields'])) {
            foreach ($_POST['fields'] as $field) {
                $field['required'] = isset($field['required']) ? true : false;
                $fields[] = $field;
            }
        }
        update_option('request_quote_fields', json_encode($fields));
    }

    $fields = json_decode(get_option('request_quote_fields', '[]'), true);

    echo '<form method="post" action="" enctype="multipart/form-data">';
    echo '<table class="form-table">';
    echo '<tr valign="top">';
    echo '<th scope="row">' . __('Add Field', 'woocommerce') . '</th>';
    echo '<td><button type="button" id="add-field" class="button">' . __('Add Field', 'woocommerce') . '</button></td>';
    echo '</tr>';
    echo '</table>';

    echo '<div id="fields-container">';
    foreach ($fields as $index => $field) {
        echo '<div class="field-row">';
        echo '<label>' . __('Field Name', 'woocommerce') . ': <input type="text" name="fields[' . $index . '][name]" value="' . esc_attr($field['name']) . '" /></label>'; 
        echo '<label>' . __('Field Type', 'woocommerce') . ': <select class="field-type-selector" name="fields[' . $index . '][type]">';
        echo '<option value="text" ' . selected($field['type'], 'text', false) . '>Text</option>';
        echo '<option value="number" ' . selected($field['type'], 'number', false) . '>Number</option>';
        echo '<option value="textarea" ' . selected($field['type'], 'textarea', false) . '>Textarea</option>';
        echo '<option value="checkbox" ' . selected($field['type'], 'checkbox', false) . '>Checkbox</option>';
        echo '<option value="radio" ' . selected($field['type'], 'radio', false) . '>Radio</option>';
        echo '<option value="select" ' . selected($field['type'], 'select', false) . '>Select</option>';
        echo '<option value="file" ' . selected($field['type'], 'file', false) . '>File</option>';
        echo '<option value="email" ' . selected($field['type'], 'email', false) . '>Email</option>';
        echo '</select></label>'; 
        echo '<label class="field-options-label" ' . ($field['type'] === 'select' || $field['type'] === 'radio' || $field['type'] === 'checkbox' ? '' : 'style="display:none;"') . '>' . __('Options (comma-separated)', 'woocommerce') . ': <input type="text" name="fields[' . $index . '][options]" value="' . esc_attr($field['options'] ?? '') . '" /></label>'; 
        echo '<label>' . __('Required', 'woocommerce') . ': <input type="checkbox" name="fields[' . $index . '][required]" ' . checked(isset($field['required']) && $field['required'], true, false) . ' /></label>'; 
        echo '<button type="button" class="remove-field button">' . __('Remove', 'woocommerce') . '</button>'; 
        echo '</div>';
    }
    echo '</div>';

    echo '<p class="submit"><input type="submit" name="submit_fields" id="submit_fields" class="button button-primary" value="' . __('Save Fields', 'woocommerce') . '" /></p>';
    echo '</form>';

    ?>
    <script>
    jQuery(document).ready(function ($) {
        let fieldIndex = <?php echo count($fields); ?>;

        $('#add-field').on('click', function () {
            $('#fields-container').append('<div class="field-row">' +
                '<label>Field Name: <input type="text" name="fields[' + fieldIndex + '][name]" /></label>' +
                '<label>Field Type: <select class="field-type-selector" name="fields[' + fieldIndex + '][type]">' +
                '<option value="text">Text</option>' +
                '<option value="number">Number</option>' +
                '<option value="textarea">Textarea</option>' +
                '<option value="checkbox">Checkbox</option>' +
                '<option value="radio">Radio</option>' +
                '<option value="select">Select</option>' +
                '<option value="file">File</option>' +
                '<option value="email">Email</option>' +
                '</select></label>' +
                '<label class="field-options-label" style="display:none;">Options (comma-separated): <input type="text" name="fields[' + fieldIndex + '][options]" /></label>' +
                '<label>Required: <input type="checkbox" name="fields[' + fieldIndex + '][required]" /></label>' +
                '<button type="button" class="remove-field button">Remove</button>' +
                '</div>');
            fieldIndex++;
        });

        $('#fields-container').on('change', '.field-type-selector', function () {
            const fieldType = $(this).val();
            const optionsField = $(this).closest('.field-row').find('.field-options-label');
            if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
                optionsField.show();
            } else {
                optionsField.hide();
            }
        });

        $('#fields-container').on('click', '.remove-field', function () {
            $(this).closest('.field-row').remove();
        });
    });
    </script>
    <?php
}

// Add custom fields above the Request a Quote button on the product page
add_action('woocommerce_single_product_summary', 'add_custom_fields_above_request_quote', 25);
function add_custom_fields_above_request_quote() {
    $fields = json_decode(get_option('request_quote_fields', '[]'), true);

    echo '<form id="request-quote-fields" method="post">';

    foreach ($fields as $field) {
        $is_required = isset($field['required']) && $field['required'] ? 'required' : '';
        echo '<div class="request-quote-field">';
        echo '<label>' . esc_html($field['name']) . '</label>';

        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'number':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($field['name']) . '" ' . $is_required . ' />';
                break;
            case 'textarea':
                echo '<textarea name="' . esc_attr($field['name']) . '" ' . $is_required . '></textarea>';
                break;
            case 'checkbox':
                $options = explode(',', $field['options'] ?? '');
                foreach ($options as $option) {
                    echo '<label><input type="checkbox" name="' . esc_attr($field['name']) . '[]" value="' . esc_attr(trim($option)) . '">' . esc_html(trim($option)) . '</label>';
                }
                break;
            case 'radio':
                $options = explode(',', $field['options'] ?? '');
                foreach ($options as $option) {
                    echo '<label><input type="radio" name="' . esc_attr($field['name']) . '" value="' . esc_attr(trim($option)) . '">' . esc_html(trim($option)) . '</label>';
                }
                break;
            case 'select':
                $options = explode(',', $field['options'] ?? '');
                echo '<select name="' . esc_attr($field['name']) . '" ' . $is_required . '>';
                foreach ($options as $option) {
                    echo '<option value="' . esc_attr(trim($option)) . '">' . esc_html(trim($option)) . '</option>';
                }
                echo '</select>';
                break;
            case 'file':
                echo '<input type="file" name="' . esc_attr($field['name']) . '" ' . $is_required . ' />';
                break;
        }

        echo '</div>';
    }

    // Field for hours
    echo '<div class="request-quote-field">';
    echo '<label>' . __('Number of Hours', 'woocommerce') . '</label>';
    echo '<input type="number" name="hours" min="1" step="1" value="3" required />';
    echo '</div>';

    // Field for custom note
    echo '<div class="request-quote-field">';
    echo '<label>' . __('Custom Note', 'woocommerce') . '</label>';
    echo '<textarea name="custom_note" placeholder="Enter custom note here">3-hour inspection</textarea>';
    echo '</div>';

    echo '</form>';

}

// Add the Request a Quote button
add_action('woocommerce_single_product_summary', 'add_request_quote_button', 30);
function add_request_quote_button() {
    global $product;
    $is_hourly_rate = get_post_meta($product->get_id(), '_is_hourly_rate', true);

    if ($is_hourly_rate === 'yes') {
        echo '<button class="button request-quote-button" data-product-id="' . esc_attr($product->get_id()) . '">' . __('Request a Quote', 'woocommerce') . '</button>';
?>
<style>
    .cart {
        display: none;
    }
</style>
<?php

    }
}

// Handle the custom AJAX for adding the product with custom data
add_action('wp_footer', 'request_quote_ajax_script');
function request_quote_ajax_script() {
    if (is_product()) {
        ?>
        <script>
jQuery(document).ready(function ($) {
    $('.request-quote-button').on('click', function (e) {
        e.preventDefault();

        var form = $('#request-quote-fields');
        var formData = form.serializeArray(); // Serialize form fields into an array
        var productId = $(this).data('product-id'); // Get the product ID

        // Append the product ID to the serialized form data
        formData.push({ name: 'product_id', value: productId });

        $.ajax({
            url: woocommerce_params.ajax_url,
            type: 'POST',
            data: {
                action: 'add_to_cart_with_custom_data',
                form_data: formData,
            },
            success: function (response) {
                if (response.success) {
                    window.location.href = "<?php echo esc_url(wc_get_checkout_url()); ?>"; // Redirect to checkout
                } else {
                    alert(response.data.message); // Show error message
                }
            },
        });
    });
});
</script>

        <?php
    }
}

// Handle the AJAX request to add the product to the cart
// Add the product to the cart with custom data
add_action('wp_ajax_add_to_cart_with_custom_data', 'add_to_cart_with_custom_data');
add_action('wp_ajax_nopriv_add_to_cart_with_custom_data', 'add_to_cart_with_custom_data');

function add_to_cart_with_custom_data() {
    if (!isset($_POST['form_data']) || !is_array($_POST['form_data'])) {
        wp_send_json_error(['message' => 'Invalid data.']);
    }

    // Parse the form data into an associative array
    $data = [];
    foreach ($_POST['form_data'] as $item) {
        $data[$item['name']] = $item['value'];
    }

    // Validate Product ID
    $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
    if (!$product_id || !wc_get_product($product_id)) {
        wp_send_json_error(['message' => 'Invalid Product ID.']);
    }

    // Prepare cart item data
    $cart_item_data = [];
    foreach ($data as $key => $value) {
        if ($key !== 'product_id') {
            $cart_item_data['custom_' . sanitize_key($key)] = sanitize_text_field($value);
        }
    }

    // Set quantity based on hours
    $quantity = isset($data['hours']) ? intval($data['hours']) : 1;

    // Add the product to the cart
    $added = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);

    if ($added) {
        wp_send_json_success(['cart_url' => wc_get_checkout_url()]);
    } else {
        wp_send_json_error(['message' => 'Failed to add product to cart.']);
    }
}


add_filter('woocommerce_get_item_data', 'display_custom_cart_item_data', 10, 2);

function display_custom_cart_item_data($item_data, $cart_item) {
    foreach ($cart_item as $key => $value) {
        if (strpos($key, 'custom_') !== false) {
            $item_data[] = [
                'name' => wc_clean(str_replace('custom_', '', $key)),
                'value' => wc_clean($value),
            ];
        }
    }
    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'save_custom_cart_item_data_to_order', 10, 4);
function save_custom_cart_item_data_to_order($item, $cart_item_key, $values, $order) {
    foreach ($values as $cart_item_key => $cart_item_value) {
        if (strpos($cart_item_key, 'custom_') !== false) {
            $item->add_meta_data(str_replace('custom_', '', $cart_item_key), $cart_item_value);
        }
    }
}

add_filter('woocommerce_order_item_meta', 'display_custom_data_in_order_meta', 10, 2);
function display_custom_data_in_order_meta($item_id, $item) {
    $order_item_meta = $item->get_meta_data();

    foreach ($order_item_meta as $meta) {
        $key = $meta->key;
        $value = $meta->value;

        if (!empty($key) && !empty($value)) {
            echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</p>';
        }
    }
}

// Ensure the quantity reflects the entered hours
add_action('woocommerce_before_calculate_totals', 'calculate_quantity_and_disable_cart_quantity');
function calculate_quantity_and_disable_cart_quantity($cart) {
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['custom_hours'])) {
            $cart_item['quantity'] = 1; // Ensure the quantity is always 1
            $hourly_rate = get_post_meta($cart_item['product_id'], '_hourly_rate', true);
            if ($hourly_rate) {
                $cart_item['data']->set_price($hourly_rate * $cart_item['custom_hours']);
            }
        }
    }
}

// Update the cart item's quantity based on the custom hours field
add_action('woocommerce_before_calculate_totals', 'update_cart_quantity_based_on_hours');
function update_cart_quantity_based_on_hours($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['custom_hours'])) {
            $hours = intval($cart_item['custom_hours']);
            if ($hours > 0) {
                $cart_item['quantity'] = $hours; // Update the cart quantity to match hours
                $hourly_rate = get_post_meta($cart_item['product_id'], '_hourly_rate', true);
                if ($hourly_rate) {
                    $cart_item['data']->set_price($hourly_rate);
                }
            }
        }
    }
}

// Disable cart quantity input for hourly billed products by adding "disabled" attribute
add_filter('woocommerce_cart_item_quantity', 'disable_quantity_input_for_hourly_products', 10, 3);
function disable_quantity_input_for_hourly_products($product_quantity, $cart_item_key, $cart_item) {
    $is_hourly_rate = get_post_meta($cart_item['product_id'], '_is_hourly_rate', true);

    if ($is_hourly_rate === 'yes') {
        // Generate a disabled input field showing the current quantity
        return '<input type="number" name="cart[' . esc_attr($cart_item_key) . '][qty]" value="' . esc_attr($cart_item['quantity']) . '" disabled />';
    }

    return $product_quantity; // Default behavior for non-hourly products
}

add_filter('woocommerce_is_purchasable', 'make_hourly_products_purchasable', 10, 2);
function make_hourly_products_purchasable($purchasable, $product) {
    $is_hourly_rate = get_post_meta($product->get_id(), '_is_hourly_rate', true);

    // Allow hourly rate products to be purchasable
    if ($is_hourly_rate === 'yes') {
        return true;
    }

    return $purchasable;
}

add_filter('woocommerce_product_get_price', 'set_hourly_product_price', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'set_hourly_product_price', 10, 2);

function set_hourly_product_price($price, $product) {
    $is_hourly_rate = get_post_meta($product->get_id(), '_is_hourly_rate', true);
    $hourly_rate = get_post_meta($product->get_id(), '_hourly_rate', true);

    // Return the hourly rate as the price if applicable
    if ($is_hourly_rate === 'yes' && $hourly_rate) {
        return $hourly_rate;
    }

    return $price;
}

add_action('woocommerce_before_calculate_totals', 'validate_hourly_product_price');
function validate_hourly_product_price($cart) {
    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $is_hourly_rate = get_post_meta($product_id, '_is_hourly_rate', true);
        $hourly_rate = get_post_meta($product_id, '_hourly_rate', true);

        if ($is_hourly_rate === 'yes' && (!$hourly_rate || $hourly_rate <= 0)) {
            wc_add_notice(__('Hourly rate must be set and greater than 0 for hourly products.', 'woocommerce'), 'error');
        }
    }
}


?>
