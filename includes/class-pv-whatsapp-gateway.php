<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_WhatsApp_Gateway_Manager
{
    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('init', [__CLASS__, 'register_order_status']);
        add_filter('wc_order_statuses', [__CLASS__, 'inject_order_status']);
        add_filter('woocommerce_payment_gateways', [__CLASS__, 'register_gateway']);
        add_action('woocommerce_thankyou_pv_whatsapp', [__CLASS__, 'render_thankyou_whatsapp_block']);
        add_action('woocommerce_blocks_loaded', [__CLASS__, 'register_blocks_support']);
    }

    public static function register_order_status(): void
    {
        register_post_status('wc-pv-whatsapp', [
            'label'                     => __('WhatsApp Order', PV_TEXT_DOMAIN),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('WhatsApp Order <span class="count">(%s)</span>', 'WhatsApp Order <span class="count">(%s)</span>', PV_TEXT_DOMAIN),
        ]);
    }

    public static function inject_order_status(array $statuses): array
    {
        $new_statuses = [];
        foreach ($statuses as $key => $label) {
            $new_statuses[$key] = $label;
            if ($key === 'wc-pending') {
                $new_statuses['wc-pv-whatsapp'] = __('WhatsApp Order', PV_TEXT_DOMAIN);
            }
        }

        if (!isset($new_statuses['wc-pv-whatsapp'])) {
            $new_statuses['wc-pv-whatsapp'] = __('WhatsApp Order', PV_TEXT_DOMAIN);
        }

        return $new_statuses;
    }

    public static function register_gateway(array $gateways): array
    {
        if (class_exists('PV_WC_Gateway_WhatsApp')) {
            $gateways[] = 'PV_WC_Gateway_WhatsApp';
        }
        return $gateways;
    }

    public static function is_checkout_flow_enabled(): bool
    {
        $settings = get_option('pv_settings', []);
        $mode = isset($settings['usage_mode']) ? sanitize_key((string) $settings['usage_mode']) : 'both';
        return in_array($mode, ['checkout', 'both'], true);
    }

    public static function register_blocks_support(): void
    {
        if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
            return;
        }

        add_action('woocommerce_blocks_payment_method_type_registration', static function ($payment_method_registry): void {
            if (!is_object($payment_method_registry) || !method_exists($payment_method_registry, 'register')) {
                return;
            }
            if (class_exists('PV_WC_Gateway_WhatsApp_Blocks')) {
                $payment_method_registry->register(new PV_WC_Gateway_WhatsApp_Blocks());
            }
        });
    }

    public static function build_whatsapp_url_from_order(WC_Order $order): string
    {
        $message = self::build_message_from_order($order);
        $settings = get_option('pv_settings', []);
        $number = isset($settings['whatsapp_number']) ? preg_replace('/[^0-9]/', '', (string) $settings['whatsapp_number']) : '';
        $message = str_replace("\n", "\r\n", $message);
        $encoded = rawurlencode($message);

        if ($number === '') {
            return 'https://wa.me/?text=' . $encoded;
        }

        return 'https://wa.me/' . $number . '?text=' . $encoded;
    }

    public static function render_thankyou_whatsapp_block(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $url = self::build_whatsapp_url_from_order($order);
        $order->update_meta_data('_pv_whatsapp_url', $url);
        $order->save();
        $message = self::build_message_from_order($order);
        $settings = get_option('pv_settings', []);
        $number = isset($settings['whatsapp_number']) ? preg_replace('/[^0-9]/', '', (string) $settings['whatsapp_number']) : '';
        $checkout_button_html = PV_Checkout_Flow::get_whatsapp_button_content_html(PV_Checkout_Flow::get_checkout_button_text());

        echo '<section class="pv-wa-order pv-wa-checkout-confirm pv-wa-order-cart">';
        echo '<h2 class="pv-wa-order-title">' . esc_html__('Confirm via WhatsApp', PV_TEXT_DOMAIN) . '</h2>';
        echo '<p class="pv-wa-order-help">' . esc_html__('Click to send your order summary through WhatsApp.', PV_TEXT_DOMAIN) . '</p>';
        echo '<p><a id="pv-wa-confirm-link" class="button alt pv-wa-btn" target="_blank" rel="noopener noreferrer" href="#">' . $checkout_button_html . '</a></p>';
        echo '</section>';
        echo '<script>(function(){'
            . 'var number=' . wp_json_encode($number) . ';'
            . 'var message=' . wp_json_encode($message) . ';'
            . 'function buildUrl(){var base=number?("https://wa.me/"+number):"https://wa.me/";return base+"?text="+encodeURIComponent(String(message||""));}'
            . 'var url=buildUrl();'
            . 'var link=document.getElementById("pv-wa-confirm-link");'
            . 'if(link){link.setAttribute("href",url);link.addEventListener("click",function(e){e.preventDefault();window.open(url,"_blank");});}'
            . 'window.setTimeout(function(){window.open(url,"_blank");},500);'
            . '})();</script>';
    }

    private static function build_message_from_order(WC_Order $order): string
    {
        $lines = [];
        $lines[] = PV_Checkout_Flow::get_default_message();
        $lines[] = '';
        $lines[] = __('Customer details', PV_TEXT_DOMAIN);
        $full_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($full_name !== '') {
            $lines[] = __('Name', PV_TEXT_DOMAIN) . ': ' . $full_name;
        }

        if ($order->get_billing_phone() !== '') {
            $lines[] = __('WhatsApp', PV_TEXT_DOMAIN) . ': ' . $order->get_billing_phone();
        }
        if ($order->get_billing_email() !== '') {
            $lines[] = __('Email', PV_TEXT_DOMAIN) . ': ' . $order->get_billing_email();
        }

        $lines[] = '';
        $lines[] = __('Shipping', PV_TEXT_DOMAIN);

        $city = self::normalize_whatsapp_text((string) $order->get_shipping_city());
        if ($city === '') {
            $city = self::normalize_whatsapp_text((string) $order->get_billing_city());
        }
        if ($city !== '') {
            $lines[] = __('City', PV_TEXT_DOMAIN) . ': ' . $city;
        }

        $state = self::normalize_whatsapp_text((string) $order->get_shipping_state());
        if ($state === '') {
            $state = self::normalize_whatsapp_text((string) $order->get_billing_state());
        }
        if ($state !== '') {
            $lines[] = __('State/Department', PV_TEXT_DOMAIN) . ': ' . self::resolve_state_label($state, (string) $order->get_shipping_country(), (string) $order->get_billing_country());
        }

        $shipping_address_1 = self::normalize_whatsapp_text((string) $order->get_shipping_address_1());
        $shipping_address_2 = self::normalize_whatsapp_text((string) $order->get_shipping_address_2());
        $address = trim($shipping_address_1 . ($shipping_address_2 !== '' ? ', ' . $shipping_address_2 : ''));
        if ($address === '') {
            $billing_address_1 = self::normalize_whatsapp_text((string) $order->get_billing_address_1());
            $billing_address_2 = self::normalize_whatsapp_text((string) $order->get_billing_address_2());
            $address = trim($billing_address_1 . ($billing_address_2 !== '' ? ', ' . $billing_address_2 : ''));
        }
        if ($address !== '') {
            $lines[] = __('Address', PV_TEXT_DOMAIN) . ': ' . $address;
        }

        $lines[] = '';
        $lines[] = __('Payment', PV_TEXT_DOMAIN);
        $payment_option_label = (string) $order->get_meta('_pv_wa_payment_option_label', true);
        if ($payment_option_label !== '') {
            $lines[] = __('Payment method', PV_TEXT_DOMAIN) . ': ' . $payment_option_label;
        } else {
            $lines[] = __('Payment method', PV_TEXT_DOMAIN) . ': ' . self::normalize_whatsapp_text((string) $order->get_payment_method_title());
        }

        $customer_note = trim((string) $order->get_customer_note());
        if ($customer_note !== '') {
            $lines[] = __('Shipping notes', PV_TEXT_DOMAIN) . ': ' . $customer_note;
        }

        $lines[] = '';
        $lines[] = __('Order from checkout', PV_TEXT_DOMAIN);
        $lines[] = __('Order', PV_TEXT_DOMAIN) . ': #' . $order->get_order_number();

        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $lines[] = __('Product', PV_TEXT_DOMAIN) . ': ' . $item->get_name();
            $lines[] = __('Quantity', PV_TEXT_DOMAIN) . ': ' . (int) $item->get_quantity();

            foreach ($item->get_meta_data() as $meta) {
                $key = isset($meta->key) ? (string) $meta->key : '';
                if ($key === '' || strpos($key, '_') === 0) {
                    continue;
                }

                $value = $meta->value;
                if (is_array($value) || is_object($value)) {
                    $value = wp_json_encode($value);
                }

                $text = self::normalize_whatsapp_text((string) $value);
                if ($text === '') {
                    continue;
                }

                if (self::looks_like_image_url($text)) {
                    $lines[] = $key . ': ' . __('Image uploaded in the order', PV_TEXT_DOMAIN);
                } else {
                    $lines[] = $key . ': ' . $text;
                }
            }
            $lines[] = '';
        }

        $closing = PV_Checkout_Flow::get_closing_message();
        if ($closing !== '') {
            $lines[] = $closing;
        }

        return implode("\n", $lines);
    }

    private static function resolve_state_label(string $state, string $shipping_country, string $billing_country): string
    {
        $country = $shipping_country !== '' ? $shipping_country : $billing_country;
        $state_code = strtoupper($state);
        if ($country !== '' && function_exists('WC') && WC() && WC()->countries) {
            $states = WC()->countries->get_states($country);
            if (is_array($states) && isset($states[$state_code])) {
                return self::normalize_whatsapp_text((string) $states[$state_code]);
            }
        }

        return $state;
    }

    private static function normalize_whatsapp_text(string $value): string
    {
        $value = preg_replace('/<br\\s*\\/?>/i', ', ', $value);
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\xc2\xa0|\x{00a0}/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        return trim((string) $value);
    }

    private static function looks_like_image_url(string $value): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }

        return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|bmp|svg|heic|heif)$/i', $path);
    }
}

