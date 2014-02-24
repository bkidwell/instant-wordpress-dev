<?php

namespace Controllers;

class Instances {
    public function get_site_list() {
        global $f3;
        $exclude = array('_data', '_system', 'docs', 'nbproject');
        $list = array();
        $site_path = $f3->get('site_path');
        $d = dir($site_path);
        while(false !== ($entry = $d->read())) {
            if(
                !in_array($entry, $exclude) and
                is_dir("$site_path/$entry") and
                (substr($entry, 0, 1) != '.')
            ) {
                $list[] = $entry;
            }
        }
        $d->close();
        sort($list);
        return $list;
    }

    public function index($f3, $path, $do = '') {
        $f3->set('menu', \Models\Menu::render('instances'));

        if(!$do) {
            $do = $f3->get('POST.do');
        }
        if(!$f3->get('alert')) {
            $f3->set('alert', '');
            $f3->set('alert_class', '');
        }
        if($do == 'created') { $this->created(); }
        if($do == 'delete') { $this->delete(); }

        $list = $this->get_site_list();
        $f3->set('instances', $list);
        $f3->set('no_data', count($list) == 0);

        if($f3->get('first_run') == 1) {
            // FIRST RUN: Create 'sandbox' instance
            \Util::set_config(
                $f3->get('site_path') . '/_data/config.sh',
                'FIRST_RUN', 0,
                'this is the first run (need to create \'sandbox\' instance)'
            );
            $f3->set('first_run', 0);
            header('Location: /_system/admin/instances/create_sandbox');
        }

        echo \Template::instance()->render('instances.html');
    }

    public function wp_home() {
        global $f3;
        $instance_name = $f3->get('PARAMS.instance');
        $this->wp_go("/$instance_name/");
    }

    public function wp_dashboard() {
        global $f3;
        $instance_name = $f3->get('PARAMS.instance');
        $this->wp_go("/$instance_name/wp-admin/");
    }

    public function wp_go($url) {
        global $f3;
        $user = $f3->get('PARAMS.user');

        $cipher = new \Crypt_AES(CRYPT_AES_MODE_ECB);
        $cipher->setPassword($f3->get('db_password'), 'pbkdf2', 'sha1', 'phpseclib/salt', 1000, 128 / 8);
        $plaintext = date('c') . '|' . $user;
        echo "token: $plaintext<br>";

        $encrypted = $cipher->encrypt($plaintext);
        $base64url = str_replace(
            array('+', '/', '='),
            array('-', '_', '%3D'),
            base64_encode($encrypted)
        );

        $url = "$url?wpdev_login_token=$base64url";
        header("Location: $url",TRUE,307);
        exit();
    }

    public function create($f3, $path, $instance_name = '') {
        $f3->set('menu', \Models\Menu::render('instances'));

        if($instance_name == '') {
            $instance_name = $f3->get('POST.instance');
        }

        $list = $this->get_site_list();
        if(in_array($instance_name, $list)) {
            $f3->set('alert', "Error: “${instance_name}” already exists.");
            $f3->set('alert_class', 'alert-danger');
            return $this->index($f3, $path, 'index');
        }

        $wpzip = $f3->get('wp_installer_zip_path');
        $path = $f3->get('site_path') . "/$instance_name";
        mkdir($path);

        $zip = new \ZipArchive;
        if ($zip->open($wpzip) === TRUE) {
            $zip->extractTo($path);
            $zip->close();
        } else {
            $f3->set('alert', "Error: Couldn't extract Zip file “${wpzip}”.");
            $f3->set('alert_class', 'alert-danger');
            return $this->index($f3, $path, 'index');
        }

        $d = dir("$path/wordpress");
        while(false !== ($entry = $d->read())) {
            if($entry == '.' || $entry == '..') { continue; }
            rename("$path/wordpress/$entry", "$path/$entry");
        }
        $d->close();

        $db_host = $f3->get('db_host');
        $db_user = $f3->get('db_user');
        $db_pw = $f3->get('db_password');
        $db_port = $f3->get('db_port');
        file_put_contents(
            "$path/wp-config.php",
            str_ireplace(
                array(
                    "define('DB_NAME', 'database_name_here');",
                    "define('DB_USER', 'username_here');",
                    "define('DB_PASSWORD', 'password_here');",
                    "define('DB_HOST', 'localhost');"
                ),
                array(
                    "define('DB_NAME', 'wp_$instance_name');",
                    "define('DB_USER', '$db_user');",
                    "define('DB_PASSWORD', '$db_pw');",
                    "define('DB_HOST', '$db_host:$db_port');"
                ),
                file_get_contents("$path/wp-config-sample.php")
            )
        );

        $link = \Util::pdo_instance('');
        $link->exec('CREATE DATABASE wp_' . $instance_name);

        $f3->set(
            'install_url',
            "http://${_SERVER['SERVER_NAME']}:${_SERVER['SERVER_PORT']}" .
            "/$instance_name/wp-admin/install.php?step=2"
        );
        $f3->set('instance_name', $instance_name);
        $f3->set('admin_password', $db_pw);

        echo \Template::instance()->render('create.html');
    }

