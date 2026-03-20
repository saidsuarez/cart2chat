<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_Bootstrap
{
    public static function activate(): void
    {
        if (!get_option('pv_settings')) {
            add_option('pv_settings', self::get_activation_settings_defaults());
        }

        if (!get_option('pv_catalog_config')) {
            add_option('pv_catalog_config', self::get_activation_catalog_defaults());
        }
    }

    public static function init(): void
    {
        require_once PV_PLUGIN_PATH . 'includes/class-pv-config.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-product-fields.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-checkout-flow.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-order-control.php';
        require_once PV_PLUGIN_PATH . 'includes/class-pv-whatsapp-gateway.php';

        add_action('init', [__CLASS__, 'load_textdomain'], 1);
        add_action('init', [__CLASS__, 'maybe_migrate_legacy_defaults'], 20);

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

    public static function load_textdomain(): void
    {
        load_plugin_textdomain(PV_TEXT_DOMAIN, false, dirname(plugin_basename(PV_PLUGIN_FILE)) . '/languages');
    }

    public static function maybe_migrate_legacy_defaults(): void
    {
        $settings = get_option('pv_settings', []);
        if (is_array($settings) && self::looks_like_legacy_default_settings($settings)) {
            update_option('pv_settings', PV_Order_Control::get_default_settings());
            $settings = get_option('pv_settings', []);
        }
        self::maybe_localize_unmodified_defaults($settings);

        $catalog = get_option('pv_catalog_config', []);
        if (is_array($catalog) && self::looks_like_legacy_default_catalog($catalog)) {
            $new_catalog = PV_Config::get_default_catalog();
            if (isset($catalog['payment_methods']) && is_array($catalog['payment_methods'])) {
                $new_catalog['payment_methods'] = $catalog['payment_methods'];
            }
            update_option('pv_catalog_config', $new_catalog);
        }
    }

    private static function looks_like_legacy_default_settings(array $settings): bool
    {
        $keys = [];
        if (!empty($settings['whatsapp_form_fields']) && is_array($settings['whatsapp_form_fields'])) {
            foreach ($settings['whatsapp_form_fields'] as $field) {
                if (is_array($field) && isset($field['key'])) {
                    $keys[] = (string) $field['key'];
                }
            }
        }

        $legacy_keys = ['name', 'whatsapp', 'email', 'city', 'department', 'address', 'shipping_notes', 'payment_method'];
        if ($keys !== $legacy_keys) {
            return false;
        }

        $usage_mode = isset($settings['usage_mode']) ? (string) $settings['usage_mode'] : 'both';
        $whatsapp_number = isset($settings['whatsapp_number']) ? preg_replace('/[^0-9]/', '', (string) $settings['whatsapp_number']) : '';
        $design_style = isset($settings['design_style']) ? (string) $settings['design_style'] : 'modern';
        $design_theme = isset($settings['design_theme']) ? (string) $settings['design_theme'] : 'light';
        $button_color = strtolower((string) ($settings['button_color'] ?? '#25d366'));
        $button_text_color = strtolower((string) ($settings['button_text_color'] ?? '#ffffff'));
        $show_icon = array_key_exists('show_whatsapp_icon', $settings) ? !empty($settings['show_whatsapp_icon']) : true;

        return $usage_mode === 'both'
            && $whatsapp_number === ''
            && $design_style === 'modern'
            && $design_theme === 'light'
            && $button_color === '#25d366'
            && $button_text_color === '#ffffff'
            && $show_icon === true;
    }

    private static function looks_like_legacy_default_catalog(array $catalog): bool
    {
        $types = isset($catalog['product_types']) && is_array($catalog['product_types']) ? $catalog['product_types'] : [];
        $fields = isset($catalog['product_fields']) && is_array($catalog['product_fields']) ? $catalog['product_fields'] : [];

        $legacy_type_keys = [
            'registro_veterinario',
            'bitacora_lectura',
            'poster',
            'bolso_personalizado',
            'camiseta_personalizada',
            'boton',
            'pin',
        ];

        return array_values(array_keys($types)) === $legacy_type_keys
            && !empty($fields)
            && isset($fields['registro_veterinario']);
    }

    private static function maybe_localize_unmodified_defaults($settings): void
    {
        if (!is_array($settings)) {
            return;
        }

        $target_spanish = self::is_spanish_locale();
        $target = self::build_settings_defaults($target_spanish);
        $english = self::build_settings_defaults(false);
        $spanish = self::build_settings_defaults(true);

        if ($target_spanish && self::is_same_settings_payload($settings, $english)) {
            update_option('pv_settings', $target);
            return;
        }

        if (!$target_spanish && self::is_same_settings_payload($settings, $spanish)) {
            update_option('pv_settings', $target);
        }
    }

    private static function is_same_settings_payload(array $a, array $b): bool
    {
        $keys = [
            'usage_mode',
            'whatsapp_number',
            'default_message',
            'closing_message',
            'design_style',
            'design_theme',
            'button_color',
            'button_text_color',
            'show_whatsapp_icon',
            'product_button_text',
            'checkout_button_text',
            'custom_css',
            'whatsapp_form_fields',
        ];

        foreach ($keys as $key) {
            $av = $a[$key] ?? null;
            $bv = $b[$key] ?? null;
            if ($av !== $bv) {
                return false;
            }
        }
        return true;
    }

    private static function get_activation_settings_defaults(): array
    {
        return self::build_settings_defaults(self::is_spanish_locale());
    }

    private static function build_settings_defaults(bool $is_spanish): array
    {
        return [
            'usage_mode' => 'both',
            'whatsapp_number' => '',
            'default_message' => $is_spanish
                ? 'Hola Cart2Chat, quiero realizar este pedido:'
                : 'Hi Cart2Chat, I want to place this order:',
            'closing_message' => $is_spanish
                ? 'Gracias. Quedo atento para confirmar.'
                : 'Thank you. I am available to confirm.',
            'design_style' => 'modern',
            'design_theme' => 'light',
            'button_color' => '#25d366',
            'button_text_color' => '#ffffff',
            'show_whatsapp_icon' => true,
            'product_button_text' => $is_spanish ? 'Hacer pedido por WhatsApp' : 'Order via WhatsApp',
            'checkout_button_text' => $is_spanish ? 'Enviar pedido por WhatsApp' : 'Send order via WhatsApp',
            'custom_css' => '',
            'whatsapp_form_fields' => [
                ['key' => 'name', 'label' => $is_spanish ? 'Nombre completo' : 'Full name', 'type' => 'text', 'required' => true],
                ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
            ],
        ];
    }

    private static function get_activation_catalog_defaults(): array
    {
        $is_spanish = self::is_spanish_locale();

        return [
            'product_types' => [],
            'product_fields' => [],
            'payment_methods' => [
                'transferencia_bancolombia' => $is_spanish ? 'Transferencia bancaria (Bancolombia)' : 'Bank transfer (Bancolombia)',
                'llave'                     => 'Llave',
                'nequi'                     => 'Nequi',
                'daviplata'                 => 'Daviplata',
                'link_pago_tarjeta'         => $is_spanish ? 'Link de pago (tarjeta de crédito)' : 'Payment link (credit card)',
            ],
        ];
    }

    private static function is_spanish_locale(): bool
    {
        $locale = (string) get_locale();
        if ($locale === '' && function_exists('determine_locale')) {
            $locale = (string) determine_locale();
        }
        $locale = strtolower($locale);
        return strpos($locale, 'es_') === 0 || $locale === 'es';
    }
}
