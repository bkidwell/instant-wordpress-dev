<?php

# Writes key $1, value $2, and comment $3 to _data/config.sh

$site_path = realpath(dirname(__FILE__) . '/../..');
$config_file = "$site_path/_data/config.sh";

include("$site_path/_system/admin/lib/util.php");

$new_key = $argv[1];
$new_value = $argv[2];
if(array_key_exists(3, $argv)) {
	$new_comment = $argv[3];
} else {
	$new_comment = '';
}

\Util::set_config($config_file, $new_key, $new_value, $new_comment);
