<?php
namespace GlumpNet\WordPress\InstantWordPressDev;

class Mail {
    function __construct() {
        add_action('phpmailer_init', array(&$this, 'intercept_mail'));
    }

    function intercept_mail($obj) {
        $mail_script = $_SERVER["DOCUMENT_ROOT"] . '/_system/scripts/mail.php';
        if(file_exists($mail_script)) {
            $obj->Sendmail = $mail_script;
            $obj->Mailer = 'sendmail';
        }
    }
}
