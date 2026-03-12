<?php
/**
 * Plugin Name: Cart2Chat - WooCommerce WhatsApp Orders
 * Description: Custom product fields, personalized order flows, and WhatsApp order management for WooCommerce.
 * Version: 0.1.1
 * Author: Pinxel
 * Text Domain: cart2chat
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PV_PLUGIN_VERSION', '0.1.1');
define('PV_PLUGIN_FILE', __FILE__);
define('PV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PV_TEXT_DOMAIN', 'cart2chat');

require_once PV_PLUGIN_PATH . 'includes/class-pv-bootstrap.php';

register_activation_hook(PV_PLUGIN_FILE, ['PV_Bootstrap', 'activate']);

add_action('plugins_loaded', static function () {
    PV_Bootstrap::load_textdomain();
    PV_Bootstrap::init();
});
