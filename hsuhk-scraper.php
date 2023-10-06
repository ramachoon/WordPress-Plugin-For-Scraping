<?php

/**

 * Plugin Name

 *

 * @package           HSUHK Scraping Tool

 * @author            Software Engineer

 * @copyright         2021 SoftwareEngineer

 * @license           GPL-2.0-or-later

 *

 * @wordpress-plugin

 * Plugin Name:       HSUHK Scraping Tool
 * Plugin URI:        https://example.com/hsuskscrape 

 * Description:       Tool for scrapping new from https://scm.hsu.edu.hk/us/news/news

 * Version:           1.0.0

 * Requires at least: 5.4

 * Requires PHP:      7.2

 * Author:            Software Engineer

 * Author URI:        https://example.com

 * Text Domain:       hsuskscrape

 * License:           GPL v2 or later

 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt

 */

// Prohibit direct script loading.

defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

define('HS_TABLE', 'hs_descriptions');

define('HS_ROOT_PATH', plugin_dir_path(__FILE__));

define('HS_ROOT_URL', plugin_dir_url(__FILE__));

if (!defined('HS_BASENAME')) {

    define('HS_BASENAME', plugin_basename(__FILE__));

}


function hsscrape_load() {

    require_once(HS_ROOT_PATH . 'hs_functions.php');

	if (is_admin()) {

        require_once(HS_ROOT_PATH . 'hs_admin.php');

    }

}


/********

 * Hooks *

 ********/

 register_activation_hook(__FILE__, 'hsActivation');

 register_deactivation_hook(__FILE__, 'hsDeactivation');
 
 register_uninstall_hook(__FILE__, 'hsUninstall');
 
 
 
 hsscrape_load();
 
 ?>
 