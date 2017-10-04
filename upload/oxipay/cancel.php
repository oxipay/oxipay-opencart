<?php

define('OXIPAY_PROXY', true);

require(__DIR__ . '/oxipay.php');

oxipay_redirect('extension/payment/oxipay/cancel');
