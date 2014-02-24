<?php

namespace Models;

class Menu {
    public static function render($current) {
        global $f3;
        $o = array();
        foreach(array(
            'instances' => 'WordPress Instances',
            'databases' => 'Databases',
            'mail' => 'Mail',
        ) as $key => $value) {
            $active = ($current == $key) ? ' class="active"' : '';
            $path = $f3->get('root_uri') . '/' . $key;
            $o[] = "<li$active><a href=\"$path\">$value</a></li>\n";
            if($current == $key and $key != 'instances') {
                $f3->set('short_title', $value);
            }
        }
        $o[] = "<li><a href=\"/docs/instant-wordpress-dev.html\" target=\"blank\">Help</a></li>\n";
        return implode($o);
    }
}