<?php

namespace Controllers;

class Mail {
    public function index() {
        global $f3;
        $f3->set('menu', \Models\Menu::render('mail'));

        $link = \Util::pdo_instance('wpdev');
        $emails = array();
        foreach($link->query('
            SELECT date, `from`, `to`, subject, body FROM mail ORDER BY date
        ') as $row) {
            $row['body'] = nl2br($row['body']);
            $emails[] = $row;
        }
        // $link->free();
        $f3->set('emails', $emails);
        $f3->set('message', count($emails) == 0 ? '<p><em>No messages</em></p>' : '');
        $f3->set('have_data', count($emails) > 0);

        echo \Template::instance()->render('mail.html');
    }

    public function post() {
        global $f3;

        $link = \Util::pdo_instance('wpdev');
        $link->exec('
            TRUNCATE mail
        ');

        return $this->index();
    }
}
