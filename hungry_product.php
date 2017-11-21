<?php

namespace Hungry;

/*
Plugin Name: Hungry Product
Description: Product Feeds
Version: 1.0.2
Author: Jaco Kok
License: GPL2
*/

if (!defined('ABSPATH')) exit;

define('Hungry\__ASSETS__', __DIR__ . '/assets');
define('Hungry\__BASEFILE__', __FILE__);

// Autoloader
require_once __DIR__ . '/src/Hungry/Tool/ClassLoader.php';

$loader = new Tool\ClassLoader();
$loader->addPrefix('Hungry', __DIR__ . '/src');
$loader->register();

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', get_option('active_plugins'))) {

    // Admin
    if(is_admin()) {
        $admin = new Controller\Admin();
        $admin->run();
    }

    // Feed 
    $feed = new Controller\Feed();
    $feed->run();
}
