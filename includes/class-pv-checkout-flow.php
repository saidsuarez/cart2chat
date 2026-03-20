<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_Checkout_Flow
{
    private static $script_printed = false;
    private static $whatsapp_script_printed = false;

    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_before_add_to_cart_form', [__CLASS__, 'ensure_multipart_form']);
        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_customization_fields']);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_customization_fields'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'store_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'store_order_item_meta'], 10, 4);
        add_action('woocommerce_after_add_to_cart_button', [__CLASS__, 'render_product_whatsapp_block']);
        add_action('woocommerce_after_add_to_cart_form', [__CLASS__, 'render_product_whatsapp_panel']);
        add_action('woocommerce_after_cart', [__CLASS__, 'render_cart_whatsapp_block']);
        add_action('wp_head', [__CLASS__, 'render_design_css']);
        add_action('wp_ajax_pv_send_whatsapp_order', [__CLASS__, 'handle_whatsapp_order_ajax']);
        add_action('wp_ajax_nopriv_pv_send_whatsapp_order', [__CLASS__, 'handle_whatsapp_order_ajax']);
    }

    public static function ensure_multipart_form(): void
    {
        echo '<script>(function(){var form=document.querySelector("form.cart");if(form){form.setAttribute("enctype","multipart/form-data");}})();</script>';
    }

    public static function render_customization_fields(): void
    {
        global $product;

        if (!$product instanceof WC_Product) {
            return;
        }

        $product_id = $product->get_id();
        $enabled = get_post_meta($product_id, '_pv_enable_customization', true);
        if ($enabled !== 'yes') {
            return;
        }

        $type = self::resolve_product_type($product_id);
        $fields = PV_Product_Fields::get_fields_for_type($type);
        $posted_mode = isset($_POST['pv_personalization_mode']) ? sanitize_text_field((string) wp_unslash($_POST['pv_personalization_mode'])) : 'same';
        $posted_mode = $posted_mode === 'individual' ? 'individual' : 'same';
        $posted_values = self::get_posted_raw_values();

        echo '<div class="pv-customization-fields">';
        echo '<h3 class="pv-customization-title">' . esc_html__('Customize your product', PV_TEXT_DOMAIN) . '</h3>';

        echo '<div id="pv-mode-wrap" class="pv-mode-wrap">';
        echo '<strong class="pv-mode-title">' . esc_html__('How do you want to customize multiple units?', PV_TEXT_DOMAIN) . '</strong>';
        echo '<label class="pv-mode-option"><input type="radio" name="pv_personalization_mode" value="same" ' . checked($posted_mode, 'same', false) . '> ' . esc_html__('All products the same (single form)', PV_TEXT_DOMAIN) . '</label>';
        echo '<label class="pv-mode-option"><input type="radio" name="pv_personalization_mode" value="individual" ' . checked($posted_mode, 'individual', false) . '> ' . esc_html__('Customize each product separately', PV_TEXT_DOMAIN) . '</label>';
        echo '</div>';

        echo '<div id="pv-shared-fields">';
        self::render_fields_group($fields, 'pv_', '');
        echo '</div>';

        echo '<div id="pv-individual-fields" class="pv-individual-fields"></div>';
        echo '<input type="hidden" id="pv_item_count" name="pv_item_count" value="1" />';
        echo '</div>';

        self::render_frontend_script($fields, $posted_values);
    }

    public static function validate_customization_fields(bool $passed, int $product_id, int $quantity): bool
    {
        $enabled = get_post_meta($product_id, '_pv_enable_customization', true);
        if ($enabled !== 'yes') {
            return $passed;
        }

        $type = self::resolve_product_type($product_id);
        $fields = PV_Product_Fields::get_fields_for_type($type);
        $mode = self::resolve_mode($quantity);

        if ($mode === 'individual') {
            for ($i = 1; $i <= $quantity; $i++) {
                $prefix = 'pv_i' . $i . '_';
                $posted = self::get_posted_values_for_prefix($fields, $prefix);
                if (!self::validate_fields_for_prefix($fields, $posted, $prefix, sprintf(__('Product #%d', PV_TEXT_DOMAIN), $i))) {
                    return false;
                }
            }

            return $passed;
        }

        $posted_values = self::get_posted_values_for_prefix($fields, 'pv_');
        if (!self::validate_fields_for_prefix($fields, $posted_values, 'pv_')) {
            return false;
        }

        return $passed;
    }

    public static function store_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array
    {
        $enabled = get_post_meta($product_id, '_pv_enable_customization', true);
        if ($enabled !== 'yes') {
            return $cart_item_data;
        }

        $type = self::resolve_product_type($product_id);
        $fields = PV_Product_Fields::get_fields_for_type($type);
        $quantity = self::get_requested_quantity();
        $mode = self::resolve_mode($quantity);

        if ($mode === 'individual' && $quantity > 1) {
            $items = [];
            for ($i = 1; $i <= $quantity; $i++) {
                $prefix = 'pv_i' . $i . '_';
                $posted = self::get_posted_values_for_prefix($fields, $prefix);
                $item_data = self::collect_customization_data($fields, $posted, $prefix);
                if (!empty($item_data)) {
                    $items[$i] = $item_data;
                }
            }

            if (!empty($items)) {
                $cart_item_data['pv_customization_mode'] = 'individual';
                $cart_item_data['pv_customization_items'] = $items;
                $cart_item_data['pv_personalization_qty'] = $quantity;
                $cart_item_data['pv_product_type'] = $type;
                $cart_item_data['pv_unique_key'] = md5(wp_json_encode($items) . microtime());
            }

            return $cart_item_data;
        }

        $posted_values = self::get_posted_values_for_prefix($fields, 'pv_');
        $data = self::collect_customization_data($fields, $posted_values, 'pv_');

        if (!empty($data)) {
            $cart_item_data['pv_customization'] = $data;
            $cart_item_data['pv_customization_mode'] = 'same';
            $cart_item_data['pv_personalization_qty'] = max(1, $quantity);
            $cart_item_data['pv_product_type'] = $type;
            $cart_item_data['pv_unique_key'] = md5(wp_json_encode($data) . microtime());
        }

        return $cart_item_data;
    }

    public static function display_cart_item_data(array $item_data, array $cart_item): array
    {
        $mode = $cart_item['pv_customization_mode'] ?? 'same';

        if ($mode === 'individual' && !empty($cart_item['pv_customization_items']) && is_array($cart_item['pv_customization_items'])) {
            foreach ($cart_item['pv_customization_items'] as $index => $fields) {
                foreach ((array) $fields as $label => $value) {
                    $name = __('Product #', PV_TEXT_DOMAIN) . (int) $index . ' - ' . $label;
                    if (is_array($value) && !empty($value['type']) && $value['type'] === 'file' && !empty($value['url'])) {
                        $item_data[] = [
                            'name'    => $name,
                            'value'   => sprintf(__('File: %s', PV_TEXT_DOMAIN), sanitize_text_field((string) ($value['name'] ?? 'image'))),
                            'display' => '<a href="' . esc_url((string) $value['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View image', PV_TEXT_DOMAIN) . '</a>',
                        ];
                        continue;
                    }

                    $item_data[] = [
                        'name'  => $name,
                        'value' => (string) $value,
                    ];
                }
            }

            return $item_data;
        }

        if (empty($cart_item['pv_customization']) || !is_array($cart_item['pv_customization'])) {
            return $item_data;
        }

        if (!empty($cart_item['pv_personalization_qty']) && (int) $cart_item['pv_personalization_qty'] > 1) {
            $item_data[] = [
                'name'  => __('Personalization mode', PV_TEXT_DOMAIN),
                'value' => __('All equal for', PV_TEXT_DOMAIN) . ' ' . (int) $cart_item['pv_personalization_qty'] . ' ' . __('units', PV_TEXT_DOMAIN),
            ];
        }

        foreach ($cart_item['pv_customization'] as $label => $value) {
            if (is_array($value) && !empty($value['type']) && $value['type'] === 'file' && !empty($value['url'])) {
                $item_data[] = [
                    'name'    => $label,
                    'value'   => sprintf(__('File: %s', PV_TEXT_DOMAIN), sanitize_text_field((string) ($value['name'] ?? 'image'))),
                    'display' => '<a href="' . esc_url((string) $value['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View image', PV_TEXT_DOMAIN) . '</a>',
                ];
                continue;
            }

            $item_data[] = [
                'name'  => $label,
                'value' => (string) $value,
            ];
        }

        return $item_data;
    }

    public static function store_order_item_meta(WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order): void
    {
        $mode = $values['pv_customization_mode'] ?? 'same';

        if ($mode === 'individual' && !empty($values['pv_customization_items']) && is_array($values['pv_customization_items'])) {
            foreach ($values['pv_customization_items'] as $index => $fields) {
                foreach ((array) $fields as $label => $value) {
                    $meta_label = __('Product #', PV_TEXT_DOMAIN) . (int) $index . ' - ' . $label;
                    if (is_array($value) && !empty($value['type']) && $value['type'] === 'file' && !empty($value['url'])) {
                        $item->add_meta_data($meta_label, esc_url_raw((string) $value['url']), true);
                        continue;
                    }

                    $item->add_meta_data($meta_label, (string) $value, true);
                }
            }

            $item->add_meta_data('_pv_customization_mode', 'individual', true);
        }

        if (!empty($values['pv_customization']) && is_array($values['pv_customization'])) {
            foreach ($values['pv_customization'] as $label => $value) {
                if (is_array($value) && !empty($value['type']) && $value['type'] === 'file' && !empty($value['url'])) {
                    $item->add_meta_data($label, esc_url_raw((string) $value['url']), true);
                    continue;
                }

                $item->add_meta_data($label, (string) $value, true);
            }

            $item->add_meta_data('_pv_customization_mode', 'same', true);
            if (!empty($values['pv_personalization_qty']) && (int) $values['pv_personalization_qty'] > 1) {
                $item->add_meta_data('_pv_personalization_qty', (int) $values['pv_personalization_qty'], true);
            }
        }

        if (!empty($values['pv_product_type'])) {
            $item->add_meta_data('_pv_product_type', sanitize_text_field((string) $values['pv_product_type']), true);
        }
    }

    public static function render_product_whatsapp_block(): void
    {
        if (!self::is_product_flow_enabled()) {
            return;
        }

        global $product;

        if (!$product instanceof WC_Product) {
            return;
        }

        $product_button_html = self::get_whatsapp_button_content_html(self::get_product_button_text());
        echo '<div class="pv-wa-inline" id="pv-wa-single">';
        echo '<button type="button" class="button alt pv-wa-btn" data-pv-wa-toggle data-pv-panel-target="#pv-wa-single-panel">' . $product_button_html . '</button>';
        echo '</div>';

        self::render_whatsapp_script();
    }

    public static function render_product_whatsapp_panel(): void
    {
        if (!self::is_product_flow_enabled()) {
            return;
        }

        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        $product_id = $product->get_id();
        $product_button_text = self::get_product_button_text();

        echo '<div class="pv-wa-panel pv-wa-order pv-wa-order-single" id="pv-wa-single-panel" data-pv-wa-panel>';
        echo '<input type="hidden" data-pv-wa-product-id value="' . esc_attr((string) $product_id) . '">';
        echo '<h3 class="pv-wa-order-title">' . esc_html($product_button_text) . '</h3>';
        self::render_whatsapp_customer_fields();
        echo '<button type="button" class="button alt pv-wa-btn pv-wa-submit" data-pv-wa-submit>' . esc_html__('Send order via WhatsApp', PV_TEXT_DOMAIN) . '</button>';
        echo '<div class="pv-wa-errors" data-pv-wa-errors></div>';
        echo '</div>';
    }

    public static function render_cart_whatsapp_block(): void
    {
        if (!apply_filters('pv_enable_legacy_cart_whatsapp', false)) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        echo '<div class="pv-wa-order pv-wa-order-cart" id="pv-wa-cart">';
        echo '<h3 class="pv-wa-order-title">' . esc_html__('WhatsApp order', PV_TEXT_DOMAIN) . '</h3>';
        echo '<p class="pv-wa-order-help">' . esc_html__('Send your full cart summary with shipping details and payment method.', PV_TEXT_DOMAIN) . '</p>';
        self::render_whatsapp_customer_fields();
        echo '<button type="button" class="button alt pv-wa-btn pv-wa-submit" data-pv-wa-submit>' . esc_html__('Send cart via WhatsApp', PV_TEXT_DOMAIN) . '</button>';
        echo '<div class="pv-wa-errors" data-pv-wa-errors></div>';
        echo '</div>';

        self::render_whatsapp_script();
    }

    public static function handle_whatsapp_order_ajax(): void
    {
        check_ajax_referer('pv_whatsapp_order', 'security');

        $context = isset($_POST['pv_wa_context']) ? sanitize_text_field((string) wp_unslash($_POST['pv_wa_context'])) : '';
        $customer = self::sanitize_whatsapp_customer_data($_POST);
        $errors = self::validate_whatsapp_customer_data($customer);

        if (!empty($errors)) {
            wp_send_json_error(['messages' => $errors], 422);
        }

        if ($context === 'single') {
            $result = self::build_single_product_whatsapp_message($customer);
        } elseif ($context === 'cart') {
            $result = self::build_cart_whatsapp_message($customer);
        } else {
            wp_send_json_error(['messages' => [__('Invalid order context.', PV_TEXT_DOMAIN)]], 422);
            return;
        }

        if (!$result['success']) {
            wp_send_json_error(['messages' => $result['errors']], 422);
            return;
        }

        $url = self::build_whatsapp_url($result['message']);
        wp_send_json_success(['url' => $url]);
    }

    private static function render_whatsapp_customer_fields(): void
    {
        $fields = self::get_whatsapp_form_fields();
        echo '<div class="pv-wa-grid">';
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $type = (string) ($field['type'] ?? 'text');
            $required = !empty($field['required']);
            $star = $required ? ' *' : '';
            $field_id = 'pv-wa-field-' . sanitize_html_class($key);

            echo '<div class="pv-wa-field">';
            echo '<label class="pv-wa-label" for="' . esc_attr($field_id) . '">' . esc_html($label . $star) . '</label>';

            if ($type === 'textarea') {
                echo '<textarea id="' . esc_attr($field_id) . '" data-pv-wa-field="' . esc_attr($key) . '" rows="2"></textarea>';
            } elseif ($type === 'select') {
                echo '<select id="' . esc_attr($field_id) . '" data-pv-wa-field="' . esc_attr($key) . '">';
                echo '<option value="">' . esc_html__('Select an option', PV_TEXT_DOMAIN) . '</option>';
                foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) {
                    if (is_int($option_value)) {
                        $option_value = (string) $option_label;
                    }
                    echo '<option value="' . esc_attr((string) $option_value) . '">' . esc_html((string) $option_label) . '</option>';
                }
                echo '</select>';
            } else {
                $input_type = in_array($type, ['text', 'email', 'tel'], true) ? $type : 'text';
                echo '<input id="' . esc_attr($field_id) . '" type="' . esc_attr($input_type) . '" data-pv-wa-field="' . esc_attr($key) . '">';
            }

            echo '</div>';
        }
        echo '</div>';
    }

    public static function get_payment_options(): array
    {
        $fields = self::get_whatsapp_form_fields();
        foreach ($fields as $field) {
            if (($field['key'] ?? '') === 'payment_method' && ($field['type'] ?? '') === 'select') {
                return (array) ($field['options'] ?? []);
            }
        }

        return [];
    }

    private static function get_whatsapp_form_fields(): array
    {
        $settings = get_option('pv_settings', []);
        $fields = isset($settings['whatsapp_form_fields']) && is_array($settings['whatsapp_form_fields']) ? $settings['whatsapp_form_fields'] : [];
        if (!empty($fields)) {
            return $fields;
        }

        return [
            ['key' => 'name', 'label' => __('Full name', PV_TEXT_DOMAIN), 'type' => 'text', 'required' => true],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
        ];
    }

    private static function sanitize_whatsapp_customer_data(array $source): array
    {
        $data = [];
        foreach (self::get_whatsapp_form_fields() as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            if ($key === '') {
                continue;
            }

            $request_key = 'pv_wa_' . $key;
            $raw = isset($source[$request_key]) ? (string) wp_unslash($source[$request_key]) : '';

            if ($type === 'textarea') {
                $data[$key] = sanitize_textarea_field($raw);
            } elseif ($type === 'email') {
                $data[$key] = sanitize_email($raw);
            } else {
                $data[$key] = sanitize_text_field($raw);
            }
        }

        return $data;
    }

    private static function validate_whatsapp_customer_data(array $customer): array
    {
        $errors = [];

        foreach (self::get_whatsapp_form_fields() as $field) {
            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $required = !empty($field['required']);
            $type = (string) ($field['type'] ?? 'text');
            $value = isset($customer[$key]) ? (string) $customer[$key] : '';

            if ($required && $value === '') {
                $errors[] = sprintf(__('The field "%s" is required.', PV_TEXT_DOMAIN), $label);
                continue;
            }

            if ($type === 'select' && $value !== '') {
                $options = (array) ($field['options'] ?? []);
                if (!array_key_exists($value, $options) && !in_array($value, $options, true)) {
                    $errors[] = sprintf(__('Select a valid option for "%s".', PV_TEXT_DOMAIN), $label);
                }
            }
        }

        return $errors;
    }

    private static function build_single_product_whatsapp_message(array $customer): array
    {
        $product_id = isset($_POST['pv_product_id']) ? absint(wp_unslash($_POST['pv_product_id'])) : 0;
        if ($product_id <= 0 && isset($_POST['add-to-cart'])) {
            $product_id = absint(wp_unslash($_POST['add-to-cart']));
        }
        if ($product_id <= 0 && isset($_POST['product_id'])) {
            $product_id = absint(wp_unslash($_POST['product_id']));
        }
        if ($product_id <= 0) {
            return ['success' => false, 'errors' => [__('Could not identify the product.', PV_TEXT_DOMAIN)], 'message' => ''];
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return ['success' => false, 'errors' => [__('Invalid product.', PV_TEXT_DOMAIN)], 'message' => ''];
        }

        $quantity = self::get_requested_quantity();
        $type = self::resolve_product_type($product_id);
        $enabled = get_post_meta($product_id, '_pv_enable_customization', true);
        $fields = PV_Product_Fields::get_fields_for_type($type);
        $mode = self::resolve_mode($quantity);
        $errors = [];
        $items = [];
        $shared = [];

        if ($enabled === 'yes') {
            if ($mode === 'individual' && $quantity > 1) {
                for ($i = 1; $i <= $quantity; $i++) {
                    $prefix = 'pv_i' . $i . '_';
                    $posted = self::get_posted_values_for_prefix($fields, $prefix);
                    if (!self::validate_fields_for_prefix($fields, $posted, $prefix, sprintf(__('Product #%d', PV_TEXT_DOMAIN), $i))) {
                        $errors[] = sprintf(__('Complete required customization fields for Product #%d.', PV_TEXT_DOMAIN), $i);
                        continue;
                    }
                    $items[$i] = self::collect_customization_data($fields, $posted, $prefix);
                }
            } else {
                $posted = self::get_posted_values_for_prefix($fields, 'pv_');
                if (!self::validate_fields_for_prefix($fields, $posted, 'pv_')) {
                    $errors[] = __('Complete required customization fields.', PV_TEXT_DOMAIN);
                } else {
                    $shared = self::collect_customization_data($fields, $posted, 'pv_');
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => ''];
        }

        $lines = self::build_customer_and_shipping_lines($customer);
        $lines[] = '';
        $lines[] = '*' . __('Order from product page', PV_TEXT_DOMAIN) . '*';
        $lines[] = __('Product', PV_TEXT_DOMAIN) . ': ' . $product->get_name();
        $lines[] = __('Quantity', PV_TEXT_DOMAIN) . ': ' . $quantity;

        if ($enabled === 'yes') {
            if ($mode === 'individual' && !empty($items)) {
                $lines[] = __('Mode', PV_TEXT_DOMAIN) . ': ' . __('Individual customization', PV_TEXT_DOMAIN);
                foreach ($items as $index => $data) {
                    $lines[] = '';
                    $lines[] = __('Product #', PV_TEXT_DOMAIN) . $index . ':';
                    $lines = array_merge($lines, self::format_customization_lines($data));
                }
            } elseif (!empty($shared)) {
                $lines[] = __('Mode', PV_TEXT_DOMAIN) . ': ' . __('All equal', PV_TEXT_DOMAIN);
                $lines = array_merge($lines, self::format_customization_lines($shared));
            }
        }

        $closing = self::get_closing_message();
        if ($closing !== '') {
            $lines[] = '';
            $lines[] = $closing;
        }

        return ['success' => true, 'errors' => [], 'message' => implode("\n", $lines)];
    }

    private static function build_cart_whatsapp_message(array $customer): array
    {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return ['success' => false, 'errors' => [__('The cart is empty.', PV_TEXT_DOMAIN)], 'message' => ''];
        }

        $lines = self::build_customer_and_shipping_lines($customer);
        $lines[] = '';
        $lines[] = '*' . __('Order from cart', PV_TEXT_DOMAIN) . '*';

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_name = isset($cart_item['data']) && $cart_item['data'] instanceof WC_Product ? $cart_item['data']->get_name() : __('Product', PV_TEXT_DOMAIN);
            $qty = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;
            $lines[] = '';
            $lines[] = '- ' . $product_name . ' x ' . $qty;

            $mode = $cart_item['pv_customization_mode'] ?? 'same';
            if ($mode === 'individual' && !empty($cart_item['pv_customization_items'])) {
                foreach ((array) $cart_item['pv_customization_items'] as $index => $data) {
                    $lines[] = '  ' . __('Product #', PV_TEXT_DOMAIN) . $index . ':';
                    foreach (self::format_customization_lines((array) $data, '    ') as $line) {
                        $lines[] = $line;
                    }
                }
            } elseif (!empty($cart_item['pv_customization'])) {
                foreach (self::format_customization_lines((array) $cart_item['pv_customization'], '  ') as $line) {
                    $lines[] = $line;
                }
            }
        }

        $lines[] = '';
        $lines[] = __('Cart total', PV_TEXT_DOMAIN) . ': ' . wp_strip_all_tags(WC()->cart->get_cart_total());

        $closing = self::get_closing_message();
        if ($closing !== '') {
            $lines[] = '';
            $lines[] = $closing;
        }

        return ['success' => true, 'errors' => [], 'message' => implode("\n", $lines)];
    }

    private static function build_customer_and_shipping_lines(array $customer): array
    {
        $lines = [];
        $lines[] = self::get_default_message();
        $lines[] = '';
        $lines[] = '*' . __('Customer and shipping details', PV_TEXT_DOMAIN) . '*';

        foreach (self::get_whatsapp_form_fields() as $field) {
            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $value = isset($customer[$key]) ? (string) $customer[$key] : '';

            if ($value === '') {
                continue;
            }

            if (($field['type'] ?? '') === 'select') {
                $options = (array) ($field['options'] ?? []);
                if (array_key_exists($value, $options)) {
                    $value = (string) $options[$value];
                }
            }

            $lines[] = $label . ': ' . $value;
        }

        return $lines;
    }

    private static function format_customization_lines(array $data, string $indent = ''): array
    {
        $lines = [];
        foreach ($data as $label => $value) {
            if (is_array($value) && !empty($value['type']) && $value['type'] === 'file' && !empty($value['url'])) {
                $lines[] = $indent . $label . ': ' . __('Image uploaded in the order', PV_TEXT_DOMAIN);
            } else {
                $lines[] = $indent . $label . ': ' . (string) $value;
            }
        }

        return $lines;
    }

    public static function get_default_message(): string
    {
        $settings = get_option('pv_settings', []);
        $message = isset($settings['default_message']) ? sanitize_text_field((string) $settings['default_message']) : '';
        if ($message === '') {
            return __('Hi Cart2Chat, I want to place this order:', PV_TEXT_DOMAIN);
        }

        return $message;
    }

    public static function get_closing_message(): string
    {
        $settings = get_option('pv_settings', []);
        return isset($settings['closing_message']) ? sanitize_text_field((string) $settings['closing_message']) : '';
    }

    public static function get_product_button_text(): string
    {
        $settings = get_option('pv_settings', []);
        $text = isset($settings['product_button_text']) ? sanitize_text_field((string) $settings['product_button_text']) : '';
        if ($text === '') {
            return __('Order via WhatsApp', PV_TEXT_DOMAIN);
        }

        return $text;
    }

    public static function get_checkout_button_text(): string
    {
        $settings = get_option('pv_settings', []);
        $text = isset($settings['checkout_button_text']) ? sanitize_text_field((string) $settings['checkout_button_text']) : '';
        if ($text === '') {
            return __('Send order via WhatsApp', PV_TEXT_DOMAIN);
        }

        return $text;
    }

    public static function should_show_whatsapp_icon(): bool
    {
        $settings = get_option('pv_settings', []);
        return !empty($settings['show_whatsapp_icon']);
    }

    public static function get_whatsapp_button_content_html(string $text): string
    {
        $html = '<span class="pv-wa-btn-content">';
        if (self::should_show_whatsapp_icon()) {
            $html .= '<img class="pv-wa-btn-icon-img" src="' . esc_url(PV_PLUGIN_URL . 'assets/icons/whatsapp-icon.svg') . '" alt="" aria-hidden="true" />';
        }
        $html .= '<span class="pv-wa-btn-text">' . esc_html($text) . '</span>';
        $html .= '</span>';

        return $html;
    }

    private static function build_whatsapp_url(string $message): string
    {
        $settings = get_option('pv_settings', []);
        $number = isset($settings['whatsapp_number']) ? preg_replace('/[^0-9]/', '', (string) $settings['whatsapp_number']) : '';
        $message = str_replace("\n", "\r\n", $message);
        $encoded = rawurlencode($message);

        if ($number === '') {
            return 'https://wa.me/?text=' . $encoded;
        }

        return 'https://wa.me/' . $number . '?text=' . $encoded;
    }

    public static function render_design_css(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = get_option('pv_settings', []);
        $design_style = isset($settings['design_style']) ? sanitize_key((string) $settings['design_style']) : 'modern';
        if (!in_array($design_style, ['flat', 'modern'], true)) {
            $design_style = 'modern';
        }

        $design_theme = isset($settings['design_theme']) ? sanitize_key((string) $settings['design_theme']) : 'light';
        if (!in_array($design_theme, ['light', 'dark'], true)) {
            $design_theme = 'light';
        }

        $button_color = sanitize_hex_color((string) ($settings['button_color'] ?? '#25d366'));
        if (!$button_color) {
            $button_color = '#25d366';
        }
        $button_text_color = sanitize_hex_color((string) ($settings['button_text_color'] ?? '#ffffff'));
        if (!$button_text_color) {
            $button_text_color = '#ffffff';
        }
        $custom_css = isset($settings['custom_css']) ? trim((string) $settings['custom_css']) : '';

        $radius = $design_style === 'flat' ? '0px' : '12px';
        $btn_bg = $button_color;
        $btn_border = $button_color;
        $btn_text = $button_text_color;

        if ($design_theme === 'dark') {
            $container_bg = '#111827';
            $container_border = '#374151';
            $text_color = '#f9fafb';
            $muted_color = '#d1d5db';
            $field_bg = '#1f2937';
            $field_border = '#4b5563';
            $field_text = '#f9fafb';
        } else {
            $container_bg = '#ffffff';
            $container_border = '#d1d5db';
            $text_color = '#111827';
            $muted_color = '#4b5563';
            $field_bg = '#ffffff';
            $field_border = '#cbd5e1';
            $field_text = '#111827';
        }

        echo '<style id="pv-design-css">'
            . '.pv-customization-fields,.pv-wa-order,#pv-mode-wrap{border-radius:' . esc_attr($radius) . ';background:' . esc_attr($container_bg) . ';border-color:' . esc_attr($container_border) . ';color:' . esc_attr($text_color) . ';}'
            . '.pv-customization-fields{margin:20px 0;padding:16px;border:1px solid ' . esc_attr($container_border) . ';width:100%;flex:0 0 100%;}'
            . '.pv-customization-title{margin:0 0 12px;}'
            . '.pv-mode-wrap{display:none;margin-bottom:14px;padding:10px;border:1px dashed ' . esc_attr($container_border) . ';}'
            . '.pv-mode-title{display:block;margin-bottom:8px;}'
            . '.pv-mode-option{display:block;margin-bottom:6px;}'
            . '.pv-individual-fields{display:none;}'
            . '.pv-group-title{margin:10px 0;}'
            . '.pv-field-group{margin-bottom:10px;}'
            . '.pv-field-label{display:block;font-weight:600;margin-bottom:4px;}'
            . '.pv-field-help{display:block;color:' . esc_attr($muted_color) . ';margin-top:4px;}'
            . '.pv-item-section{margin-bottom:16px;padding:12px;border:1px solid ' . esc_attr($container_border) . ';border-radius:' . esc_attr($radius) . ';}'
            . '.pv-item-section-title{margin:0 0 10px;}'
            . '.pv-wa-order p,.pv-wa-order label,.pv-customization-fields p,.pv-customization-fields label,.pv-wa-order h2,.pv-wa-order h3,.pv-customization-fields h3{color:' . esc_attr($text_color) . ';}'
            . '.pv-wa-order .description,.pv-customization-fields .description{color:' . esc_attr($muted_color) . ';}'
            . '.pv-wa-order-single{margin-top:14px;padding:12px;border:1px solid ' . esc_attr($container_border) . ';}'
            . '.pv-wa-order-cart{margin-top:18px;padding:14px;border:1px solid ' . esc_attr($container_border) . ';}'
            . '.pv-wa-order-title{margin:0;}'
            . '.pv-wa-order-help{margin:0 0 10px;}'
            . '.pv-wa-panel{display:none;flex-direction:column;gap:12px;margin-top:10px;}'
            . '.woocommerce.single-product div.product form.cart .pv-wa-inline{display:inline-block;vertical-align:middle;margin-left:8px;}'
            . '.woocommerce.single-product div.product .pv-wa-order-single{margin-top:12px;min-width:320px;max-width:560px;}'
            . '.pv-wa-order .pv-wa-submit{display:inline-block;margin-top:0 !important;}'
            . '.pv-wa-errors{margin-top:8px;color:#b11;display:none;}'
            . '.pv-wa-payment-option{margin-top:10px;}'
            . '.pv-wa-payment-option select{width:100%;}'
            . '.woocommerce.single-product div.product form.cart .quantity input.qty{width:5rem;min-width:5rem;text-align:center;}'
            . '.woocommerce.single-product div.product form.cart:not(.grouped_form):not(.variations_form){display:flex;flex-wrap:wrap;align-items:flex-start;column-gap:12px;row-gap:12px;}'
            . ':is(.elementor-widget-woocommerce-product-add-to-cart,.woocommerce div.product .elementor-widget-woocommerce-product-add-to-cart,.elementor-widget-wc-add-to-cart,.woocommerce div.product .elementor-widget-wc-add-to-cart) form.cart.variations_form .woocommerce-variation-add-to-cart,:is(.elementor-widget-woocommerce-product-add-to-cart,.woocommerce div.product .elementor-widget-woocommerce-product-add-to-cart,.elementor-widget-wc-add-to-cart,.woocommerce div.product .elementor-widget-wc-add-to-cart) form.cart:not(.grouped_form):not(.variations_form){display:flex;flex-wrap:wrap;align-items:flex-start;column-gap:12px;row-gap:12px;}'
            . '.pv-wa-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}'
            . '.pv-wa-field{margin:0;}'
            . '.pv-wa-field .pv-wa-label,.pv-customization-fields label{display:block;margin-bottom:6px;}'
            . '.pv-wa-order .pv-wa-field input,.pv-wa-order .pv-wa-field select,.pv-wa-order .pv-wa-field textarea,.pv-customization-fields input,.pv-customization-fields select,.pv-customization-fields textarea{width:100%;border-radius:' . esc_attr($radius) . ';background:' . esc_attr($field_bg) . ';border:1px solid ' . esc_attr($field_border) . ';color:' . esc_attr($field_text) . ';}'
            . '.pv-wa-order .pv-wa-field input[type="checkbox"],.pv-wa-order .pv-wa-field input[type="radio"],.pv-customization-fields input[type="checkbox"],.pv-customization-fields input[type="radio"]{width:auto;min-width:0;}'
            . '.woocommerce .button.pv-wa-btn,.woocommerce button.pv-wa-btn,.pv-wa-btn{background-color:' . esc_attr($btn_bg) . ' !important;border-color:' . esc_attr($btn_border) . ' !important;color:' . esc_attr($btn_text) . ' !important;}'
            . '.pv-wa-order .pv-wa-btn:hover,.pv-wa-btn:hover{opacity:.92;}'
            . '.pv-wa-btn .pv-wa-btn-content{display:inline-flex;align-items:center;gap:.45em;}'
            . '.pv-wa-btn .pv-wa-btn-icon-img{display:block;width:1em;height:1em;flex:0 0 1em;}'
            . '@media (max-width: 768px){.pv-wa-grid{grid-template-columns:1fr;}.woocommerce.single-product div.product form.cart .pv-wa-inline{display:block;width:100%;margin-left:0;margin-top:10px;}.woocommerce.single-product div.product form.cart .pv-wa-inline .pv-wa-btn{display:block;width:100%;}}'
            . $custom_css
            . '</style>';
    }

    private static function render_whatsapp_script(): void
    {
        if (self::$whatsapp_script_printed) {
            return;
        }

        self::$whatsapp_script_printed = true;
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('pv_whatsapp_order');
        $fallback_error = __('The order could not be processed.', PV_TEXT_DOMAIN);
        $connection_error = __('Connection error. Please try again.', PV_TEXT_DOMAIN);

        echo '<script>
            (function() {
                var ajaxUrl = ' . wp_json_encode($ajax_url) . ';
                var security = ' . wp_json_encode($nonce) . ';

                function attach(sectionId, context) {
                    var section = document.getElementById(sectionId);
                    if (!section) return;

                    var toggle = section.querySelector("[data-pv-wa-toggle]");
                    var panel = section.querySelector("[data-pv-wa-panel]");
                    if (!panel && toggle && toggle.getAttribute("data-pv-panel-target")) {
                        panel = document.querySelector(toggle.getAttribute("data-pv-panel-target"));
                    }
                    var dataScope = panel || section;
                    var submit = dataScope.querySelector("[data-pv-wa-submit]");
                    var errorBox = dataScope.querySelector("[data-pv-wa-errors]");

                    if (toggle) {
                        toggle.addEventListener("click", function() {
                            var targetSelector = toggle.getAttribute("data-pv-panel-target");
                            var activePanel = panel;
                            if (!activePanel && targetSelector) {
                                activePanel = document.querySelector(targetSelector);
                            }
                            if (!activePanel) return;
                            var currentDisplay = window.getComputedStyle(activePanel).display;
                            activePanel.style.display = currentDisplay === "none" ? "flex" : "none";
                        });
                    }

                    if (!submit) return;

                    submit.addEventListener("click", function() {
                        if (errorBox) {
                            errorBox.style.display = "none";
                            errorBox.innerHTML = "";
                        }

                        var data;
                        if (context === "single") {
                            var cartForm = document.querySelector("form.cart");
                            data = cartForm ? new FormData(cartForm) : new FormData();
                            var pid = dataScope.querySelector("[data-pv-wa-product-id]") || section.querySelector("[data-pv-wa-product-id]");
                            if (pid && pid.value) {
                                data.append("pv_product_id", pid.value);
                            }
                        } else {
                            data = new FormData();
                        }

                        data.append("action", "pv_send_whatsapp_order");
                        data.append("security", security);
                        data.append("pv_wa_context", context);

                        dataScope.querySelectorAll("[data-pv-wa-field]").forEach(function(field) {
                            var key = field.getAttribute("data-pv-wa-field");
                            data.append("pv_wa_" + key, field.value || "");
                        });

                        fetch(ajaxUrl, { method: "POST", body: data, credentials: "same-origin" })
                            .then(function(response) { return response.json(); })
                            .then(function(result) {
                                if (!result.success) {
                                    var messages = (result.data && result.data.messages) ? result.data.messages : [' . wp_json_encode($fallback_error) . '];
                                    if (errorBox) {
                                        errorBox.innerHTML = messages.map(function(m) { return "<div>" + m + "</div>"; }).join("");
                                        errorBox.style.display = "block";
                                    }
                                    return;
                                }

                                if (result.data && result.data.url) {
                                    window.open(result.data.url, "_blank");
                                }
                            })
                            .catch(function() {
                                if (errorBox) {
                                    errorBox.innerHTML = "<div>" + ' . wp_json_encode($connection_error) . ' + "</div>";
                                    errorBox.style.display = "block";
                                }
                            });
                    });
                }

                attach("pv-wa-single", "single");
                attach("pv-wa-single-panel", "single");
                attach("pv-wa-cart", "cart");
            })();
        </script>';
    }

    private static function get_requested_quantity(): int
    {
        $quantity = isset($_POST['quantity']) ? (int) wp_unslash($_POST['quantity']) : 1;
        return $quantity > 0 ? $quantity : 1;
    }

    private static function is_product_flow_enabled(): bool
    {
        $settings = get_option('pv_settings', []);
        $mode = isset($settings['usage_mode']) ? sanitize_key((string) $settings['usage_mode']) : 'both';
        return in_array($mode, ['product', 'both'], true);
    }

    private static function resolve_mode(int $quantity): string
    {
        if ($quantity <= 1) {
            return 'same';
        }

        $mode = isset($_POST['pv_personalization_mode']) ? sanitize_text_field((string) wp_unslash($_POST['pv_personalization_mode'])) : 'same';
        return $mode === 'individual' ? 'individual' : 'same';
    }

    private static function resolve_product_type(int $product_id): string
    {
        $type = sanitize_text_field((string) get_post_meta($product_id, '_pv_product_type', true));
        $types = PV_Product_Fields::get_product_types();
        if ($type !== '' && array_key_exists($type, $types)) {
            return $type;
        }

        $first_type = array_key_first($types);
        return is_string($first_type) ? $first_type : '';
    }

    private static function get_posted_raw_values(): array
    {
        $raw = [];
        foreach ($_POST as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (strpos($key, 'pv_') !== 0) {
                continue;
            }
            $raw[$key] = is_array($value) ? '' : (string) wp_unslash($value);
        }

        return $raw;
    }

    private static function get_posted_values_for_prefix(array $fields, string $prefix): array
    {
        $values = [];
        foreach ($fields as $field) {
            $request_key = $prefix . $field['key'];
            $values[$request_key] = isset($_POST[$request_key]) ? trim((string) wp_unslash($_POST[$request_key])) : '';
        }

        return $values;
    }

    private static function validate_fields_for_prefix(array $fields, array $posted_values, string $prefix, string $context = ''): bool
    {
        foreach ($fields as $field) {
            if (empty($field['required'])) {
                continue;
            }

            if (!self::is_field_active_for_request($field, $posted_values, $prefix)) {
                continue;
            }

            $request_key = $prefix . $field['key'];
            $field_label = $context !== '' ? $context . ' - ' . $field['label'] : $field['label'];

            if (($field['type'] ?? '') === 'file') {
                if (empty($_FILES[$request_key]['name'])) {
                    wc_add_notice(sprintf(__('The field "%s" is required to customize this product.', PV_TEXT_DOMAIN), $field_label), 'error');
                    return false;
                }

                if (!self::is_valid_image_upload($request_key)) {
                    return false;
                }

                continue;
            }

            $value = $posted_values[$request_key] ?? '';
            if ($value === '') {
                wc_add_notice(sprintf(__('The field "%s" is required to customize this product.', PV_TEXT_DOMAIN), $field_label), 'error');
                return false;
            }
        }

        return true;
    }

    private static function collect_customization_data(array $fields, array $posted_values, string $prefix): array
    {
        $data = [];

        foreach ($fields as $field) {
            if (!self::is_field_active_for_request($field, $posted_values, $prefix)) {
                continue;
            }

            $request_key = $prefix . $field['key'];

            if (($field['type'] ?? '') === 'file') {
                $file_data = self::handle_image_upload($request_key);
                if (!empty($file_data)) {
                    $data[$field['label']] = $file_data;
                }
                continue;
            }

            $raw = $posted_values[$request_key] ?? '';
            if ($raw === '') {
                continue;
            }

            if (($field['type'] ?? '') === 'select') {
                $raw = self::map_select_value_to_label($field, $raw);
            }

            $data[$field['label']] = sanitize_textarea_field($raw);
        }

        return $data;
    }

    private static function render_fields_group(array $fields, string $prefix, string $title): void
    {
        if ($title !== '') {
            echo '<h4 class="pv-group-title">' . esc_html($title) . '</h4>';
        }

        foreach ($fields as $field) {
            $input_name = $prefix . $field['key'];
            $input_id = $input_name;
            $label = esc_html($field['label']);
            $required = !empty($field['required']);
            $is_conditional = !empty($field['required_if']);
            $posted = isset($_POST[$input_name]) ? sanitize_text_field((string) wp_unslash($_POST[$input_name])) : '';

            $attr = '';
            if ($is_conditional) {
                $depends_on_key = $prefix . key($field['required_if']);
                $depends_on_value = (string) current($field['required_if']);
                $attr = ' data-pv-required-if-field="' . esc_attr($depends_on_key) . '" data-pv-required-if-value="' . esc_attr($depends_on_value) . '"';
            }

            echo '<div class="pv-field-group"' . $attr . '>';
            echo '<label class="pv-field-label" for="' . esc_attr($input_id) . '">' . $label;
            if ($required && !$is_conditional) {
                echo ' *';
            }
            if ($is_conditional) {
                echo ' (' . esc_html__('conditional', PV_TEXT_DOMAIN) . ')';
            }
            echo '</label>';

            if (($field['type'] ?? '') === 'textarea') {
                echo '<textarea id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" rows="3">' . esc_textarea($posted) . '</textarea>';
            } elseif (($field['type'] ?? '') === 'select') {
                echo '<select id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '">';
                echo '<option value="">' . esc_html__('Select an option', PV_TEXT_DOMAIN) . '</option>';
                foreach (($field['options'] ?? []) as $option_value => $option_label) {
                    if (is_int($option_value)) {
                        $option_value = (string) $option_label;
                    }
                    echo '<option value="' . esc_attr((string) $option_value) . '" ' . selected($posted, (string) $option_value, false) . '>' . esc_html((string) $option_label) . '</option>';
                }
                echo '</select>';
            } elseif (($field['type'] ?? '') === 'file') {
                echo '<input id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" type="file" accept="image/*" />';
                echo '<small class="pv-field-help">' . esc_html__('Allowed formats: JPG, PNG, WEBP.', PV_TEXT_DOMAIN) . '</small>';
            } else {
                $input_type = ($field['type'] ?? '') === 'number' ? 'number' : 'text';
                echo '<input id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" type="' . esc_attr($input_type) . '" value="' . esc_attr($posted) . '" />';
            }

            echo '</div>';
        }
    }

    private static function is_field_active_for_request(array $field, array $posted_values, string $prefix): bool
    {
        if (empty($field['required_if']) || !is_array($field['required_if'])) {
            return true;
        }

        $depends_on_field = $prefix . key($field['required_if']);
        $depends_on_value = (string) current($field['required_if']);

        return isset($posted_values[$depends_on_field]) && (string) $posted_values[$depends_on_field] === $depends_on_value;
    }

    private static function map_select_value_to_label(array $field, string $selected_value): string
    {
        if (empty($field['options']) || !is_array($field['options'])) {
            return $selected_value;
        }

        if (array_key_exists($selected_value, $field['options'])) {
            return (string) $field['options'][$selected_value];
        }

        return $selected_value;
    }

    private static function is_valid_image_upload(string $request_key): bool
    {
        if (empty($_FILES[$request_key]) || !is_array($_FILES[$request_key])) {
            return false;
        }

        $file = $_FILES[$request_key];
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK || $name === '' || $tmp_name === '') {
            wc_add_notice(__('Could not process uploaded image. Please try again.', PV_TEXT_DOMAIN), 'error');
            return false;
        }

        if ($size > 5 * 1024 * 1024) {
            wc_add_notice(__('Image exceeds maximum size (5MB).', PV_TEXT_DOMAIN), 'error');
            return false;
        }

        $check = wp_check_filetype($name, [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ]);

        if (empty($check['type'])) {
            wc_add_notice(__('Invalid image format. Use JPG, PNG, or WEBP.', PV_TEXT_DOMAIN), 'error');
            return false;
        }

        return true;
    }

    private static function handle_image_upload(string $request_key): array
    {
        if (empty($_FILES[$request_key]['name'])) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = $_FILES[$request_key];
        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
            ],
        ]);

        if (isset($upload['error'])) {
            wc_add_notice(__('Could not save image:', PV_TEXT_DOMAIN) . ' ' . sanitize_text_field((string) $upload['error']), 'error');
            return [];
        }

        return [
            'type' => 'file',
            'url'  => esc_url_raw((string) ($upload['url'] ?? '')),
            'name' => sanitize_file_name((string) ($file['name'] ?? 'image')),
        ];
    }

    private static function render_frontend_script(array $fields, array $posted_values): void
    {
        if (self::$script_printed) {
            return;
        }

        self::$script_printed = true;
        $script = <<<'JS'
(function() {
    var fields = __FIELDS__;
    var postedValues = __POSTED_VALUES__;
    var i18n = __I18N__;
    var modeWrap = document.getElementById("pv-mode-wrap");
    var sharedWrap = document.getElementById("pv-shared-fields");
    var individualWrap = document.getElementById("pv-individual-fields");
    var itemCountEl = document.getElementById("pv_item_count");
    var cartForm = document.querySelector("form.cart") || document.querySelector("form.variations_form");
    var qtyInput = null;

    if (!modeWrap || !sharedWrap || !individualWrap) {
        return;
    }

    function getQty() {
        qtyInput = findQtyInput();
        if (!qtyInput) {
            return 1;
        }
        var qty = parseInt(qtyInput.value || "1", 10);
        return qty > 0 ? qty : 1;
    }

    function getMode() {
        var checked = document.querySelector("input[name='pv_personalization_mode']:checked");
        return checked ? checked.value : "same";
    }

    function findQtyInput() {
        var form = document.querySelector("form.cart") || document.querySelector("form.variations_form") || cartForm;
        var input = (form ? form.querySelector("input.qty") : null) || document.querySelector("input.qty") || document.querySelector("input[name='quantity']");
        return input || null;
    }

    function createField(field, prefix) {
        var requestKey = prefix + field.key;
        var wrapper = document.createElement("div");
        wrapper.className = "pv-field-group";

        if (field.required_if) {
            var depKey = Object.keys(field.required_if)[0];
            wrapper.setAttribute("data-pv-required-if-field", prefix + depKey);
            wrapper.setAttribute("data-pv-required-if-value", field.required_if[depKey]);
        }

        var label = document.createElement("label");
        label.className = "pv-field-label";
        label.setAttribute("for", requestKey);
        label.textContent = field.label + ((field.required && !field.required_if) ? " *" : (field.required_if ? " (" + i18n.conditional + ")" : ""));
        wrapper.appendChild(label);

        var input;
        if (field.type === "textarea") {
            input = document.createElement("textarea");
            input.rows = 3;
            input.value = postedValues[requestKey] || "";
        } else if (field.type === "select") {
            input = document.createElement("select");
            var placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = i18n.selectOption;
            input.appendChild(placeholder);

            Object.keys(field.options || {}).forEach(function(optionKey) {
                var optionLabel = field.options[optionKey];
                var optionValue = Array.isArray(field.options) ? optionLabel : optionKey;
                var option = document.createElement("option");
                option.value = optionValue;
                option.textContent = optionLabel;
                if ((postedValues[requestKey] || "") === optionValue) {
                    option.selected = true;
                }
                input.appendChild(option);
            });
        } else if (field.type === "file") {
            input = document.createElement("input");
            input.type = "file";
            input.accept = "image/*";
        } else {
            input = document.createElement("input");
            input.type = field.type === "number" ? "number" : "text";
            input.value = postedValues[requestKey] || "";
        }

        input.name = requestKey;
        input.id = requestKey;
        wrapper.appendChild(input);

        if (field.type === "file") {
            var note = document.createElement("small");
            note.className = "pv-field-help";
            note.textContent = i18n.allowedFormats;
            wrapper.appendChild(note);
        }

        return wrapper;
    }

    function syncConditionalFields(scope) {
        var wrappers = scope.querySelectorAll(".pv-field-group[data-pv-required-if-field]");
        wrappers.forEach(function(wrapper) {
            var fieldName = wrapper.getAttribute("data-pv-required-if-field");
            var expectedValue = wrapper.getAttribute("data-pv-required-if-value");
            var trigger = scope.querySelector("[name='" + fieldName + "']");

            if (!trigger) {
                wrapper.style.display = "none";
                return;
            }

            wrapper.style.display = trigger.value === expectedValue ? "block" : "none";
        });
    }

    function renderIndividualGroups() {
        var qty = getQty();
        individualWrap.innerHTML = "";

        for (var i = 1; i <= qty; i++) {
            var section = document.createElement("div");
            section.className = "pv-item-section";
            section.setAttribute("data-pv-item-section", "1");

            var title = document.createElement("h4");
            title.className = "pv-item-section-title";
            title.textContent = i18n.productCustomizationTitle + " #" + i;
            section.appendChild(title);

            var prefix = "pv_i" + i + "_";
            fields.forEach(function(field) {
                section.appendChild(createField(field, prefix));
            });

            individualWrap.appendChild(section);
            syncConditionalFields(section);
        }
    }

    function syncMode() {
        var qty = getQty();
        var mode = getMode();

        if (itemCountEl) {
            itemCountEl.value = String(qty);
        }

        if (qty <= 1) {
            modeWrap.style.display = "none";
            var sameRadio = document.querySelector("input[name='pv_personalization_mode'][value='same']");
            if (sameRadio) {
                sameRadio.checked = true;
            }
            sharedWrap.style.display = "block";
            individualWrap.style.display = "none";
            individualWrap.innerHTML = "";
            syncConditionalFields(sharedWrap);
            return;
        }

        modeWrap.style.display = "block";
        if (mode === "individual") {
            sharedWrap.style.display = "none";
            individualWrap.style.display = "block";
            renderIndividualGroups();
        } else {
            sharedWrap.style.display = "block";
            individualWrap.style.display = "none";
            individualWrap.innerHTML = "";
            syncConditionalFields(sharedWrap);
        }
    }

    modeWrap.addEventListener("change", syncMode);
    sharedWrap.addEventListener("change", function() { syncConditionalFields(sharedWrap); });
    individualWrap.addEventListener("change", function(e) {
        var section = e.target.closest("[data-pv-item-section]");
        if (section) {
            syncConditionalFields(section);
        }
    });

    function bindQtyEvents() {
        qtyInput = findQtyInput();
        if (!qtyInput || qtyInput.dataset.pvBound === "1") {
            return false;
        }

        qtyInput.dataset.pvBound = "1";
        qtyInput.addEventListener("change", syncMode);
        qtyInput.addEventListener("input", syncMode);
        qtyInput.addEventListener("keyup", syncMode);
        return true;
    }

    document.addEventListener("click", function(e) {
        if (e.target && (e.target.classList.contains("plus") || e.target.classList.contains("minus"))) {
            setTimeout(syncMode, 0);
        }
    });

    bindQtyEvents();

    var observer = new MutationObserver(function() {
        if (bindQtyEvents()) {
            syncMode();
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    var retries = 0;
    var retryTimer = setInterval(function() {
        retries++;
        if (bindQtyEvents()) {
            syncMode();
        }
        if (retries >= 15) {
            clearInterval(retryTimer);
        }
    }, 500);

    syncConditionalFields(sharedWrap);
    syncMode();
})();
JS;

        $script = str_replace('__FIELDS__', wp_json_encode($fields), $script);
        $script = str_replace('__POSTED_VALUES__', wp_json_encode($posted_values), $script);
        $script = str_replace('__I18N__', wp_json_encode([
            'conditional' => __('conditional', PV_TEXT_DOMAIN),
            'selectOption' => __('Select an option', PV_TEXT_DOMAIN),
            'allowedFormats' => __('Allowed formats: JPG, PNG, WEBP.', PV_TEXT_DOMAIN),
            'productCustomizationTitle' => __('Product customization', PV_TEXT_DOMAIN),
        ]), $script);

        echo '<script>' . $script . '</script>';
    }
}
