<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_Product_Fields
{
    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('woocommerce_product_data_tabs', [__CLASS__, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [__CLASS__, 'render_product_panel']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_product_fields']);
    }

    public static function get_product_types(): array
    {
        return PV_Config::get_product_types();
    }

    public static function get_fields_for_type(string $type): array
    {
        return PV_Config::get_fields_for_type($type);
    }

    public static function add_product_tab(array $tabs): array
    {
        $tabs['cart2chat'] = [
            'label'    => 'Cart2Chat',
            'target'   => 'cart2chat_product_data',
            'priority' => 80,
        ];

        return $tabs;
    }

    public static function render_product_panel(): void
    {
        global $post;

        $enabled = get_post_meta($post->ID, '_pv_enable_customization', true);
        $type = get_post_meta($post->ID, '_pv_product_type', true);
        if (!$type) {
            $first_type = array_key_first(self::get_product_types());
            $type = $first_type ?: '';
        }

        echo '<div id="cart2chat_product_data" class="panel woocommerce_options_panel">';

        woocommerce_wp_checkbox([
            'id'          => '_pv_enable_customization',
            'label'       => __('Enable personalized purchase', PV_TEXT_DOMAIN),
            'description' => __('Show customization fields on the product page.', PV_TEXT_DOMAIN),
            'value'       => $enabled,
        ]);

        woocommerce_wp_select([
            'id'          => '_pv_product_type',
            'label'       => __('Product type', PV_TEXT_DOMAIN),
            'description' => __('Define which fields will be requested from the customer.', PV_TEXT_DOMAIN),
            'options'     => self::get_product_types(),
            'value'       => $type,
        ]);

        echo '<p class="form-field"><strong>' . esc_html__('Fields visible to the customer:', PV_TEXT_DOMAIN) . '</strong></p>';
        $fields = self::get_fields_for_type($type);
        echo '<ul style="padding-left:20px;">';
        foreach ($fields as $field) {
            $required = !empty($field['required']) ? ' (' . __('required', PV_TEXT_DOMAIN) . ')' : '';
            echo '<li>' . esc_html($field['label'] . $required) . '</li>';
        }
        echo '</ul>';

        echo '</div>';
    }

    public static function save_product_fields(WC_Product $product): void
    {
        $enabled = isset($_POST['_pv_enable_customization']) ? 'yes' : 'no';
        $first_type = array_key_first(self::get_product_types());
        $type = isset($_POST['_pv_product_type']) ? sanitize_text_field(wp_unslash($_POST['_pv_product_type'])) : (string) $first_type;

        if (!array_key_exists($type, self::get_product_types())) {
            $type = (string) $first_type;
        }

        $product->update_meta_data('_pv_enable_customization', $enabled);
        $product->update_meta_data('_pv_product_type', $type);
    }
}
