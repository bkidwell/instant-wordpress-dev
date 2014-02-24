<?php

namespace Controllers;

class Databases {
    public function index() {
        global $f3;
        $f3->set('menu', \Models\Menu::render('databases'));

        $link = \Util::pdo_instance('');
        $dbs = array();
        $skip = explode(',', 'information_schema,mysql,performance_schema,test');
        foreach($link->query('SHOW DATABASES') as $row) {
            if(!in_array($row['Database'], $skip)) {
                $dbs[] = $row['Database'];
            }
        }
        $f3->set('dbs', $dbs);

        echo \Template::instance()->render('databases.html');
    }
}
