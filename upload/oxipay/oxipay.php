<?php

if (!defined('OXIPAY_PROXY')) {
    exit;
}

function oxipay_redirect($route) {
    if (is_file(__DIR__ . '/../config.php')) {
        require_once(__DIR__ . '/../config.php');
    }

    require_once(DIR_SYSTEM . 'startup.php');

    $config = new Config();
    $config->load('default');
    $config->load('catalog');

    $url = new Url($config->get('site_base'), $config->get('site_ssl'));

    $query = http_build_query($_GET);

    header('Location: ' . $url->link($route, '', true) . ($query ? '&' . $query : ''));
    exit;
}