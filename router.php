<?php

function wpdev_router() {
    $startsWith = function($haystack, $needle) {
        return strncasecmp($haystack, $needle, strlen($needle)) == 0;
    };
    $notAllowed = function() {
        http_response_code(401);
        echo "Not authorized.";
        exit();
    };

    $uri = explode('?', $_SERVER['REQUEST_URI'], 2);
    $path = $uri[0];
    if(!isset($uri[1])) { $uri[1] = ''; }
    list($path, $query) = $uri;

    if($startsWith($path, '/_data')) { $notAllowed(); }
    if($startsWith($path, '/_system')) {
        if(!(
            $startsWith($path, '/_system/admin') or
            $startsWith($path, '/_system/documentation') or
            $startsWith($path, '/_system/db-adminer')
        )) {
            $notAllowed();
        }
    }

    // if SCRIPT_NAME ends with index.php
    if(substr($_SERVER['SCRIPT_NAME'], -10) == '/index.php') {
        // and it is a real directory but doesn't end with a '/'
        if(
            substr($path, -1) != '/' and
            is_dir($_SERVER['DOCUMENT_ROOT'] . $path)
        ) {
            // then redirect and add the '/'
            $redir = $path . '/';
            if($query) { $redir .= '?' . $query; }
            header("HTTP/1.1 301 Moved Permanently"); 
            header("Location: $redir");
            exit();
        }
    }
    return false;
}
return wpdev_router();
