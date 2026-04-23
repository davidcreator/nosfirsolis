<?php
/**
 * Plugin Name: Nosfir Solis Bridge
 * Plugin URI: https://nosfir.example.com
 * Description: Recebe publicacoes do Solis e cria posts no WordPress via REST API.
 * Version: 0.1.0
 * Author: Nosfir
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nosfir-solis-bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NOSFIR_SOLIS_BRIDGE_VERSION', '0.1.0');
define('NOSFIR_SOLIS_BRIDGE_PLUGIN_FILE', __FILE__);
define('NOSFIR_SOLIS_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOSFIR_SOLIS_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once NOSFIR_SOLIS_BRIDGE_PLUGIN_DIR . 'src/class-nosfir-solis-bridge-plugin.php';

register_activation_hook(__FILE__, ['Nosfir_Solis_Bridge_Plugin', 'activate']);
register_uninstall_hook(__FILE__, ['Nosfir_Solis_Bridge_Plugin', 'uninstall']);

Nosfir_Solis_Bridge_Plugin::instance();
