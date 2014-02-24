#!/usr/bin/env php
<?php

$site_path = realpath(dirname(__FILE__) . '/../..');

// Get database instance

function get_config_sh($path) {
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

function pdo_instance($throw_errors = true) {
    global $site_path;
    $config_sh = get_config_sh("$site_path/_data/config.sh");
    $conn = 'mysql:host=127.0.0.1;port=' . $config_sh['MYSQL_PORT'] .
        ';dbname=wpdev';
    $link = new \PDO($conn, 'root', $config_sh['DB_PASSWORD']);
    if($throw_errors) {
        $link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
    return $link;
}
$link = pdo_instance();

// Read message from stdin

$in = fopen('php://stdin', 'r');
$message = '';
while(!feof($in)){
    $message .= fgets($in, 4096);
}

// Split into headers, body

$parts = split("\n\n", $message, 2);
$header_text = $parts[0];
$body = $parts[1];

// Parse To, From, Subject

$headers = array();
$pending = array();
foreach(split("\n", $header_text . "\n-") as $current) {
    if(substr($current, 0, 1) == ' ') {
        # continuation
        $pending[1] .= " " . trim($current);
    } else {
        if(count($pending)) {
            # to, from, subject, date
            if(in_array($pending[0], array('To', 'From', 'Subject'))) {
                $headers[$pending[0]] = $pending[1];
            }
        }
        if($current != '-') {
            # new key
            $parts = split(":", $current, 2);
            $parts[0] = trim($parts[0]);
            $parts[1] = trim($parts[1]);
            $pending = $parts;
        }
    }
}

// Save to database

$sql =
    "INSERT INTO mail (`from`, `to`, subject, headers, body) VALUES (" .
    $link->quote(substr($headers['From'], 0, 200)) . ", " .
    $link->quote(substr($headers['To'], 0, 200)) . ", " .
    $link->quote(substr($headers['Subject'], 0, 200)) . ", " .
    $link->quote($header_text) . ", " .
    $link->quote($body).
    ");";
echo("\n" . $sql . "\n\n");
$link->exec($sql);
