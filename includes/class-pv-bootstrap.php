<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_Bootstrap
{
    public static function activate(): void
    {
        if (!class_exists('PV_Config')) {
            require_once PV_PLUGIN_PATH . 'includes/class-pv-config.php';
        }

        if (!get_option('pv_settings')) {
            add_option('pv_settings', [
                'usage_mode' => 'both',
                'whatsapp_number' => '',
                'default_message' => __('Hi Cart2Chat, I want to place this order:', PV_TEXT_DOMAIN),
                'closing_message' => __('Thank you. I am available to confirm.', PV_TEXT_DOMAIN),
                'design_style' => 'modern',
                'design_theme' => 'light',
                'button_color' => '#25d366',
                'button_text_color' => '#ffffff',
                'show_whatsapp_icon' => true,
                'product_button_text' => __('Order via WhatsApp', PV_TEXT_DOMAIN),
                'checkout_button_text' => __('Send order via WhatsApp', PV_TEXT_DOMAIN),
            ]);
        }

        if (!get_option('pv_catalog_config')) {
            add_option('pv_catalog_config', PV_Config::get_default_catalog());
        }
    }

    public static function init(): void
    {
        load_plugin_textdomain(PV_TEXT_DOMAIN, false, dirname(plugin_basename(PV_PLUGIN_FILE)) . '/languages');

        require_once PV_PLUGIN_PATH . 'includes/class-pv-config.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-product-fields.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-checkout-flow.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-order-control.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-whatsapp-gateway.php';

        PV_Product_Fields::init();
        PV_Checkout_Flow::init();
        PV_Order_Control::init();
        PV_WhatsApp_Gateway_Manager::init();

        add_action('admin_notices', [__CLASS__, 'maybe_show_woocommerce_notice']);
    }

    public static function maybe_show_woocommerce_notice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        if (class_exists('WooCommerce')) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Cart2Chat:', PV_TEXT_DOMAIN) . '</strong> '
            . esc_html__('requires WooCommerce to work properly.', PV_TEXT_DOMAIN)
            . '</p></div>';
    }
}