if (class_exists('WC_Payment_Gateway')) {
    class PV_WC_Gateway_WhatsApp extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id                 = 'pv_whatsapp';
            $this->method_title       = __('Order via WhatsApp', PV_TEXT_DOMAIN);
            $this->method_description = __('Creates the order in WooCommerce and opens WhatsApp with the summary for confirmation.', PV_TEXT_DOMAIN);
            $this->has_fields         = true;
            $this->supports           = ['products'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = (string) $this->get_option('title', __('Order via WhatsApp', PV_TEXT_DOMAIN));
            $this->description = (string) $this->get_option('description', __('Finish your order and confirm via WhatsApp.', PV_TEXT_DOMAIN));
            $this->enabled     = (string) $this->get_option('enabled', 'yes');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields(): void
        {
            $this->form_fields = [
                'enabled' => [
                    'title'   => __('Enable/Disable', PV_TEXT_DOMAIN),
                    'type'    => 'checkbox',
                    'label'   => __('Enable "Order via WhatsApp"', PV_TEXT_DOMAIN),
                    'default' => 'yes',
                ],
                'title' => [
                    'title'       => __('Title', PV_TEXT_DOMAIN),
                    'type'        => 'text',
                    'description' => __('Title shown to customers at checkout.', PV_TEXT_DOMAIN),
                    'default'     => __('Order via WhatsApp', PV_TEXT_DOMAIN),
                    'desc_tip'    => true,
                ],
                'description' => [
                    'title'       => __('Description', PV_TEXT_DOMAIN),
                    'type'        => 'textarea',
                    'description' => __('Text visible to the customer at checkout.', PV_TEXT_DOMAIN),
                    'default'     => __('We will contact you via WhatsApp to confirm payment and production.', PV_TEXT_DOMAIN),
                ],
            ];
        }

        public function payment_fields(): void
        {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            $options = PV_Checkout_Flow::get_payment_options();
            if (empty($options)) {
                return;
            }

            echo '<p class="pv-wa-payment-option"><label for="pv_wa_payment_option"><strong>' . esc_html__('Preferred payment method *', PV_TEXT_DOMAIN) . '</strong></label><br>';
            echo '<select name="pv_wa_payment_option" id="pv_wa_payment_option">';
            echo '<option value="">' . esc_html__('Select an option', PV_TEXT_DOMAIN) . '</option>';
            foreach ($options as $value => $label) {
                if (is_int($value)) {
                    $value = (string) $label;
                }
                echo '<option value="' . esc_attr((string) $value) . '">' . esc_html((string) $label) . '</option>';
            }
            echo '</select></p>';
        }

        public function validate_fields(): bool
        {
            if (!PV_WhatsApp_Gateway_Manager::is_checkout_flow_enabled()) {
                wc_add_notice(__('This method is disabled in Cart2Chat > General.', PV_TEXT_DOMAIN), 'error');
                return false;
            }

            $options = PV_Checkout_Flow::get_payment_options();
            if (empty($options)) {
                return true;
            }

            if (!isset($_POST['pv_wa_payment_option'])) {
                return true;
            }

            $selected = isset($_POST['pv_wa_payment_option']) ? sanitize_text_field((string) wp_unslash($_POST['pv_wa_payment_option'])) : '';
            if ($selected === '' || (!array_key_exists($selected, $options) && !in_array($selected, $options, true))) {
                wc_add_notice(__('Select a payment method to continue with the WhatsApp order.', PV_TEXT_DOMAIN), 'error');
                return false;
            }

            return true;
        }

        public function process_payment($order_id): array
        {
            if (!PV_WhatsApp_Gateway_Manager::is_checkout_flow_enabled()) {
                wc_add_notice(__('This method is disabled in Cart2Chat > General.', PV_TEXT_DOMAIN), 'error');
                return ['result' => 'failure'];
            }

            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                wc_add_notice(__('The order could not be processed.', PV_TEXT_DOMAIN), 'error');
                return ['result' => 'failure'];
            }

            $options = PV_Checkout_Flow::get_payment_options();
            $selected = isset($_POST['pv_wa_payment_option']) ? sanitize_text_field((string) wp_unslash($_POST['pv_wa_payment_option'])) : '';
            if (!empty($options)) {
                if ($selected === '' || (!array_key_exists($selected, $options) && !in_array($selected, $options, true))) {
                    $first_key = (string) array_key_first($options);
                    if ($first_key !== '') {
                        $selected = $first_key;
                    }
                }
            }

            if ($selected !== '') {
                $label = array_key_exists($selected, $options) ? (string) $options[$selected] : $selected;
                $order->update_meta_data('_pv_wa_payment_option', $selected);
                $order->update_meta_data('_pv_wa_payment_option_label', $label);
            }

            $url = PV_WhatsApp_Gateway_Manager::build_whatsapp_url_from_order($order);
            $order->update_meta_data('_pv_whatsapp_url', $url);

            $order->update_status('pv-whatsapp', __('Order created from checkout with WhatsApp method.', PV_TEXT_DOMAIN));
            $order->reduce_order_stock();
            $order->save();
            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        public function is_available(): bool
        {
            if (!PV_WhatsApp_Gateway_Manager::is_checkout_flow_enabled()) {
                return false;
            }

            return parent::is_available();
        }
    }
}

