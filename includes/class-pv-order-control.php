<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_Order_Control
{
    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_post_pv_save_catalog_config', [__CLASS__, 'handle_save_catalog_config']);

        add_action('add_meta_boxes', [__CLASS__, 'register_order_metabox']);
        add_action('save_post_shop_order', [__CLASS__, 'save_order_metabox']);

        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'render_order_panel_hpos']);
        add_action('woocommerce_process_shop_order_meta', [__CLASS__, 'save_order_meta_hpos']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'Cart2Chat',
            'Cart2Chat',
            'manage_woocommerce',
            'cart2chat',
            [__CLASS__, 'render_admin_page'],
            'dashicons-store',
            56
        );
    }

    public static function render_admin_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'general';
        if (!in_array($tab, ['general', 'design', 'catalog', 'docs'], true)) {
            $tab = 'general';
        }
        ?>
        <div class="wrap">
            <h1>Cart2Chat</h1>
            <h2 class="nav-tab-wrapper" style="margin-bottom:16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cart2chat&tab=general')); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('General', PV_TEXT_DOMAIN); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cart2chat&tab=catalog')); ?>" class="nav-tab <?php echo $tab === 'catalog' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Catalog & Fields', PV_TEXT_DOMAIN); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cart2chat&tab=design')); ?>" class="nav-tab <?php echo $tab === 'design' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Design', PV_TEXT_DOMAIN); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cart2chat&tab=docs')); ?>" class="nav-tab <?php echo $tab === 'docs' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Usage Guide', PV_TEXT_DOMAIN); ?></a>
            </h2>

            <?php
            if ($tab === 'design') {
                self::render_design_page(true);
            } elseif ($tab === 'catalog') {
                self::render_catalog_page(true);
            } elseif ($tab === 'docs') {
                self::render_docs_page(true);
            } else {
                self::render_settings_page(true);
            }
            ?>
        </div>
        <?php
    }

    public static function register_settings(): void
    {
        $defaults = self::get_default_settings();
        register_setting('pv_settings_group', 'pv_settings', [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default'           => $defaults,
        ]);
    }

    public static function sanitize_settings(array $input): array
    {
        $defaults = self::get_default_settings();
        $existing = get_option('pv_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }

        $usage_mode = isset($input['usage_mode']) ? sanitize_key((string) $input['usage_mode']) : (string) ($existing['usage_mode'] ?? $defaults['usage_mode']);
        if (!array_key_exists($usage_mode, self::get_usage_mode_options())) {
            $usage_mode = 'both';
        }

        $design_style = isset($input['design_style']) ? sanitize_key((string) $input['design_style']) : (string) ($existing['design_style'] ?? $defaults['design_style']);
        if (!in_array($design_style, ['flat', 'modern'], true)) {
            $design_style = 'modern';
        }

        $design_theme = isset($input['design_theme']) ? sanitize_key((string) $input['design_theme']) : (string) ($existing['design_theme'] ?? $defaults['design_theme']);
        if (!in_array($design_theme, ['light', 'dark'], true)) {
            $design_theme = 'light';
        }

        $button_color = isset($input['button_color']) ? sanitize_hex_color((string) $input['button_color']) : sanitize_hex_color((string) ($existing['button_color'] ?? $defaults['button_color']));
        if (!$button_color) {
            $button_color = '#25d366';
        }

        $button_text_color = isset($input['button_text_color']) ? sanitize_hex_color((string) $input['button_text_color']) : sanitize_hex_color((string) ($existing['button_text_color'] ?? $defaults['button_text_color']));
        if (!$button_text_color) {
            $button_text_color = '#ffffff';
        }

        $design_payload_present = array_key_exists('design_style', $input)
            || array_key_exists('design_theme', $input)
            || array_key_exists('button_color', $input)
            || array_key_exists('button_text_color', $input)
            || array_key_exists('product_button_text', $input)
            || array_key_exists('checkout_button_text', $input);
        if (array_key_exists('show_whatsapp_icon', $input)) {
            $show_whatsapp_icon = !empty($input['show_whatsapp_icon']);
        } elseif ($design_payload_present) {
            $show_whatsapp_icon = false;
        } else {
            $show_whatsapp_icon = !empty($existing['show_whatsapp_icon']);
        }
        $product_button_text = isset($input['product_button_text']) ? sanitize_text_field((string) $input['product_button_text']) : (string) ($existing['product_button_text'] ?? $defaults['product_button_text']);
        if ($product_button_text === '') {
            $product_button_text = (string) $defaults['product_button_text'];
        }
        $checkout_button_text = isset($input['checkout_button_text']) ? sanitize_text_field((string) $input['checkout_button_text']) : (string) ($existing['checkout_button_text'] ?? $defaults['checkout_button_text']);
        if ($checkout_button_text === '') {
            $checkout_button_text = (string) $defaults['checkout_button_text'];
        }

        if (array_key_exists('whatsapp_form_fields', $input)) {
            $fields = [];
            $used_keys = [];
            if (!empty($input['whatsapp_form_fields']) && is_array($input['whatsapp_form_fields'])) {
                foreach ($input['whatsapp_form_fields'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
                    $type = isset($row['type']) ? sanitize_key((string) $row['type']) : 'text';
                    $required = !empty($row['required']);
                    $options_text = isset($row['options_text']) ? (string) $row['options_text'] : '';

                    $key = self::normalize_field_key($label);
                    $key = self::ensure_unique_field_key($key, $used_keys);
                    if ($key === '' || $label === '') {
                        continue;
                    }

                    if (!in_array($type, ['text', 'email', 'tel', 'textarea', 'select'], true)) {
                        $type = 'text';
                    }

                    $field = [
                        'key'      => $key,
                        'label'    => $label,
                        'type'     => $type,
                        'required' => $required,
                    ];

                    if ($type === 'select') {
                        $options = self::parse_options_text($options_text);
                        if (empty($options)) {
                            continue;
                        }
                        $field['options'] = $options;
                    }

                    $fields[] = $field;
                    $used_keys[] = $key;
                }
            }

            if (empty($fields)) {
                $fields = self::get_default_whatsapp_form_fields();
            }
        } else {
            $fields = isset($existing['whatsapp_form_fields']) && is_array($existing['whatsapp_form_fields'])
                ? $existing['whatsapp_form_fields']
                : self::get_default_whatsapp_form_fields();
        }

        return [
            'usage_mode' => $usage_mode,
            'whatsapp_number' => isset($input['whatsapp_number'])
                ? preg_replace('/[^0-9]/', '', (string) $input['whatsapp_number'])
                : (isset($existing['whatsapp_number']) ? preg_replace('/[^0-9]/', '', (string) $existing['whatsapp_number']) : ''),
            'default_message' => isset($input['default_message']) ? sanitize_text_field((string) $input['default_message']) : (string) ($existing['default_message'] ?? $defaults['default_message']),
            'closing_message' => isset($input['closing_message']) ? sanitize_text_field((string) $input['closing_message']) : (string) ($existing['closing_message'] ?? $defaults['closing_message']),
            'design_style' => $design_style,
            'design_theme' => $design_theme,
            'button_color' => $button_color,
            'button_text_color' => $button_text_color,
            'show_whatsapp_icon' => $show_whatsapp_icon,
            'product_button_text' => $product_button_text,
            'checkout_button_text' => $checkout_button_text,
            'whatsapp_form_fields' => $fields,
        ];
    }

    public static function get_default_settings(): array
    {
        return [
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
            'whatsapp_form_fields' => self::get_default_whatsapp_form_fields(),
        ];
    }

    public static function render_design_page(bool $embedded = false): void
    {
        if (!current_user_can('manage_woocommerce')) {
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
        $show_whatsapp_icon = !empty($settings['show_whatsapp_icon']);
        $product_button_text = sanitize_text_field((string) ($settings['product_button_text'] ?? __('Order via WhatsApp', PV_TEXT_DOMAIN)));
        if ($product_button_text === '') {
            $product_button_text = __('Order via WhatsApp', PV_TEXT_DOMAIN);
        }
        $checkout_button_text = sanitize_text_field((string) ($settings['checkout_button_text'] ?? __('Send order via WhatsApp', PV_TEXT_DOMAIN)));
        if ($checkout_button_text === '') {
            $checkout_button_text = __('Send order via WhatsApp', PV_TEXT_DOMAIN);
        }
        ?>
        <?php if (!$embedded) : ?>
        <div class="wrap">
            <h1>Cart2Chat</h1>
        <?php endif; ?>
            <p><?php echo esc_html__('Customize the visual style of Cart2Chat forms and buttons on the frontend.', PV_TEXT_DOMAIN); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('pv_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Visual style', PV_TEXT_DOMAIN); ?></th>
                            <td>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="pv_settings[design_style]" value="flat" <?php checked($design_style, 'flat'); ?>>
                                    <strong><?php echo esc_html__('Flat', PV_TEXT_DOMAIN); ?></strong> - <?php echo esc_html__('Containers and fields without rounded corners.', PV_TEXT_DOMAIN); ?>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="pv_settings[design_style]" value="modern" <?php checked($design_style, 'modern'); ?>>
                                    <strong><?php echo esc_html__('Modern', PV_TEXT_DOMAIN); ?></strong> - <?php echo esc_html__('Containers and fields with rounded corners.', PV_TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Theme', PV_TEXT_DOMAIN); ?></th>
                            <td>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="pv_settings[design_theme]" value="light" <?php checked($design_theme, 'light'); ?>>
                                    <strong><?php echo esc_html__('Light', PV_TEXT_DOMAIN); ?></strong> - <?php echo esc_html__('Light background with dark text in containers and fields.', PV_TEXT_DOMAIN); ?>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="pv_settings[design_theme]" value="dark" <?php checked($design_theme, 'dark'); ?>>
                                    <strong><?php echo esc_html__('Dark', PV_TEXT_DOMAIN); ?></strong> - <?php echo esc_html__('Dark background with light text in containers and fields.', PV_TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Buttons', PV_TEXT_DOMAIN); ?></th>
                            <td>
                                <p style="margin:0 0 10px;">
                                    <label><input type="checkbox" name="pv_settings[show_whatsapp_icon]" value="1" <?php checked($show_whatsapp_icon); ?>> <?php echo esc_html__('Show WhatsApp icon in buttons', PV_TEXT_DOMAIN); ?></label>
                                </p>
                                <p style="margin:0 0 10px;">
                                    <label for="pv_button_color"><strong><?php echo esc_html__('Button background color', PV_TEXT_DOMAIN); ?></strong></label><br>
                                    <input type="color" name="pv_settings[button_color]" id="pv_button_color" value="<?php echo esc_attr($button_color); ?>">
                                </p>
                                <p style="margin:0;">
                                    <label for="pv_button_text_color"><strong><?php echo esc_html__('Button text color', PV_TEXT_DOMAIN); ?></strong></label><br>
                                    <input type="color" name="pv_settings[button_text_color]" id="pv_button_text_color" value="<?php echo esc_attr($button_text_color); ?>">
                                </p>
                                <hr style="margin:14px 0;">
                                <p style="margin:0 0 10px;">
                                    <label for="pv_product_button_text"><strong><?php echo esc_html__('Product button text', PV_TEXT_DOMAIN); ?></strong></label><br>
                                    <input type="text" class="regular-text" name="pv_settings[product_button_text]" id="pv_product_button_text" value="<?php echo esc_attr($product_button_text); ?>">
                                </p>
                                <p style="margin:0;">
                                    <label for="pv_checkout_button_text"><strong><?php echo esc_html__('Checkout button text', PV_TEXT_DOMAIN); ?></strong></label><br>
                                    <input type="text" class="regular-text" name="pv_settings[checkout_button_text]" id="pv_checkout_button_text" value="<?php echo esc_attr($checkout_button_text); ?>">
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save design settings', PV_TEXT_DOMAIN)); ?>
            </form>
        <?php if (!$embedded) : ?>
        </div>
        <?php endif; ?>
        <?php
    }

    public static function render_settings_page(bool $embedded = false): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = get_option('pv_settings', []);
        $usage_mode = isset($settings['usage_mode']) ? sanitize_key((string) $settings['usage_mode']) : 'both';
        if (!array_key_exists($usage_mode, self::get_usage_mode_options())) {
            $usage_mode = 'both';
        }
        $show_product_whatsapp_fields = in_array($usage_mode, ['product', 'both'], true);
        $wa_fields = self::prepare_whatsapp_form_fields_for_admin($settings['whatsapp_form_fields'] ?? []);
        ?>
        <?php if (!$embedded) : ?>
        <div class="wrap">
            <h1>Cart2Chat</h1>
        <?php endif; ?>
            <p><?php echo esc_html__('Core setup for personalized sales flow.', PV_TEXT_DOMAIN); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('pv_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('How do you want to use Cart2Chat?', PV_TEXT_DOMAIN); ?></th>
                            <td>
                                <fieldset id="pv-usage-mode-fieldset">
                                    <?php foreach (self::get_usage_mode_options() as $mode_key => $mode) : ?>
                                        <label style="display:block;border:1px solid #dcdcde;border-radius:8px;padding:10px 12px;margin-bottom:8px;background:#fff;">
                                            <input type="radio" name="pv_settings[usage_mode]" value="<?php echo esc_attr($mode_key); ?>" <?php checked($usage_mode, $mode_key); ?>>
                                            <strong><?php echo esc_html($mode['title']); ?></strong><br>
                                            <span class="description"><?php echo esc_html($mode['description']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description"><?php echo wp_kses_post(sprintf(__('Recommended to start: <strong>%s</strong>.', PV_TEXT_DOMAIN), __('Both options', PV_TEXT_DOMAIN))); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pv_whatsapp_number"><?php echo esc_html__('WhatsApp number for orders', PV_TEXT_DOMAIN); ?></label></th>
                            <td>
                                <input name="pv_settings[whatsapp_number]" id="pv_whatsapp_number" type="text" class="regular-text" value="<?php echo esc_attr($settings['whatsapp_number'] ?? ''); ?>" />
                                <p class="description"><?php echo esc_html__('Numbers only. Example: 573001234567', PV_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pv_default_message"><?php echo esc_html__('Opening message', PV_TEXT_DOMAIN); ?></label></th>
                            <td>
                                <input name="pv_settings[default_message]" id="pv_default_message" type="text" class="regular-text" value="<?php echo esc_attr($settings['default_message'] ?? ''); ?>" />
                                <p class="description"><?php echo esc_html__('This message is used at the beginning of WhatsApp order messages.', PV_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pv_closing_message"><?php echo esc_html__('Closing message', PV_TEXT_DOMAIN); ?></label></th>
                            <td>
                                <input name="pv_settings[closing_message]" id="pv_closing_message" type="text" class="regular-text" value="<?php echo esc_attr($settings['closing_message'] ?? ''); ?>" />
                                <p class="description"><?php echo esc_html__('Closing text shown at the end of WhatsApp order messages.', PV_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr id="pv-wa-fields-row" style="<?php echo $show_product_whatsapp_fields ? '' : 'display:none;'; ?>">
                            <th scope="row"><?php echo esc_html__('WhatsApp order form fields', PV_TEXT_DOMAIN); ?></th>
                            <td>
                                <p class="description"><strong><?php echo esc_html__('These fields apply to the WhatsApp order form shown on product pages.', PV_TEXT_DOMAIN); ?></strong> <?php echo esc_html__('If checkout flow is used, customer/shipping data is taken from WooCommerce checkout fields.', PV_TEXT_DOMAIN); ?></p>
                                <p class="description"><?php echo esc_html__('Define which fields are shown to customers, whether they are required, and their options when the field type is select.', PV_TEXT_DOMAIN); ?></p>
                                <table class="widefat striped" id="pv-wa-fields-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html__('Label', PV_TEXT_DOMAIN); ?></th>
                                            <th><?php echo esc_html__('Type', PV_TEXT_DOMAIN); ?></th>
                                            <th><?php echo esc_html__('Required', PV_TEXT_DOMAIN); ?></th>
                                            <th><?php echo esc_html__('Options (select)', PV_TEXT_DOMAIN); ?></th>
                                            <th><?php echo esc_html__('Actions', PV_TEXT_DOMAIN); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wa_fields as $i => $field) : ?>
                                            <?php self::render_whatsapp_field_row($i, $field, true); ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p>
                                    <button type="button" class="button" id="pv-add-wa-field"><?php echo esc_html__('Add field', PV_TEXT_DOMAIN); ?></button>
                                </p>
                                <p class="description"><?php echo esc_html__('If the field type is select, use "Add option" to create, edit, or remove options.', PV_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save changes', PV_TEXT_DOMAIN)); ?>
            </form>

            <script>
                (function() {
                    var usageFieldset = document.getElementById('pv-usage-mode-fieldset');
                    var waRow = document.getElementById('pv-wa-fields-row');
                    var btn = document.getElementById('pv-add-wa-field');
                    var table = document.getElementById('pv-wa-fields-table');
                    if (!btn || !table) return;
                    var tbody = table.querySelector('tbody');
                    var index = tbody.querySelectorAll('tr').length;
                    var template = <?php echo wp_json_encode((string) self::get_whatsapp_field_row_template()); ?>;
                    var removeOptionLabel = <?php echo wp_json_encode(__('Remove', PV_TEXT_DOMAIN)); ?>;

                    function syncWaFieldsVisibility() {
                        if (!usageFieldset || !waRow) return;
                        var checked = usageFieldset.querySelector('input[name="pv_settings[usage_mode]"]:checked');
                        var mode = checked ? checked.value : 'both';
                        var visible = mode === 'product' || mode === 'both';
                        waRow.style.display = visible ? '' : 'none';
                    }

                    function refreshWaRowMoveButtons() {
                        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                        rows.forEach(function(row, idx) {
                            var upBtn = row.querySelector('.pv-move-up');
                            var downBtn = row.querySelector('.pv-move-down');
                            if (upBtn) {
                                upBtn.style.display = idx === 0 ? 'none' : '';
                            }
                            if (downBtn) {
                                downBtn.style.display = idx === rows.length - 1 ? 'none' : '';
                            }
                        });
                    }

                    function slugify(value) {
                        return String(value || '')
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .replace(/[^a-z0-9\s_-]/g, '')
                            .trim()
                            .replace(/[\s-]+/g, '_')
                            .replace(/_+/g, '_')
                            .replace(/^_+|_+$/g, '');
                    }

                    function syncRow(row) {
                        if (!row) return;
                        var labelInput = row.querySelector('.pv-label-input');
                        var typeSelect = row.querySelector('.pv-type-select');
                        var optionsBuilder = row.querySelector('.pv-options-builder');
                        var optionsNa = row.querySelector('.pv-options-na');
                        var keyInput = row.querySelector('.pv-hidden-key');
                        if (labelInput && keyInput) {
                            keyInput.value = slugify(labelInput.value);
                        }

                        if (typeSelect && optionsBuilder && optionsNa) {
                            var isSelect = typeSelect.value === 'select';
                            var hidden = optionsBuilder.querySelector('.pv-options-hidden');
                            if (hidden) hidden.disabled = !isSelect;
                            optionsBuilder.style.display = isSelect ? 'block' : 'none';
                            optionsNa.style.display = isSelect ? 'none' : 'inline';
                        }
                    }

                    function updateHiddenFromOptions(row) {
                        var builder = row.querySelector('.pv-options-builder');
                        if (!builder) return;
                        var hidden = builder.querySelector('.pv-options-hidden');
                        if (!hidden) return;
                        var labels = [];
                        builder.querySelectorAll('.pv-option-label').forEach(function(input) {
                            var value = (input.value || '').trim();
                            if (value) labels.push(value);
                        });
                        hidden.value = labels.join("\n");
                    }

                    function addOptionInput(row, value) {
                        var builder = row.querySelector('.pv-options-builder');
                        if (!builder) return;
                        var list = builder.querySelector('.pv-options-list');
                        if (!list) return;
                        var wrap = document.createElement('div');
                        wrap.className = 'pv-option-item';
                        wrap.style.display = 'flex';
                        wrap.style.gap = '6px';
                        wrap.style.marginBottom = '6px';
                        wrap.innerHTML = '<input type="text" class="pv-option-label" value="' + (value || '').replace(/"/g, '&quot;') + '" style="flex:1;"><button type="button" class="button pv-remove-option">' + removeOptionLabel + '</button>';
                        list.appendChild(wrap);
                        updateHiddenFromOptions(row);
                    }

                    function hydrateOptionsBuilder(row) {
                        var builder = row.querySelector('.pv-options-builder');
                        if (!builder || builder.dataset.hydrated === '1') return;
                        builder.dataset.hydrated = '1';
                        var hidden = builder.querySelector('.pv-options-hidden');
                        var initial = hidden ? hidden.value : '';
                        var lines = (initial || '').split(/\r?\n/).map(function(l) { return l.trim(); }).filter(Boolean);
                        if (lines.length === 0) {
                            addOptionInput(row, '');
                        } else {
                            lines.forEach(function(line) { addOptionInput(row, line); });
                        }
                    }

                    btn.addEventListener('click', function() {
                        var html = template.replace(/__INDEX__/g, String(index));
                        tbody.insertAdjacentHTML('beforeend', html);
                        var last = tbody.lastElementChild;
                        if (last) {
                            hydrateOptionsBuilder(last);
                            syncRow(last);
                        }
                        refreshWaRowMoveButtons();
                        index++;
                    });

                    if (usageFieldset) {
                        usageFieldset.addEventListener('change', syncWaFieldsVisibility);
                    }

                    function getClickedButton(eventTarget) {
                        var node = eventTarget || null;
                        if (node && node.nodeType === 3) {
                            node = node.parentElement;
                        }
                        return node && node.closest ? node.closest('button') : null;
                    }

                    tbody.addEventListener('click', function(e) {
                        var button = getClickedButton(e.target);
                        if (!button) return;

                        if (button.classList.contains('pv-move-up')) {
                            e.preventDefault();
                            var rowUp = button.closest('tr');
                            if (rowUp && rowUp.previousElementSibling) {
                                rowUp.parentNode.insertBefore(rowUp, rowUp.previousElementSibling);
                                refreshWaRowMoveButtons();
                            }
                        }
                        if (button.classList.contains('pv-move-down')) {
                            e.preventDefault();
                            var rowDown = button.closest('tr');
                            if (rowDown && rowDown.nextElementSibling) {
                                rowDown.parentNode.insertBefore(rowDown.nextElementSibling, rowDown);
                                refreshWaRowMoveButtons();
                            }
                        }
                        if (button.classList.contains('pv-remove-row')) {
                            e.preventDefault();
                            var row = button.closest('tr');
                            if (row) {
                                row.remove();
                                refreshWaRowMoveButtons();
                            }
                        }
                        if (button.classList.contains('pv-add-option')) {
                            e.preventDefault();
                            var rowAdd = button.closest('tr');
                            if (rowAdd) addOptionInput(rowAdd, '');
                        }
                        if (button.classList.contains('pv-remove-option')) {
                            e.preventDefault();
                            var rowRem = button.closest('tr');
                            var optRow = button.closest('.pv-option-item');
                            if (optRow) optRow.remove();
                            if (rowRem) updateHiddenFromOptions(rowRem);
                        }
                    });

                    tbody.addEventListener('input', function(e) {
                        var target = e.target;
                        var row = target.closest('tr');
                        if (!row) return;

                        if (target.classList.contains('pv-label-input') || target.classList.contains('pv-type-select')) {
                            syncRow(row);
                        }
                        if (target.classList.contains('pv-option-label')) {
                            updateHiddenFromOptions(row);
                        }
                    });

                    tbody.querySelectorAll('tr').forEach(function(row) {
                        hydrateOptionsBuilder(row);
                        syncRow(row);
                    });
                    refreshWaRowMoveButtons();
                    syncWaFieldsVisibility();
                })();
            </script>
        <?php if (!$embedded) : ?>
        </div>
        <?php endif; ?>
        <?php
    }

    public static function get_stage_options(): array
    {
        return [
            'nuevo'       => __('To review', PV_TEXT_DOMAIN),
            'diseno'      => __('Received', PV_TEXT_DOMAIN),
            'produccion'  => __('Paid', PV_TEXT_DOMAIN),
            'listo'       => __('Shipped', PV_TEXT_DOMAIN),
            'entregado'   => __('Delivered', PV_TEXT_DOMAIN),
        ];
    }

    public static function render_docs_page(bool $embedded = false): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $locale = (string) get_locale();
        $is_spanish = strpos(strtolower($locale), 'es_') === 0 || strtolower($locale) === 'es';
        $support_email = $is_spanish ? 'soporte@pinxel.co' : 'support@pinxel.co';
        $support_label = $is_spanish ? 'Correo de soporte:' : 'Support email:';
        ?>
        <?php if (!$embedded) : ?>
        <div class="wrap">
            <h1>Cart2Chat</h1>
        <?php endif; ?>
            <p><?php echo esc_html__('This page is a practical guide for store owners using Cart2Chat day to day.', PV_TEXT_DOMAIN); ?></p>

            <h2><?php echo esc_html__('1. Quick start (first setup)', PV_TEXT_DOMAIN); ?></h2>
            <ol>
                <li><?php echo esc_html__('Go to Cart2Chat > General.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Choose how you will sell: product page, checkout, or both.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Enter your WhatsApp number in international format (numbers only).', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Set your opening message and closing message.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Save changes.', PV_TEXT_DOMAIN); ?></li>
            </ol>

            <h2><?php echo esc_html__('2. If you want to sell from the product page', PV_TEXT_DOMAIN); ?></h2>
            <ol>
                <li><?php echo esc_html__('Edit a WooCommerce product.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Open the Cart2Chat tab inside the product editor.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Enable personalized purchase and choose a product type.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Update the product.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('On the storefront, the customer will see customization fields and the WhatsApp order button.', PV_TEXT_DOMAIN); ?></li>
            </ol>

            <h2><?php echo esc_html__('3. If you want to sell from checkout', PV_TEXT_DOMAIN); ?></h2>
            <ol>
                <li><?php echo esc_html__('In Cart2Chat > General, use mode: Checkout only or Both options.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Go to WooCommerce > Settings > Payments.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Enable the payment method "Order via WhatsApp".', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Customers can complete checkout and send the order summary to WhatsApp.', PV_TEXT_DOMAIN); ?></li>
            </ol>

            <h2><?php echo esc_html__('4. How to manage product types and custom fields', PV_TEXT_DOMAIN); ?></h2>
            <ol>
                <li><?php echo esc_html__('Go to Cart2Chat > Catalog & Fields.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Create product types (for example: Veterinary Record, Poster, Pin).', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Add fields for each type (text, number, textarea, select, file, email, tel).', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('For select fields, add options with "Add option".', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Use Up/Down buttons to sort product types and fields in the desired order.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Save configuration.', PV_TEXT_DOMAIN); ?></li>
            </ol>

            <h2><?php echo esc_html__('5. How to manage the WhatsApp customer form', PV_TEXT_DOMAIN); ?></h2>
            <ol>
                <li><?php echo esc_html__('Go to Cart2Chat > General.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('In "WhatsApp order form fields", add the customer/shipping/payment fields you need.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Mark required fields when needed.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Use Up/Down buttons to sort the form fields.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Save changes.', PV_TEXT_DOMAIN); ?></li>
            </ol>

            <h2><?php echo esc_html__('6. Design settings', PV_TEXT_DOMAIN); ?></h2>
            <ul>
                <li><?php echo esc_html__('Use Cart2Chat > Design to control style, theme, button colors, icon visibility, and button labels.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Font family and base button shape are inherited from your active theme.', PV_TEXT_DOMAIN); ?></li>
            </ul>

            <h2><?php echo esc_html__('7. Daily order management', PV_TEXT_DOMAIN); ?></h2>
            <ul>
                <li><?php echo esc_html__('Open WooCommerce orders to review customer data and product customization details.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Use the Cart2Chat order panel to track stage and internal notes.', PV_TEXT_DOMAIN); ?></li>
            </ul>

            <h2><?php echo esc_html__('8. Troubleshooting', PV_TEXT_DOMAIN); ?></h2>
            <ul>
                <li><?php echo esc_html__('No payment methods available at checkout: verify Cart2Chat mode includes checkout and the WhatsApp gateway is enabled in WooCommerce payments.', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('WhatsApp link is not opening correctly: verify the WhatsApp number format (numbers only, international format).', PV_TEXT_DOMAIN); ?></li>
                <li><?php echo esc_html__('Customer cannot continue: check required fields and required select options in your configuration.', PV_TEXT_DOMAIN); ?></li>
            </ul>

            <h2><?php echo esc_html__('9. Developer and support', PV_TEXT_DOMAIN); ?></h2>
            <p>
                <?php echo esc_html__('Developed by Pinxel.', PV_TEXT_DOMAIN); ?><br>
                <?php echo esc_html($support_label); ?> <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a><br>
                <?php echo esc_html__('If you want to support the developer with a donation, you can do it via PayPal.', PV_TEXT_DOMAIN); ?>
            </p>
        <?php if (!$embedded) : ?>
        </div>
        <?php endif; ?>
        <?php
    }

    public static function render_catalog_page(bool $embedded = false): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $config = PV_Config::get_catalog_config();
        $notice = isset($_GET['pv_notice']) ? sanitize_text_field((string) wp_unslash($_GET['pv_notice'])) : '';
        ?>
        <?php if (!$embedded) : ?>
        <div class="wrap">
            <h1>Cart2Chat</h1>
        <?php endif; ?>
            <p><?php echo esc_html__('Manage product types and custom fields for each type.', PV_TEXT_DOMAIN); ?></p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Configuration saved successfully.', PV_TEXT_DOMAIN); ?></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Could not save. Check required fields and try again.', PV_TEXT_DOMAIN); ?></p></div>
            <?php elseif ($notice === 'reset') : ?>
                <div class="notice notice-warning"><p><?php echo esc_html__('Default configuration restored.', PV_TEXT_DOMAIN); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="pv_save_catalog_config" />
                <?php wp_nonce_field('pv_save_catalog_config_nonce', 'pv_save_catalog_config_nonce_field'); ?>

                <h2><?php echo esc_html__('Product types and custom fields', PV_TEXT_DOMAIN); ?></h2>
                <div id="pv-type-blocks">
                    <?php $block_index = 0; foreach ($config['product_types'] as $type_key => $type_label) : ?>
                        <div class="pv-type-block" data-type-index="<?php echo esc_attr((string) $block_index); ?>" style="border:1px solid #ccd0d4;border-radius:8px;padding:12px;margin-bottom:14px;background:#fff;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                                <h3 style="margin:0;"><?php echo esc_html__('Product type', PV_TEXT_DOMAIN); ?></h3>
                                <div>
                                    <button class="button pv-move-type-up" type="button"><?php echo esc_html__('Up', PV_TEXT_DOMAIN); ?></button>
                                    <button class="button pv-move-type-down" type="button"><?php echo esc_html__('Down', PV_TEXT_DOMAIN); ?></button>
                                    <button class="button pv-remove-type" type="button"><?php echo esc_html__('Remove type', PV_TEXT_DOMAIN); ?></button>
                                </div>
                            </div>
                            <p style="margin:8px 0 12px;">
                                <label><?php echo esc_html__('Type name', PV_TEXT_DOMAIN); ?>
                                    <input class="pv-type-label" type="text" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][type_label]" value="<?php echo esc_attr((string) $type_label); ?>" style="width:100%;">
                                </label>
                            </p>

                            <table class="widefat striped pv-block-fields-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Field label', PV_TEXT_DOMAIN); ?></th>
                                        <th><?php echo esc_html__('Type', PV_TEXT_DOMAIN); ?></th>
                                        <th><?php echo esc_html__('Required', PV_TEXT_DOMAIN); ?></th>
                                        <th><?php echo esc_html__('Show only if...', PV_TEXT_DOMAIN); ?></th>
                                        <th><?php echo esc_html__('Options (select)', PV_TEXT_DOMAIN); ?></th>
                                        <th><?php echo esc_html__('Actions', PV_TEXT_DOMAIN); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $fields_for_type = (array) ($config['product_fields'][$type_key] ?? []);
                                    $key_to_idx = [];
                                    foreach ($fields_for_type as $idx => $f) {
                                        $k = isset($f['key']) ? (string) $f['key'] : '';
                                        if ($k !== '') {
                                            $key_to_idx[$k] = (string) $idx;
                                        }
                                    }
                                    ?>
                                    <?php $sub_index = 0; foreach ($fields_for_type as $field) : ?>
                                        <?php $is_select = (string) ($field['type'] ?? 'text') === 'select'; ?>
                                        <?php
                                        $dep_index = '';
                                        $dep_value = '';
                                        if (!empty($field['required_if']) && is_array($field['required_if'])) {
                                            $dep_key = (string) key($field['required_if']);
                                            $dep_value = (string) current($field['required_if']);
                                            $dep_index = $key_to_idx[$dep_key] ?? '';
                                        }
                                        ?>
                                        <tr>
                                            <td><input class="pv-label-input" type="text" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][fields][<?php echo esc_attr((string) $sub_index); ?>][label]" value="<?php echo esc_attr((string) ($field['label'] ?? '')); ?>" style="width:100%;"></td>
                                            <td>
                                                <select class="pv-type-select" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][fields][<?php echo esc_attr((string) $sub_index); ?>][type]" style="width:100%;">
                                                    <?php foreach (['text','number','textarea','select','file','email','tel'] as $field_type) : ?>
                                                        <option value="<?php echo esc_attr($field_type); ?>" <?php selected((string) ($field['type'] ?? 'text'), $field_type); ?>><?php echo esc_html($field_type); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><label><input type="checkbox" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][fields][<?php echo esc_attr((string) $sub_index); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?>> <?php echo esc_html__('Yes', PV_TEXT_DOMAIN); ?></label></td>
                                            <td>
                                                <select class="pv-depends-on" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][fields][<?php echo esc_attr((string) $sub_index); ?>][depends_on_index]" data-current="<?php echo esc_attr($dep_index); ?>" style="width:100%;margin-bottom:6px;">
                                                    <option value=""><?php echo esc_html__('Always visible', PV_TEXT_DOMAIN); ?></option>
                                                </select>
                                                <select class="pv-depends-value" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][fields][<?php echo esc_attr((string) $sub_index); ?>][depends_on_value]" data-current="<?php echo esc_attr($dep_value); ?>" style="width:100%;">
                                                    <option value="">N/A</option>
                                                </select>
                                            </td>
                                            <td>
                                                <?php
                                                $opt_text = '';
                                                if (!empty($field['options']) && is_array($field['options'])) {
                                                    foreach ($field['options'] as $v => $l) {
                                                        $opt_text .= (is_int($v) ? $l : $v . '|' . $l) . PHP_EOL;
                                                    }
                                                    $opt_text = trim($opt_text);
                                                }
                                                ?>
                                                <div class="pv-options-builder" style="<?php echo !$is_select ? 'display:none;' : ''; ?>">
                                                    <div class="pv-options-list"></div>
                                                    <button type="button" class="button pv-add-option"><?php echo esc_html__('Add option', PV_TEXT_DOMAIN); ?></button>
                                                    <input class="pv-options-hidden" type="hidden" name="pv_catalog_blocks[<?php echo esc_attr((string) $block_index); ?>][fields][<?php echo esc_attr((string) $sub_index); ?>][options_text]" value="<?php echo esc_attr($opt_text); ?>" <?php echo !$is_select ? 'disabled' : ''; ?>>
                                                </div>
                                                <span class="pv-options-na" style="<?php echo $is_select ? 'display:none;' : ''; ?>">N/A</span>
                                            </td>
                                            <td>
                                                <button class="button pv-move-up" type="button"><?php echo esc_html__('Up', PV_TEXT_DOMAIN); ?></button>
                                                <button class="button pv-move-down" type="button"><?php echo esc_html__('Down', PV_TEXT_DOMAIN); ?></button>
                                                <button class="button pv-remove-row" type="button"><?php echo esc_html__('Remove', PV_TEXT_DOMAIN); ?></button>
                                            </td>
                                        </tr>
                                    <?php $sub_index++; endforeach; ?>
                                </tbody>
                            </table>
                            <p style="margin-top:8px;"><button class="button pv-add-field-in-block" type="button"><?php echo esc_html__('Add field to this type', PV_TEXT_DOMAIN); ?></button></p>
                        </div>
                    <?php $block_index++; endforeach; ?>
                </div>

                <p><button class="button" type="button" id="pv-add-type-block"><?php echo esc_html__('Add product type', PV_TEXT_DOMAIN); ?></button></p>
                <p class="description"><?php echo esc_html__('For select fields, use "Add option" to create options.', PV_TEXT_DOMAIN); ?></p>

                <p style="margin-top:14px;">
                    <button class="button button-primary" type="submit" name="pv_catalog_action" value="save"><?php echo esc_html__('Save configuration', PV_TEXT_DOMAIN); ?></button>
                    <button class="button" type="submit" name="pv_catalog_action" value="reset" onclick="return confirm('<?php echo esc_js(__('Restore default configuration?', PV_TEXT_DOMAIN)); ?>');"><?php echo esc_html__('Restore defaults', PV_TEXT_DOMAIN); ?></button>
                </p>
            </form>

            <script>
                (function() {
                    var blocksWrap = document.getElementById('pv-type-blocks');
                    var addTypeBtn = document.getElementById('pv-add-type-block');
                    if (!blocksWrap || !addTypeBtn) return;
                    var i18n = {
                        field: <?php echo wp_json_encode(__('Field', PV_TEXT_DOMAIN)); ?>,
                        alwaysVisible: <?php echo wp_json_encode(__('Always visible', PV_TEXT_DOMAIN)); ?>,
                        selectValue: <?php echo wp_json_encode(__('Select value', PV_TEXT_DOMAIN)); ?>,
                        remove: <?php echo wp_json_encode(__('Remove', PV_TEXT_DOMAIN)); ?>,
                        yes: <?php echo wp_json_encode(__('Yes', PV_TEXT_DOMAIN)); ?>,
                        addOption: <?php echo wp_json_encode(__('Add option', PV_TEXT_DOMAIN)); ?>,
                        productType: <?php echo wp_json_encode(__('Product type', PV_TEXT_DOMAIN)); ?>,
                        removeType: <?php echo wp_json_encode(__('Remove type', PV_TEXT_DOMAIN)); ?>,
                        typeName: <?php echo wp_json_encode(__('Type name', PV_TEXT_DOMAIN)); ?>,
                        fieldLabel: <?php echo wp_json_encode(__('Field label', PV_TEXT_DOMAIN)); ?>,
                        type: <?php echo wp_json_encode(__('Type', PV_TEXT_DOMAIN)); ?>,
                        required: <?php echo wp_json_encode(__('Required', PV_TEXT_DOMAIN)); ?>,
                        showOnlyIf: <?php echo wp_json_encode(__('Show only if...', PV_TEXT_DOMAIN)); ?>,
                        optionsSelect: <?php echo wp_json_encode(__('Options (select)', PV_TEXT_DOMAIN)); ?>,
                        addFieldToType: <?php echo wp_json_encode(__('Add field to this type', PV_TEXT_DOMAIN)); ?>,
                        actions: <?php echo wp_json_encode(__('Actions', PV_TEXT_DOMAIN)); ?>,
                        up: <?php echo wp_json_encode(__('Up', PV_TEXT_DOMAIN)); ?>,
                        down: <?php echo wp_json_encode(__('Down', PV_TEXT_DOMAIN)); ?>
                    };

                    function syncRow(row) {
                        if (!row) return;
                        var typeSelect = row.querySelector('.pv-type-select');
                        var optionsBuilder = row.querySelector('.pv-options-builder');
                        var optionsNa = row.querySelector('.pv-options-na');
                        if (!typeSelect || !optionsBuilder || !optionsNa) return;
                        var isSelect = typeSelect.value === 'select';
                        var hidden = optionsBuilder.querySelector('.pv-options-hidden');
                        if (hidden) hidden.disabled = !isSelect;
                        optionsBuilder.style.display = isSelect ? 'block' : 'none';
                        optionsNa.style.display = isSelect ? 'none' : 'inline';
                    }

                    function parseOptionsText(raw) {
                        var text = String(raw || '').replace(/\\n/g, '\n');
                        return text.split(/\r?\n/).map(function(line) {
                            line = line.trim();
                            if (!line) return null;
                            if (line.indexOf('|') !== -1) {
                                var parts = line.split('|');
                                var value = (parts[0] || '').trim();
                                var label = (parts[1] || '').trim();
                                if (!value || !label) return null;
                                return { value: value, label: label };
                            }
                            return { value: line, label: line };
                        }).filter(Boolean);
                    }

                    function refreshDependencies(block) {
                        if (!block) return;
                        var rows = Array.prototype.slice.call(block.querySelectorAll('table tbody tr'));
                        var controllers = [];
                        function getRowIndex(row) {
                            var labelInput = row.querySelector('.pv-label-input');
                            if (!labelInput || !labelInput.name) return '';
                            var m = labelInput.name.match(/\[fields\]\[([^\]]+)\]\[label\]/);
                            return m && m[1] ? String(m[1]) : '';
                        }

                        rows.forEach(function(row) {
                            var typeSelect = row.querySelector('.pv-type-select');
                            var labelInput = row.querySelector('.pv-label-input');
                            var hidden = row.querySelector('.pv-options-hidden');
                            var rowIndex = getRowIndex(row);
                            if (!typeSelect || !labelInput) return;
                            if (typeSelect.value !== 'select') return;
                            if (rowIndex === '') return;
                            controllers.push({
                                index: rowIndex,
                                label: (labelInput.value || i18n.field).trim(),
                                options: parseOptionsText(hidden ? hidden.value : '')
                            });
                        });

                        rows.forEach(function(row) {
                            var depSelect = row.querySelector('.pv-depends-on');
                            var depValue = row.querySelector('.pv-depends-value');
                            var rowIndex = getRowIndex(row);
                            if (!depSelect || !depValue) return;

                            var currentDep = depSelect.value || depSelect.getAttribute('data-current') || '';
                            var currentVal = depValue.value || depValue.getAttribute('data-current') || '';
                            depSelect.innerHTML = '<option value="">' + i18n.alwaysVisible + '</option>';

                            controllers.forEach(function(ctrl) {
                                if (ctrl.index === rowIndex) return;
                                var opt = document.createElement('option');
                                opt.value = ctrl.index;
                                opt.textContent = ctrl.label;
                                depSelect.appendChild(opt);
                            });
                            depSelect.value = currentDep;
                            depSelect.removeAttribute('data-current');

                            var selected = controllers.find(function(c) { return c.index === depSelect.value; });
                            depValue.innerHTML = '';
                            if (!selected || selected.options.length === 0) {
                                depValue.innerHTML = '<option value="">N/A</option>';
                                depValue.disabled = true;
                            } else {
                                depValue.disabled = false;
                                depValue.innerHTML = '<option value="">' + i18n.selectValue + '</option>';
                                selected.options.forEach(function(optData) {
                                    var opt = document.createElement('option');
                                    opt.value = optData.value;
                                    opt.textContent = optData.label;
                                    depValue.appendChild(opt);
                                });
                            }

                            depValue.value = currentVal;
                            depValue.removeAttribute('data-current');
                        });
                    }

                    function refreshFieldMoveButtons(block) {
                        if (!block) return;
                        var rows = Array.prototype.slice.call(block.querySelectorAll('table tbody tr'));
                        rows.forEach(function(row, idx) {
                            var upBtn = row.querySelector('.pv-move-up');
                            var downBtn = row.querySelector('.pv-move-down');
                            if (upBtn) {
                                upBtn.style.display = idx === 0 ? 'none' : '';
                            }
                            if (downBtn) {
                                downBtn.style.display = idx === rows.length - 1 ? 'none' : '';
                            }
                        });
                    }

                    function refreshTypeMoveButtons() {
                        var blocks = Array.prototype.slice.call(blocksWrap.querySelectorAll('.pv-type-block'));
                        blocks.forEach(function(block, idx) {
                            var upBtn = block.querySelector('.pv-move-type-up');
                            var downBtn = block.querySelector('.pv-move-type-down');
                            if (upBtn) {
                                upBtn.style.display = idx === 0 ? 'none' : '';
                            }
                            if (downBtn) {
                                downBtn.style.display = idx === blocks.length - 1 ? 'none' : '';
                            }
                        });
                    }

                    function updateHiddenFromOptions(row) {
                        var builder = row.querySelector('.pv-options-builder');
                        if (!builder) return;
                        var hidden = builder.querySelector('.pv-options-hidden');
                        if (!hidden) return;
                        var labels = [];
                        builder.querySelectorAll('.pv-option-label').forEach(function(input) {
                            var value = (input.value || '').trim();
                            if (value) labels.push(value);
                        });
                        hidden.value = labels.join("\n");
                    }

                    function addOptionInput(row, value) {
                        var builder = row.querySelector('.pv-options-builder');
                        if (!builder) return;
                        var list = builder.querySelector('.pv-options-list');
                        if (!list) return;
                        var wrap = document.createElement('div');
                        wrap.className = 'pv-option-item';
                        wrap.style.display = 'flex';
                        wrap.style.gap = '6px';
                        wrap.style.marginBottom = '6px';
                        wrap.innerHTML = '<input type="text" class="pv-option-label" value="' + (value || '').replace(/"/g, '&quot;') + '" style="flex:1;"><button type="button" class="button pv-remove-option">' + i18n.remove + '</button>';
                        list.appendChild(wrap);
                        updateHiddenFromOptions(row);
                    }

                    function hydrateOptionsBuilder(row) {
                        var builder = row.querySelector('.pv-options-builder');
                        if (!builder || builder.dataset.hydrated === '1') return;
                        builder.dataset.hydrated = '1';
                        var hidden = builder.querySelector('.pv-options-hidden');
                        var initial = hidden ? hidden.value : '';
                        var lines = (initial || '').split(/\r?\n/).map(function(l) { return l.trim(); }).filter(Boolean);
                        if (lines.length === 0) {
                            addOptionInput(row, '');
                        } else {
                            lines.forEach(function(line) { addOptionInput(row, line); });
                        }
                    }

                    function buildFieldRow(blockIndex, fieldIndex) {
                        return '<tr>' +
                            '<td><input class=\"pv-label-input\" type=\"text\" name=\"pv_catalog_blocks[' + blockIndex + '][fields][' + fieldIndex + '][label]\" style=\"width:100%;\"></td>' +
                            '<td><select class=\"pv-type-select\" name=\"pv_catalog_blocks[' + blockIndex + '][fields][' + fieldIndex + '][type]\" style=\"width:100%;\"><option value=\"text\">text</option><option value=\"number\">number</option><option value=\"textarea\">textarea</option><option value=\"select\">select</option><option value=\"file\">file</option><option value=\"email\">email</option><option value=\"tel\">tel</option></select></td>' +
                            '<td><label><input type=\"checkbox\" name=\"pv_catalog_blocks[' + blockIndex + '][fields][' + fieldIndex + '][required]\" value=\"1\"> ' + i18n.yes + '</label></td>' +
                            '<td><select class=\"pv-depends-on\" name=\"pv_catalog_blocks[' + blockIndex + '][fields][' + fieldIndex + '][depends_on_index]\" style=\"width:100%;margin-bottom:6px;\"><option value=\"\">' + i18n.alwaysVisible + '</option></select><select class=\"pv-depends-value\" name=\"pv_catalog_blocks[' + blockIndex + '][fields][' + fieldIndex + '][depends_on_value]\" style=\"width:100%;\" disabled><option value=\"\">N/A</option></select></td>' +
                            '<td><div class=\"pv-options-builder\" style=\"display:none;\"><div class=\"pv-options-list\"></div><button type=\"button\" class=\"button pv-add-option\">' + i18n.addOption + '</button><input class=\"pv-options-hidden\" type=\"hidden\" name=\"pv_catalog_blocks[' + blockIndex + '][fields][' + fieldIndex + '][options_text]\" disabled></div><span class=\"pv-options-na\">N/A</span></td>' +
                            '<td><button class=\"button pv-move-up\" type=\"button\">' + i18n.up + '</button> <button class=\"button pv-move-down\" type=\"button\">' + i18n.down + '</button> <button class=\"button pv-remove-row\" type=\"button\">' + i18n.remove + '</button></td>' +
                        '</tr>';
                    }

                    function buildTypeBlock(blockIndex) {
                        return '<div class=\"pv-type-block\" data-type-index=\"' + blockIndex + '\" style=\"border:1px solid #ccd0d4;border-radius:8px;padding:12px;margin-bottom:14px;background:#fff;\">' +
                            '<div style=\"display:flex;justify-content:space-between;align-items:center;gap:10px;\">' +
                                '<h3 style=\"margin:0;\">' + i18n.productType + '</h3>' +
                                '<div><button class=\"button pv-move-type-up\" type=\"button\">' + i18n.up + '</button> <button class=\"button pv-move-type-down\" type=\"button\">' + i18n.down + '</button> <button class=\"button pv-remove-type\" type=\"button\">' + i18n.removeType + '</button></div>' +
                            '</div>' +
                            '<p style=\"margin:8px 0 12px;\"><label>' + i18n.typeName + '<input class=\"pv-type-label\" type=\"text\" name=\"pv_catalog_blocks[' + blockIndex + '][type_label]\" style=\"width:100%;\"></label></p>' +
                            '<table class=\"widefat striped pv-block-fields-table\"><thead><tr><th>' + i18n.fieldLabel + '</th><th>' + i18n.type + '</th><th>' + i18n.required + '</th><th>' + i18n.showOnlyIf + '</th><th>' + i18n.optionsSelect + '</th><th>' + i18n.actions + '</th></tr></thead><tbody></tbody></table>' +
                            '<p style=\"margin-top:8px;\"><button class=\"button pv-add-field-in-block\" type=\"button\">' + i18n.addFieldToType + '</button></p>' +
                        '</div>';
                    }

                    function getNextBlockIndex() {
                        var max = -1;
                        blocksWrap.querySelectorAll('.pv-type-block').forEach(function(block) {
                            var i = parseInt(block.getAttribute('data-type-index') || '0', 10);
                            if (i > max) max = i;
                        });
                        return max + 1;
                    }

                    function getNextFieldIndex(block) {
                        var max = -1;
                        block.querySelectorAll('table tbody .pv-label-input').forEach(function(input) {
                            if (!input.name) return;
                            var m = input.name.match(/\[fields\]\[([^\]]+)\]\[label\]/);
                            if (!m || !m[1]) return;
                            var i = parseInt(m[1], 10);
                            if (!isNaN(i) && i > max) max = i;
                        });
                        return max + 1;
                    }

                    function getClickedButton(eventTarget) {
                        var node = eventTarget || null;
                        if (node && node.nodeType === 3) {
                            node = node.parentElement;
                        }
                        return node && node.closest ? node.closest('button') : null;
                    }

                    blocksWrap.addEventListener('click', function(e) {
                        var button = getClickedButton(e.target);
                        if (!button) return;

                        if (button.classList.contains('pv-move-type-up')) {
                            e.preventDefault();
                            var typeBlockUp = button.closest('.pv-type-block');
                            if (typeBlockUp && typeBlockUp.previousElementSibling) {
                                typeBlockUp.parentNode.insertBefore(typeBlockUp, typeBlockUp.previousElementSibling);
                                refreshTypeMoveButtons();
                            }
                            return;
                        }

                        if (button.classList.contains('pv-move-type-down')) {
                            e.preventDefault();
                            var typeBlockDown = button.closest('.pv-type-block');
                            if (typeBlockDown && typeBlockDown.nextElementSibling) {
                                typeBlockDown.parentNode.insertBefore(typeBlockDown.nextElementSibling, typeBlockDown);
                                refreshTypeMoveButtons();
                            }
                            return;
                        }

                        if (button.classList.contains('pv-remove-type')) {
                            e.preventDefault();
                            var block = button.closest('.pv-type-block');
                            if (block) {
                                block.remove();
                                refreshTypeMoveButtons();
                            }
                            return;
                        }

                        if (button.classList.contains('pv-move-up')) {
                            e.preventDefault();
                            var rowUp = button.closest('tr');
                            if (rowUp && rowUp.previousElementSibling) {
                                rowUp.parentNode.insertBefore(rowUp, rowUp.previousElementSibling);
                                var blockAfterUp = button.closest('.pv-type-block');
                                if (blockAfterUp) {
                                    refreshDependencies(blockAfterUp);
                                    refreshFieldMoveButtons(blockAfterUp);
                                }
                            }
                            return;
                        }

                        if (button.classList.contains('pv-move-down')) {
                            e.preventDefault();
                            var rowDown = button.closest('tr');
                            if (rowDown && rowDown.nextElementSibling) {
                                rowDown.parentNode.insertBefore(rowDown.nextElementSibling, rowDown);
                                var blockAfterDown = button.closest('.pv-type-block');
                                if (blockAfterDown) {
                                    refreshDependencies(blockAfterDown);
                                    refreshFieldMoveButtons(blockAfterDown);
                                }
                            }
                            return;
                        }

                        if (button.classList.contains('pv-remove-row')) {
                            e.preventDefault();
                            var row = button.closest('tr');
                            if (row) row.remove();
                            var blockAfterRemove = button.closest('.pv-type-block');
                            if (blockAfterRemove) {
                                refreshDependencies(blockAfterRemove);
                                refreshFieldMoveButtons(blockAfterRemove);
                            }
                            return;
                        }

                        if (button.classList.contains('pv-add-option')) {
                            e.preventDefault();
                            var rowAdd = button.closest('tr');
                            if (rowAdd) addOptionInput(rowAdd, '');
                            return;
                        }

                        if (button.classList.contains('pv-remove-option')) {
                            e.preventDefault();
                            var rowParent = button.closest('tr');
                            var optRow = button.closest('.pv-option-item');
                            if (optRow) optRow.remove();
                            if (rowParent) updateHiddenFromOptions(rowParent);
                            return;
                        }

                        if (button.classList.contains('pv-add-field-in-block')) {
                            e.preventDefault();
                            var block = button.closest('.pv-type-block');
                            if (!block) return;
                            var blockIndex = block.getAttribute('data-type-index');
                            var nextField = getNextFieldIndex(block);
                            var tbody = block.querySelector('table tbody');
                            if (!tbody) return;
                            tbody.insertAdjacentHTML('beforeend', buildFieldRow(blockIndex, nextField));
                            hydrateOptionsBuilder(tbody.lastElementChild);
                            syncRow(tbody.lastElementChild);
                            refreshDependencies(block);
                            refreshFieldMoveButtons(block);
                        }
                    });

                    blocksWrap.addEventListener('change', function(e) {
                        var target = e.target;
                        if (!target) return;
                        var row = target.closest('tr');
                        if (target.classList.contains('pv-type-select') && row) {
                            syncRow(row);
                            var blockA = target.closest('.pv-type-block');
                            if (blockA) refreshDependencies(blockA);
                            return;
                        }
                        if (target.classList.contains('pv-depends-on')) {
                            var blockB = target.closest('.pv-type-block');
                            if (blockB) refreshDependencies(blockB);
                        }
                    });

                    blocksWrap.addEventListener('input', function(e) {
                        var target = e.target;
                        if (!target) return;
                        var row = target.closest('tr');
                        if (!row) return;
                        if (target.classList.contains('pv-option-label')) {
                            updateHiddenFromOptions(row);
                            var blockC = target.closest('.pv-type-block');
                            if (blockC) refreshDependencies(blockC);
                        }
                        if (target.classList.contains('pv-label-input')) {
                            var blockD = target.closest('.pv-type-block');
                            if (blockD) refreshDependencies(blockD);
                        }
                    });

                    addTypeBtn.addEventListener('click', function() {
                        var idx = getNextBlockIndex();
                        blocksWrap.insertAdjacentHTML('beforeend', buildTypeBlock(idx));
                        refreshTypeMoveButtons();
                    });

                    blocksWrap.querySelectorAll('table tbody tr').forEach(function(row) {
                        hydrateOptionsBuilder(row);
                        syncRow(row);
                    });
                    blocksWrap.querySelectorAll('.pv-type-block').forEach(function(block) {
                        refreshDependencies(block);
                        refreshFieldMoveButtons(block);
                    });
                    refreshTypeMoveButtons();
                })();
            </script>
        <?php if (!$embedded) : ?>
        </div>
        <?php endif; ?>
        <?php
    }

    public static function handle_save_catalog_config(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', PV_TEXT_DOMAIN));
        }

        if (!isset($_POST['pv_save_catalog_config_nonce_field']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['pv_save_catalog_config_nonce_field'])), 'pv_save_catalog_config_nonce')) {
            wp_die(esc_html__('Invalid nonce.', PV_TEXT_DOMAIN));
        }

        $action = isset($_POST['pv_catalog_action']) ? sanitize_text_field((string) wp_unslash($_POST['pv_catalog_action'])) : 'save';
        if ($action === 'reset') {
            PV_Config::reset_to_defaults();
            wp_safe_redirect(admin_url('admin.php?page=cart2chat&tab=catalog&pv_notice=reset'));
            exit;
        }

        $catalog_blocks = isset($_POST['pv_catalog_blocks']) && is_array($_POST['pv_catalog_blocks']) ? wp_unslash($_POST['pv_catalog_blocks']) : [];
        if (!empty($catalog_blocks)) {
            $result = PV_Config::save_catalog_from_blocks($catalog_blocks);
        } else {
            $type_rows = isset($_POST['pv_product_types']) && is_array($_POST['pv_product_types']) ? wp_unslash($_POST['pv_product_types']) : [];
            $field_rows = isset($_POST['pv_product_fields']) && is_array($_POST['pv_product_fields']) ? wp_unslash($_POST['pv_product_fields']) : [];
            $result = PV_Config::save_catalog_from_rows($type_rows, $field_rows);
        }
        $notice = !empty($result['success']) ? 'saved' : 'error';

        wp_safe_redirect(admin_url('admin.php?page=cart2chat&tab=catalog&pv_notice=' . $notice));
        exit;
    }

    public static function register_order_metabox(): void
    {
        add_meta_box(
            'pv_order_control',
            __('Cart2Chat - Control', PV_TEXT_DOMAIN),
            [__CLASS__, 'render_order_metabox'],
            'shop_order',
            'side',
            'high'
        );
    }

    public static function render_order_metabox(WP_Post $post): void
    {
        $stage = get_post_meta($post->ID, '_pv_order_stage', true) ?: 'nuevo';
        $notes = get_post_meta($post->ID, '_pv_internal_notes', true);
        $options = self::get_stage_options();

        wp_nonce_field('pv_order_control_nonce', 'pv_order_control_nonce_field');

        echo '<p><label for="pv_order_stage"><strong>' . esc_html__('Order stage', PV_TEXT_DOMAIN) . '</strong></label></p>';
        echo '<select name="pv_order_stage" id="pv_order_stage" style="width:100%;">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($stage, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<p style="margin-top:12px;"><label for="pv_internal_notes"><strong>' . esc_html__('Internal notes', PV_TEXT_DOMAIN) . '</strong></label></p>';
        echo '<textarea name="pv_internal_notes" id="pv_internal_notes" rows="5" style="width:100%;">' . esc_textarea((string) $notes) . '</textarea>';
    }

    public static function save_order_metabox(int $post_id): void
    {
        if (!isset($_POST['pv_order_control_nonce_field'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pv_order_control_nonce_field'])), 'pv_order_control_nonce')) {
            return;
        }

        if (!current_user_can('edit_shop_order', $post_id)) {
            return;
        }

        self::save_order_meta_values($post_id, $_POST);
    }

    public static function render_order_panel_hpos(WC_Order $order): void
    {
        $order_id = $order->get_id();
        $stage = get_post_meta($order_id, '_pv_order_stage', true) ?: 'nuevo';
        $notes = get_post_meta($order_id, '_pv_internal_notes', true);
        $options = self::get_stage_options();

        echo '<div class="order_data_column" style="width:100%;">';
        echo '<h4>Cart2Chat</h4>';
        echo '<p class="form-field"><label for="pv_order_stage_hpos">' . esc_html__('Order stage', PV_TEXT_DOMAIN) . '</label>';
        echo '<select name="pv_order_stage" id="pv_order_stage_hpos">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($stage, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';
        echo '<p class="form-field"><label for="pv_internal_notes_hpos">' . esc_html__('Internal notes', PV_TEXT_DOMAIN) . '</label>';
        echo '<textarea name="pv_internal_notes" id="pv_internal_notes_hpos" rows="3" style="width:100%;">' . esc_textarea((string) $notes) . '</textarea></p>';
        echo '</div>';

        wp_nonce_field('pv_order_control_nonce', 'pv_order_control_nonce_field');
    }

    public static function save_order_meta_hpos(int $order_id): void
    {
        if (!isset($_POST['pv_order_control_nonce_field'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pv_order_control_nonce_field'])), 'pv_order_control_nonce')) {
            return;
        }

        self::save_order_meta_values($order_id, $_POST);
    }

    private static function save_order_meta_values(int $order_id, array $source): void
    {
        $stage = isset($source['pv_order_stage']) ? sanitize_text_field((string) wp_unslash($source['pv_order_stage'])) : 'nuevo';
        if (!array_key_exists($stage, self::get_stage_options())) {
            $stage = 'nuevo';
        }

        $notes = isset($source['pv_internal_notes']) ? sanitize_textarea_field((string) wp_unslash($source['pv_internal_notes'])) : '';

        update_post_meta($order_id, '_pv_order_stage', $stage);
        update_post_meta($order_id, '_pv_internal_notes', $notes);
    }

    private static function get_default_whatsapp_form_fields(): array
    {
        return [
            ['key' => 'name', 'label' => __('Full name', PV_TEXT_DOMAIN), 'type' => 'text', 'required' => true],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
        ];
    }

    private static function get_usage_mode_options(): array
    {
        return [
            'both' => [
                'title' => __('Both options', PV_TEXT_DOMAIN),
                'description' => __('Enable product-page button and WhatsApp payment method at checkout.', PV_TEXT_DOMAIN),
            ],
            'product' => [
                'title' => __('Product page only', PV_TEXT_DOMAIN),
                'description' => __('Customer sends the order via WhatsApp from each product page, without checkout.', PV_TEXT_DOMAIN),
            ],
            'checkout' => [
                'title' => __('Checkout only', PV_TEXT_DOMAIN),
                'description' => __('Customer adds products to cart and finishes with "Order via WhatsApp" payment method.', PV_TEXT_DOMAIN),
            ],
        ];
    }

    private static function prepare_whatsapp_form_fields_for_admin($fields): array
    {
        $fields = is_array($fields) && !empty($fields) ? $fields : self::get_default_whatsapp_form_fields();
        $prepared = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $options_text = '';
            if (!empty($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as $value => $label) {
                    if (is_int($value)) {
                        $options_text .= $label . PHP_EOL;
                    } else {
                        $options_text .= $value . '|' . $label . PHP_EOL;
                    }
                }
                $options_text = trim($options_text);
            }

            $prepared[] = [
                'key'          => (string) ($field['key'] ?? ''),
                'label'        => (string) ($field['label'] ?? ''),
                'type'         => (string) ($field['type'] ?? 'text'),
                'required'     => !empty($field['required']),
                'options_text' => $options_text,
            ];
        }

        return $prepared;
    }

    private static function render_whatsapp_field_row($index, array $field, bool $is_existing = false): void
    {
        $is_select = (string) ($field['type'] ?? 'text') === 'select';
        ?>
        <tr>
            <td>
                <input class="pv-hidden-key" type="hidden" name="pv_settings[whatsapp_form_fields][<?php echo esc_attr((string) $index); ?>][key]" value="<?php echo esc_attr((string) ($field['key'] ?? '')); ?>">
                <input class="pv-label-input" type="text" name="pv_settings[whatsapp_form_fields][<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr((string) ($field['label'] ?? '')); ?>" style="width:100%;">
            </td>
            <td>
                <select class="pv-type-select" name="pv_settings[whatsapp_form_fields][<?php echo esc_attr((string) $index); ?>][type]" style="width:100%;">
                    <?php foreach (['text','email','tel','textarea','select'] as $type) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected((string) ($field['type'] ?? 'text'), $type); ?>><?php echo esc_html($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><label><input type="checkbox" name="pv_settings[whatsapp_form_fields][<?php echo esc_attr((string) $index); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?>> <?php echo esc_html__('Yes', PV_TEXT_DOMAIN); ?></label></td>
            <td class="pv-options-cell">
                <div class="pv-options-builder" style="<?php echo !$is_select ? 'display:none;' : ''; ?>">
                    <div class="pv-options-list"></div>
                    <button type="button" class="button pv-add-option"><?php echo esc_html__('Add option', PV_TEXT_DOMAIN); ?></button>
                    <input class="pv-options-hidden" type="hidden" name="pv_settings[whatsapp_form_fields][<?php echo esc_attr((string) $index); ?>][options_text]" value="<?php echo esc_attr((string) ($field['options_text'] ?? '')); ?>" <?php echo !$is_select ? 'disabled' : ''; ?>>
                </div>
                <span class="pv-options-na" style="<?php echo $is_select ? 'display:none;' : ''; ?>">N/A</span>
            </td>
            <td>
                <button class="button pv-move-up" type="button"><?php echo esc_html__('Up', PV_TEXT_DOMAIN); ?></button>
                <button class="button pv-move-down" type="button"><?php echo esc_html__('Down', PV_TEXT_DOMAIN); ?></button>
                <button class="button pv-remove-row" type="button"><?php echo esc_html__('Remove', PV_TEXT_DOMAIN); ?></button>
            </td>
        </tr>
        <?php
    }

    private static function get_whatsapp_field_row_template(): string
    {
        ob_start();
        self::render_whatsapp_field_row('__INDEX__', [
            'key' => '',
            'label' => '',
            'type' => 'text',
            'required' => false,
            'options_text' => '',
        ], false);
        return (string) ob_get_clean();
    }

    private static function normalize_field_key(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_\\- ]/', '', $normalized);
        $normalized = str_replace([' ', '-'], '_', (string) $normalized);
        $normalized = preg_replace('/_+/', '_', (string) $normalized);
        return trim((string) $normalized, '_');
    }

    private static function ensure_unique_field_key(string $key, array $used_keys): string
    {
        $base = $key !== '' ? $key : 'field';
        $candidate = $base;
        $n = 2;
        while (in_array($candidate, $used_keys, true)) {
            $candidate = $base . '_' . $n;
            $n++;
        }
        return $candidate;
    }

    private static function parse_options_text(string $options_text): array
    {
        $options_text = str_replace("\\n", "\n", $options_text);
        $lines = preg_split('/\\r\\n|\\r|\\n/', $options_text);
        if (!is_array($lines)) {
            return [];
        }

        $options = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '|') !== false) {
                [$value, $label] = array_pad(explode('|', $line, 2), 2, '');
                $value = self::normalize_field_key((string) $value);
                $label = sanitize_text_field((string) $label);
                if ($value !== '' && $label !== '') {
                    $options[$value] = $label;
                }
            } else {
                $label = sanitize_text_field($line);
                if ($label !== '') {
                    $options[] = $label;
                }
            }
        }

        return $options;
    }

    private static function prepare_catalog_field_rows(array $fields_by_type): array
    {
        $rows = [];
        foreach ($fields_by_type as $type_key => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $options_text = '';
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $value => $label) {
                        if (is_int($value)) {
                            $options_text .= $label . PHP_EOL;
                        } else {
                            $options_text .= $value . '|' . $label . PHP_EOL;
                        }
                    }
                    $options_text = trim($options_text);
                }

                $rows[] = [
                    'product_type' => (string) $type_key,
                    'key'          => (string) ($field['key'] ?? ''),
                    'label'        => (string) ($field['label'] ?? ''),
                    'type'         => (string) ($field['type'] ?? 'text'),
                    'required'     => !empty($field['required']),
                    'options_text' => $options_text,
                ];
            }
        }

        return $rows;
    }
}
