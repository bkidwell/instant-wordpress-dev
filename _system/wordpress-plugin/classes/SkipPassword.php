<?php
namespace GlumpNet\WordPress\InstantWordPressDev;

class SkipPassword {
    function __construct() {
        add_action('init', array(&$this, 'check_token'));
    }

    function check_token() {
        $token = $_GET['wpdev_login_token'];
        if(!$token) { return; }

        $this->try_login($token);

        $redir = $this->remove_url_param($_SERVER['REQUEST_URI'], 'wpdev_login_token');
        header("Location: $redir",TRUE,307);
        exit();
    }

    private function try_login($token) {
        $token = str_replace(
            array('-', '_'),
            array('+', '/'),
            $token
        );
        $encrypted = base64_decode($token);

        $cipher = new \Crypt_AES(CRYPT_AES_MODE_ECB);
        $cipher->setPassword(DB_PASSWORD, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000, 128 / 8);

        $parts = split('\|', $cipher->decrypt($encrypted));
        $d = new \DateTime($parts[0]);
        $user_name = $parts[1];

        $seconds = (new \DateTime())->format('U') - $d->format('U');
        if($seconds > 60) { return; } // expired

        if($user_name == 'anonymous') {
            wp_logout();
        } else {
            $user = get_user_by('login', $user_name);
            if(!$user) { return; } // not found

            wp_set_current_user($user->ID, $user_name);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user_name);
        }
    }

    private function remove_url_param($url, $param) {
        $parts = split('\?', $url, 2);
        if(count($parts) == 1) { return $url; }

        parse_str($parts[1], $query);
        unset($query[$param]);
        $query = http_build_query($query);

        if(!$query) { return $parts[0]; }

        return $parts[0] . '?' . $query;
    }
}