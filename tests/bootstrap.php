<?php
// Run against a disposable local WordPress database, never production.
$path = getenv('WP_ROOT') ?: '/var/www/html';
require_once $path . '/wp-load.php';
if (!class_exists('FunnelKit\\Store')) { require_once dirname(__DIR__) . '/funnelkit-lite.php'; }
FunnelKit\Store::activate();
