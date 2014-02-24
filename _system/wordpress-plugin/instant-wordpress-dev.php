<?php
/*
Plugin Name: InstantWordPressDev Support Plugin
Plugin URI: http://www.glump.net
Description: WordPress enhancements required for InstantWordPressDev: 1) Passwordless login from global /admin screen. 2) Remove "http://host:port" prefix from URLs when saving posts.
Version: 1.0
Author: Brendan Kidwell
Author URI: http://www.glump.net
License: GPL 3
*/

namespace GlumpNet\WordPress\InstantWordPressDev;

define(__NAMESPACE__ . '\\PLUGIN_DIR', dirname(__FILE__) . '/');
define(__NAMESPACE__ . '\\PLUGIN_URL', plugins_url(basename(dirname(__FILE__))) . '/');

spl_autoload_register(__NAMESPACE__ . '\\autoload');
function autoload($cls) {
    $c = ltrim($cls, '\\'); $l = strlen(__NAMESPACE__);
    if(strncmp($c, __NAMESPACE__, $l) !== 0) { return; }
    $c = str_replace('\\', '/', substr($c, $l)); $f = PLUGIN_DIR . 'classes' . $c . '.php';
    if(!file_exists($f)) {
        ob_clean(); echo "<br><br><pre><b>Error loading class $cls</b>\n"; debug_print_backtrace(); die();
    }
    require_once($f);
}

require_once('lib/Crypt/Hash.php');
require_once('lib/Crypt/Rijndael.php');
require_once('lib/Crypt/AES.php');

new SkipPassword();
new Mail();