<?php
/**
 * Plugin Name: FunnelKit Lite
 * Description: Sales funnels, lead capture and a React admin with offline mock checkout.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
define('FUNNELKIT_FILE', __FILE__);
require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-plugin.php';
register_activation_hook(__FILE__, ['FunnelKit\\Store', 'activate']);
add_action('plugins_loaded', static function () { (new FunnelKit\Plugin())->boot(); });
