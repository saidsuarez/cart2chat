<?php

if (!defined('ABSPATH')) {
    exit;
}

class PV_Config
{
    private const OPTION_KEY = 'pv_catalog_config';

    private static function t(string $text): string
    {
        return __($text, PV_TEXT_DOMAIN);
    }

    public static function get_default_catalog(): array
    {
        return [
            'product_types' => [
                'registro_veterinario'    => self::t('Veterinary Record'),
                'bitacora_lectura'        => self::t('Reading Journal'),
                'poster'                  => self::t('Poster'),
                'bolso_personalizado'     => self::t('Custom Tote Bag'),
                'camiseta_personalizada'  => self::t('Custom T-Shirt'),
                'boton'                   => self::t('Button'),
                'pin'                     => self::t('Pin'),
            ],
            'product_fields' => [
                'registro_veterinario' => [
                    ['key' => 'nombre_mascota', 'label' => self::t('Pet name'), 'type' => 'text', 'required' => true],
                    ['key' => 'portada_origen', 'label' => self::t('How do you want to define the cover?'), 'type' => 'select', 'required' => true, 'options' => ['foto_final' => self::t('Upload the photo for the cover'), 'referencia_ia' => self::t('Upload a reference image so Cart2Chat can create the cover with AI')]],
                    ['key' => 'foto_portada', 'label' => self::t('Cover photo'), 'type' => 'file', 'required' => true, 'required_if' => ['portada_origen' => 'foto_final']],
                    ['key' => 'imagen_referencia', 'label' => self::t('Reference image'), 'type' => 'file', 'required' => true, 'required_if' => ['portada_origen' => 'referencia_ia']],
                    ['key' => 'estilo_portada_ia', 'label' => self::t('Cover style'), 'type' => 'select', 'required' => true, 'required_if' => ['portada_origen' => 'referencia_ia'], 'options' => ['realista' => self::t('Realistic'), 'cute' => self::t('Cute'), 'anime' => self::t('Anime'), 'disney_pixar' => self::t('Disney Pixar'), 'crochet' => self::t('Crochet')]],
                    ['key' => 'escenario_portada', 'label' => self::t('Cover scene or ideas'), 'type' => 'textarea', 'required' => true, 'required_if' => ['portada_origen' => 'referencia_ia']],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
                'bitacora_lectura' => [
                    ['key' => 'nombre_propietario', 'label' => self::t('Owner name'), 'type' => 'text', 'required' => true],
                    ['key' => 'nivel_lector', 'label' => self::t('Reading level'), 'type' => 'select', 'required' => true, 'options' => [self::t('Beginner'), self::t('Intermediate'), self::t('Advanced')]],
                    ['key' => 'color_portada', 'label' => self::t('Cover color'), 'type' => 'text', 'required' => false],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
                'poster' => [
                    ['key' => 'tamano', 'label' => self::t('Size'), 'type' => 'select', 'required' => true, 'options' => ['A4', 'A3', '50x70 cm', '70x100 cm']],
                    ['key' => 'orientacion', 'label' => self::t('Orientation'), 'type' => 'select', 'required' => true, 'options' => [self::t('Vertical'), self::t('Horizontal')]],
                    ['key' => 'frase', 'label' => self::t('Main phrase or text'), 'type' => 'text', 'required' => false],
                    ['key' => 'acabado', 'label' => self::t('Finish'), 'type' => 'select', 'required' => false, 'options' => [self::t('Matte'), self::t('Glossy')]],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
                'bolso_personalizado' => [
                    ['key' => 'material', 'label' => self::t('Material'), 'type' => 'select', 'required' => true, 'options' => [self::t('Canvas'), self::t('Cotton'), self::t('Polyester')]],
                    ['key' => 'color_bolso', 'label' => self::t('Bag color'), 'type' => 'text', 'required' => true],
                    ['key' => 'tipo_estampado', 'label' => self::t('Print type'), 'type' => 'select', 'required' => true, 'options' => [self::t('Text'), self::t('Image'), self::t('Mixed')]],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
                'camiseta_personalizada' => [
                    ['key' => 'talla', 'label' => self::t('Size'), 'type' => 'select', 'required' => true, 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                    ['key' => 'color_camiseta', 'label' => self::t('Shirt color'), 'type' => 'text', 'required' => true],
                    ['key' => 'tipo_estampado', 'label' => self::t('Print type'), 'type' => 'select', 'required' => true, 'options' => [self::t('Front'), self::t('Back'), self::t('Both sides')]],
                    ['key' => 'nombre_estampado', 'label' => self::t('Text for print'), 'type' => 'text', 'required' => false],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
                'boton' => [
                    ['key' => 'diametro_mm', 'label' => self::t('Diameter (mm)'), 'type' => 'number', 'required' => true],
                    ['key' => 'cantidad', 'label' => self::t('Quantity'), 'type' => 'number', 'required' => true],
                    ['key' => 'acabado', 'label' => self::t('Finish'), 'type' => 'select', 'required' => false, 'options' => [self::t('Matte'), self::t('Glossy')]],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
                'pin' => [
                    ['key' => 'tipo_pin', 'label' => self::t('Pin type'), 'type' => 'select', 'required' => true, 'options' => [self::t('Metal'), self::t('Acrylic'), 'PVC']],
                    ['key' => 'ancho_cm', 'label' => self::t('Width (cm)'), 'type' => 'number', 'required' => true],
                    ['key' => 'alto_cm', 'label' => self::t('Height (cm)'), 'type' => 'number', 'required' => true],
                    ['key' => 'cantidad', 'label' => self::t('Quantity'), 'type' => 'number', 'required' => true],
                    ['key' => 'comentarios', 'label' => self::t('Additional comments'), 'type' => 'textarea', 'required' => false],
                ],
            ],
            'payment_methods' => [
                'transferencia_bancolombia' => self::t('Bank transfer (Bancolombia)'),
                'llave'                     => self::t('Llave'),
                'nequi'                     => self::t('Nequi'),
                'daviplata'                 => self::t('Daviplata'),
                'link_pago_tarjeta'         => self::t('Payment link (credit card)'),
            ],
        ];
    }

    public static function get_catalog_config(): array
    {
        $defaults = self::get_default_catalog();
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            return $defaults;
        }

        $product_types = (array) (array_key_exists('product_types', $saved) && is_array($saved['product_types']) ? $saved['product_types'] : $defaults['product_types']);
        $product_fields_raw = (array) (array_key_exists('product_fields', $saved) && is_array($saved['product_fields']) ? $saved['product_fields'] : $defaults['product_fields']);
        $payment_methods = (array) (array_key_exists('payment_methods', $saved) && is_array($saved['payment_methods']) ? $saved['payment_methods'] : $defaults['payment_methods']);

        $product_fields = [];
        foreach ($product_types as $type_key => $type_label) {
            $type_key = (string) $type_key;
            $product_fields[$type_key] = !empty($product_fields_raw[$type_key]) && is_array($product_fields_raw[$type_key]) ? array_values($product_fields_raw[$type_key]) : [];
        }

        return [
            'product_types'  => $product_types,
            'product_fields' => $product_fields,
            'payment_methods'=> $payment_methods,
        ];
    }

    public static function get_product_types(): array
    {
        return self::get_catalog_config()['product_types'];
    }

    public static function get_fields_for_type(string $type): array
    {
        $config = self::get_catalog_config();
        if (!empty($config['product_fields'][$type]) && is_array($config['product_fields'][$type])) {
            return $config['product_fields'][$type];
        }

        $first = array_key_first($config['product_fields']);
        return $first ? (array) $config['product_fields'][$first] : [];
    }

    public static function get_payment_methods(): array
    {
        return self::get_catalog_config()['payment_methods'];
    }

    public static function save_catalog_from_json(string $product_types_json, string $product_fields_json, string $payment_methods_json): array
    {
        $types = json_decode($product_types_json, true);
        $fields = json_decode($product_fields_json, true);
        $payments = json_decode($payment_methods_json, true);

        if (!is_array($types) || empty($types)) {
            return ['success' => false, 'message' => __('Invalid JSON in product types.', PV_TEXT_DOMAIN)];
        }
        if (!is_array($fields) || empty($fields)) {
            return ['success' => false, 'message' => __('Invalid JSON in fields by type.', PV_TEXT_DOMAIN)];
        }
        if (!is_array($payments) || empty($payments)) {
            return ['success' => false, 'message' => __('Invalid JSON in payment methods.', PV_TEXT_DOMAIN)];
        }

        update_option(self::OPTION_KEY, [
            'product_types' => $types,
            'product_fields' => $fields,
            'payment_methods' => $payments,
        ]);

        return ['success' => true, 'message' => __('Configuration saved successfully.', PV_TEXT_DOMAIN)];
    }

    public static function save_catalog_from_rows(array $type_rows, array $field_rows): array
    {
        $types = [];
        $used_type_keys = [];
        foreach ($type_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
            $key = self::normalize_key($label);
            $key = self::ensure_unique_key($key, $used_type_keys, 'type');

            if ($key === '' || $label === '') {
                continue;
            }
            $types[$key] = $label;
            $used_type_keys[] = $key;
        }

        $fields_by_type = [];
        $used_field_keys_by_type = [];
        foreach ($field_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $product_type = isset($row['product_type']) ? self::normalize_key((string) $row['product_type']) : '';
            if ($product_type === '' || !isset($types[$product_type])) {
                continue;
            }

            $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
            $field_key = self::normalize_key($label);
            if (!isset($used_field_keys_by_type[$product_type])) {
                $used_field_keys_by_type[$product_type] = [];
            }
            $field_key = self::ensure_unique_key($field_key, $used_field_keys_by_type[$product_type], 'field');
            $field_type = isset($row['type']) ? sanitize_key((string) $row['type']) : 'text';
            $required = !empty($row['required']);
            $options_text = isset($row['options_text']) ? (string) $row['options_text'] : '';

            if ($label === '' || $field_key === '') {
                continue;
            }

            if (!in_array($field_type, ['text', 'number', 'textarea', 'select', 'file', 'email', 'tel'], true)) {
                $field_type = 'text';
            }

            $field = [
                'key'      => $field_key,
                'label'    => $label,
                'type'     => $field_type,
                'required' => $required,
            ];

            if ($field_type === 'select') {
                $options = self::parse_options_text($options_text);
                if (!empty($options)) {
                    $field['options'] = $options;
                }
            }

            $fields_by_type[$product_type][] = $field;
            $used_field_keys_by_type[$product_type][] = $field_key;
        }

        foreach (array_keys($types) as $type_key) {
            if (!isset($fields_by_type[$type_key])) {
                $fields_by_type[$type_key] = [];
            }
        }

        $current = self::get_catalog_config();
        update_option(self::OPTION_KEY, [
            'product_types'  => $types,
            'product_fields' => $fields_by_type,
            'payment_methods'=> $current['payment_methods'],
        ]);

        return ['success' => true, 'message' => __('Configuration saved successfully.', PV_TEXT_DOMAIN)];
    }

    public static function save_catalog_from_blocks(array $blocks): array
    {
        $types = [];
        $fields_by_type = [];
        $used_type_keys = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type_label = isset($block['type_label']) ? sanitize_text_field((string) $block['type_label']) : '';
            if ($type_label === '') {
                continue;
            }
            $type_key = self::normalize_key($type_label);
            $type_key = self::ensure_unique_key($type_key, $used_type_keys, 'type');
            $types[$type_key] = $type_label;
            $used_type_keys[] = $type_key;

            $raw_fields = isset($block['fields']) && is_array($block['fields']) ? $block['fields'] : [];
            $used_field_keys = [];
            $temp_fields = [];

            foreach ($raw_fields as $field_index => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';
                if ($label === '') {
                    continue;
                }

                $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : 'text';
                if (!in_array($field_type, ['text', 'number', 'textarea', 'select', 'file', 'email', 'tel'], true)) {
                    $field_type = 'text';
                }

                $field_key = self::normalize_key($label);
                $field_key = self::ensure_unique_key($field_key, $used_field_keys, 'field');
                $used_field_keys[] = $field_key;

                $item = [
                    'key'      => $field_key,
                    'label'    => $label,
                    'type'     => $field_type,
                    'required' => !empty($field['required']),
                ];

                if ($field_type === 'select') {
                    $options_text = isset($field['options_text']) ? (string) $field['options_text'] : '';
                    $options = self::parse_options_text($options_text);
                    if (!empty($options)) {
                        $item['options'] = $options;
                    }
                }

                $temp_fields[] = [
                    'index'            => (string) $field_index,
                    'field'            => $item,
                    'depends_on_index' => isset($field['depends_on_index']) ? (string) $field['depends_on_index'] : '',
                    'depends_on_value' => isset($field['depends_on_value']) ? sanitize_text_field((string) $field['depends_on_value']) : '',
                ];
            }

            $index_to_key = [];
            foreach ($temp_fields as $entry) {
                $index_to_key[$entry['index']] = (string) $entry['field']['key'];
            }

            $type_fields = [];
            foreach ($temp_fields as $entry) {
                $field = $entry['field'];
                $dep_index = $entry['depends_on_index'];
                $dep_value = $entry['depends_on_value'];

                if ($dep_index !== '' && $dep_value !== '' && isset($index_to_key[$dep_index])) {
                    $controller_key = (string) $index_to_key[$dep_index];
                    $field['required_if'] = [$controller_key => $dep_value];
                }

                $type_fields[] = $field;
            }

            $fields_by_type[$type_key] = $type_fields;
        }

        $current = self::get_catalog_config();
        update_option(self::OPTION_KEY, [
            'product_types'  => $types,
            'product_fields' => $fields_by_type,
            'payment_methods'=> $current['payment_methods'],
        ]);

        return ['success' => true, 'message' => __('Configuration saved successfully.', PV_TEXT_DOMAIN)];
    }

    public static function reset_to_defaults(): void
    {
        update_option(self::OPTION_KEY, self::get_default_catalog());
    }

    private static function normalize_key(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_\\- ]/', '', $normalized);
        $normalized = str_replace([' ', '-'], '_', (string) $normalized);
        $normalized = preg_replace('/_+/', '_', (string) $normalized);

        return trim((string) $normalized, '_');
    }

    private static function ensure_unique_key(string $key, array $used_keys, string $prefix): string
    {
        $base = $key !== '' ? $key : $prefix;
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
                $value = self::normalize_key((string) $value);
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
}
