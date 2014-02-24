<?php

function remove_url_param($url, $param) {
    $parts = split('\?', $url, 2);
    if(count($parts) == 1) { return $url; }

    parse_str($parts[1], $query);
    unset($query[$param]);
    $query = http_build_query($query);

    if(!$query) {
        return $parts[0];
    }

    return $parts[0] . '?' . $query;
}

$urls = array(
    'http://localhost/abc',
    'http://localhost/abc/',
    'http://localhost/abc?token=1',
    'http://localhost/abc/?token=1',
    'http://localhost/abc?token=1&num=2',
    'http://localhost/abc/?token=1&num=2',
    'http://localhost/abc?num=2&token=1',
    'http://localhost/abc/?num=2&token=1&num2=%3D'
);

foreach($urls as $url) {
    echo "$url<br />" . remove_url_param($url, 'token') . "<br /><br />";
}