if (class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
    class PV_WC_Gateway_WhatsApp_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
    {
        protected $name = 'pv_whatsapp';

        public function initialize(): void
        {
            $this->settings = get_option('woocommerce_pv_whatsapp_settings', []);
        }

        public function is_active(): bool
        {
            return !empty($this->settings['enabled'])
                && $this->settings['enabled'] === 'yes'
                && PV_WhatsApp_Gateway_Manager::is_checkout_flow_enabled();
        }

        public function get_payment_method_script_handles(): array
        {
            $handle = 'pv-whatsapp-blocks';
            $src = PV_PLUGIN_URL . 'assets/js/pv-whatsapp-blocks.js';
            wp_register_script(
                $handle,
                $src,
                ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'],
                PV_PLUGIN_VERSION,
                true
            );
            wp_set_script_translations($handle, PV_TEXT_DOMAIN, PV_PLUGIN_PATH . 'languages');

            return [$handle];
        }

        public function get_payment_method_data(): array
        {
            $title = !empty($this->settings['title']) ? (string) $this->settings['title'] : __('Order via WhatsApp', PV_TEXT_DOMAIN);
            $description = !empty($this->settings['description']) ? (string) $this->settings['description'] : __('We will contact you via WhatsApp to confirm payment and production.', PV_TEXT_DOMAIN);

            return [
                'title'       => $title,
                'description' => $description,
                'supports'    => [
                    'features' => ['products'],
                ],
            ];
        }
    }
}
