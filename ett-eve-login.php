<?php
/*
Plugin Name: EVE Trade Tools EVE Login
Description: Register and log in using EVE Online SSO. Accounts are created automatically on first login. Existing WordPress users can also link their EVE character to enable SSO login going forward.
Version: 1.0.1
Author: C4813
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

define('ETT_EL_VERSION', '1.0.1');
define('ETT_EL_PATH',    plugin_dir_path(__FILE__));
define('ETT_EL_URL',     plugin_dir_url(__FILE__));

require_once ETT_EL_PATH . 'includes/class-ett-el-crypto.php';
require_once ETT_EL_PATH . 'includes/class-ett-el-oauth.php';
require_once ETT_EL_PATH . 'includes/class-ett-el-admin.php';
require_once ETT_EL_PATH . 'includes/class-ett-el-tab.php';

add_action('plugins_loaded', function () {
    ETT_EL_OAuth::init();
    ETT_EL_Admin::init();
    ETT_EL_Tab::init();
});