    public function create_sandbox($f3, $path) {
        return $this->create($f3, $path, 'sandbox');
    }

    public function created() {
        global $f3;
        $instance_name = $f3->get('POST.instance');

        $link = \Util::pdo_instance("wp_$instance_name");

        // set friendly permalink style
        $link->exec("
            UPDATE wp_options SET option_value='/%year%/%monthnum%/%postname%/'
            WHERE option_name='permalink_structure'
        ");

        // initialize users
        $link->exec("
            UPDATE wp_users
            SET display_name='Admin'
            WHERE ID=1;

            UPDATE wp_usermeta
            SET meta_value='Admin'
            WHERE user_id=1 AND meta_key='first_name';\

            UPDATE wp_usermeta
            SET meta_value='0'
            WHERE user_id=1 AND meta_key='show_welcome_panel';

            INSERT INTO wp_usermeta (meta_key, meta_value)
            VALUES ('metaboxhidden_dashboard',
            'a:3:{i:0;s:24:\"dashboard_incoming_links\";i:1;s:17:\"dashboard_primary\";i:2;s:19:\"dashboard_secondary\";}')
        "); //TODO: use serialize()

        foreach(array(
            array('Editor', 7),
            array('Author', 2),
            array('Contributor', 1),
            array('Subscriber', 0)
        ) as $user) {
            $username = $user[0];
            $level = $user[1];
            $lowercase = strtolower($username);
            $email = "$lowercase@example.com";
            $ts = date('Y-m-d H:i:s');

            $link->exec("
                INSERT INTO wp_users (
                user_login, user_pass, user_nicename, user_email, user_registered,
                user_activation_key, user_status, display_name
                ) VALUES (
                '$lowercase', '', '$lowercase', '$email', '$ts',
                '', 0, '$username'
                )
            ");
            foreach($link->query('SELECT Max(ID) ID FROM wp_users') as $row) {
                $id = $row['ID'];
            }
            foreach(array(
                'first_name' => $username,
                'last_name' => '',
                'nickname' => $lowercase,
                'description' => '',
                'rich_editing' => 'true',
                'comment_shortcuts' => 'false',
                'admin_color' => 'fresh',
                'use_ssl' => '0',
                'show_admin_bar_front' => 'true',
                'wp_capabilities' => 'a:1:{s:' . strlen($lowercase) . ':"' . $lowercase . '";b:1;}',
                'wp_user_level' => $level,
                'dismissed_wp_pointers' => 'wp330_toolbar,wp330_saving_widgets,wp340_choose_image_from_library,wp340_customize_current_theme_link,wp350_media',
                'metaboxhidden_dashboard' => 'a:3:{i:0;s:24:"dashboard_incoming_links";i:1;s:17:"dashboard_primary";i:2;s:19:"dashboard_secondary";}'
            ) as $key => $value) {
                $value = $link->quote($value);
                $link->exec("
                    INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
                    VALUES ($id, '$key', $value)
                ");
            }
        }

        $link->exec("
            UPDATE wp_users SET user_pass=MD5('{$f3->get('db_password')}')
        ");

        // install plugin
        \Util::copyr(
            $f3->get('site_path') . '/_system/wordpress-plugin',
            $f3->get('site_path') . '/' . $instance_name . '/wp-content/plugins/instant-wordpress-dev'
        );
        foreach($link->query(
            "SELECT option_value FROM wp_options WHERE option_name='active_plugins'") as $row
        ) {
            $plugins = $row['option_value'];
        }
        $plugins = unserialize($plugins);
        $plugins_a[] = 'instant-wordpress-dev/instant-wordpress-dev.php';
        $plugins = $link->quote(serialize($plugins_a));
        $link->exec("
            UPDATE wp_options SET option_value=$plugins WHERE option_name='active_plugins'
        ");

        if($instance_name != 'sandbox') {
            $f3->set('alert', "Created WordPress instance “${instance_name}”.");
        } else {
            $f3->set('alert', "Created WordPress initial instance “${instance_name}” to get you started.");
        }
        $f3->set('alert_class', 'alert-success');
    }

    public function delete() {
        global $f3;
        $instance_name = $f3->get('POST.instance');

        $path = $f3->get('site_path') . "/$instance_name";
        $this->unlinkRecursive($path);

        $link = \Util::pdo_instance('');
        $link->exec('DROP DATABASE IF EXISTS wp_' . $instance_name);

        $f3->set('alert', "WordPress instance “${instance_name}” deleted.");
        $f3->set('alert_class', 'alert-success');
    }

    private function unlinkRecursive($dir) {
        if(!$dh = @opendir($dir)) {
            return;
        }
        while(false !== ($entry = readdir($dh))) {
            if($entry == '.' || $entry == '..') { continue; }
            if(!@unlink($dir . '/' . $entry)) {
                $this->unlinkRecursive($dir . '/' . $entry, true);
            }
        }
        closedir($dh);
        @rmdir($dir);
        return;
    }
}
