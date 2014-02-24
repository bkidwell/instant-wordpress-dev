<?php
define('DB_LATEST_VERSION', 1);

require_once('lib/Crypt/Hash.php');
require_once('lib/Crypt/Rijndael.php');
require_once('lib/Crypt/AES.php');

$f3=require('lib/base.php');

$script_directory = substr($_SERVER['SCRIPT_FILENAME'], 0, strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
$f3->set('site_path', dirname(dirname($script_directory)));
$f3->set('admin_path', $script_directory);
$f3->set('LOGS', "$script_directory/app/logs/");
$f3->set('UI', "$script_directory/app/views/");
$f3->set('TEMP', "$script_directory/tmp/");
$f3->set('CACHE', "$script_directory/tmp/cache/");
$f3->set('AUTOLOAD', "$script_directory/app/; $script_directory/lib/");
$f3->set('DEBUG', 3);

$config_sh = \Util::get_config_sh($f3->get('site_path') . '/_data/config.sh');

$f3->set(
    'wp_installer_zip_path',
    $f3->get('site_path') . '/_data/installers/' . $config_sh['WP_VERSION']
);

$root_uri = substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/'));
$f3->set('root_uri', $root_uri);
$f3->set('first_run', $config_sh['FIRST_RUN']);
$f3->set('db_host', '127.0.0.1');
$f3->set('db_port', $_SERVER['SERVER_PORT'] + 1);
$f3->set('db_user', 'root');
$f3->set('db_password', $config_sh['DB_PASSWORD']);
$f3->set('db_version', $config_sh['DB_VERSION']);

if(DB_LATEST_VERSION != $f3->get('db_version')) {
    \Util::migrate();
}

$f3->route("GET $root_uri/", 'Controllers\Main->display');
$f3->route("GET|POST $root_uri/instances", 'Controllers\Instances->index');
$f3->route("GET $root_uri/instances", 'Controllers\Instances->index');
$f3->route("POST $root_uri/instances/create", 'Controllers\Instances->create');
$f3->route("GET $root_uri/instances/create_sandbox", 'Controllers\Instances->create_sandbox');
$f3->route("GET $root_uri/instances/@action/@instance/@user", 'Controllers\Instances->@action');
$f3->route("GET $root_uri/databases", 'Controllers\Databases->index');
$f3->route("GET $root_uri/mail", 'Controllers\Mail->index');
$f3->route("POST $root_uri/mail", 'Controllers\Mail->post');

$f3->set('head_extra', '');
$f3->set('short_title', '');

$f3->run();
