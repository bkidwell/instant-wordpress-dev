<?php

class Util {
    public static function pdo_instance($db_name = '', $throw_errors = true) {
        global $f3;
        $conn =
            'mysql:host=' . $f3->get('db_host') .
            ';port=' . $f3->get('db_port');
        if($db_name) {
            $conn .= ';dbname=' . $db_name;
        }
        $link = new \PDO($conn, $f3->get('db_user'), $f3->get('db_password'));
        if($throw_errors) {
            $link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        return $link;
    }

    public static function get_config_sh($path) {
        $lines = file($path);
        $config = array();
        foreach($lines as $line) {
            $a = explode("=", $line, 2);
            if(count($a) == 2) {
                $key = trim($a[0]);
                $b = explode("#", $a[1]);
                $value = trim($b[0]);
                $config[$key] = $value;
            }
        }
        return $config;
    }

    public static function migrate() {
        global $f3;
        $migrations = $f3->get('site_path') . '/_system/migrations';
        $migrated = false;

        if($f3->get('db_version') == 'FIXME') {
            // New installation
            $link = \Util::pdo_instance();
            $link->exec(file_get_contents("$migrations/latest-schema.sql"));
            $migrated = true;
        }

        // LATER: if database schema changes, put more code in here to migrate
        // from old versions.

        if($migrated) {
            \Util::set_config(
                $f3->get('site_path') . '/_data/config.sh',
                'DB_VERSION', DB_LATEST_VERSION,
                'database migration version'
            );
            $f3->set('db_version', DB_LATEST_VERSION);
        }
    }

    public static function set_config($config_file, $new_key, $new_value, $new_comment) {
        $lines = file($config_file);

        $items = array();
        $config = array();
        foreach($lines as $line) {
            $a = explode("=", $line, 2);
            if(count($a) == 2) {
                $key = trim($a[0]);
                $b = explode("#", $a[1]);
                if(count($b) == 1) { $b[1] = ''; }
                $value = trim($b[0]);
                $comment = trim($b[1]);

                if($key == $new_key) {
                    $key = $new_key;
                    $value = $new_value;
                    if($new_comment) {
                        $comment = $new_comment;
                    }
                }

                $items[] = array(
                    'key' => $key,
                    'value' => $value,
                    'comment' => $comment
                );
                $config[$key] = $value;
            }
        }
        if(!array_key_exists($new_key, $config)) {
            $items[] = array(
                'key' => $new_key,
                'value' => $new_value,
                'comment' => $new_comment
            );
            $config[$new_key] = $new_value;
        }

        $width = 0;
        foreach($items as $item) {
            $new_width = strlen($item['key']) + strlen($item['value']) + 2;
            if($new_width > $width) { $width = $new_width; }
        }
        $lines = array();
        foreach($items as $item) {
            $line = $item['key'] . '=' . $item['value'];
            $line .= str_repeat(' ', $width - strlen($line));
            $line .= "# " . $item['comment'];
            $lines[] = $line . "\n";
        }

        file_put_contents($config_file, $lines);
    }

    /**
     * Copy a file, or recursively copy a folder and its contents
     *
     * @author      Aidan Lister <aidan@php.net>
     * @version     1.0.1
     * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
     * @param       string   $source    Source path
     * @param       string   $dest      Destination path
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    public static function copyr($source, $dest) {
        // Check for symlinks
        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }

        // Make destination directory
        if (!is_dir($dest)) {
            mkdir($dest);
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            \Util::copyr("$source/$entry", "$dest/$entry");
        }

        // Clean up
        $dir->close();
        return true;
    }

}