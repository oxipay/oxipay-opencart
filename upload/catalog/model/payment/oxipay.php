<?php
class ModelPaymentOxipay extends Model {
    /**
     * @param mixed[] $address
     * @param double $total
     *
     * @return mixed[]
     */
    public function getMethod($address, $total) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('oxipay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        $status = false;

        if (!$this->config->get('oxipay_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        }

        $method_data = [];

        if ($status) {
            $title = $this->config->get('oxipay_title');

            $method_data = [
                'code'       => 'oxipay',
                'title'      => $this->config->get('oxipay_title'),
                'terms'      => $this->config->get('oxipay_description'),
                'sort_order' => $this->config->get('oxipay_sort_order'),
            ];
        }

        return $method_data;
    }

    /**
     * Generate HMAC-SHA256 signature
     *
     * @param string[] $params
     *
     * @return string
     */
    public function getSignature($params) {
        $string = '';

        ksort($params);

        foreach ($params as $key => $value) {
            if (substr($key, 0, 2) === 'x_') {
                $string .= $key . $value;
            }
        }

        $hash = hash_hmac('sha256', $string, $this->config->get('oxipay_api_key'));

        return str_replace('-', '', $hash);
    }

    /**
     * Validate HMAC-SHA256 signature
     *
     * @param string[] $params
     *
     * @return bool
     */
    public function validateSignature($params) {
        if (!isset($params['x_signature'])) {
            return false;
        }

        $signature = $params['x_signature'];

        unset($params['x_signature']);

        return $signature == $this->getSignature($params);
    }

    /**
     * Generate array of parameters to be passed onto Oxipay.
     *
     * @return mixed[]
     */
    public function getParams() {
        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $payment_country_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$order_info['payment_country_id'] . "' AND status = 1 LIMIT 1")->row;
        $payment_zone_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$order_info['payment_zone_id'] . "' AND status = 1 AND country_id = '" . (int)$order_info['payment_country_id'] . "' LIMIT 1")->row;

        $shipping_country_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$order_info['shipping_country_id'] . "' AND status = 1 LIMIT 1")->row;
        $shipping_zone_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$order_info['shipping_zone_id'] . "' AND status = 1 AND country_id = '" . (int)$order_info['shipping_country_id'] . "' LIMIT 1")->row;

        $url_prefix = (
            $this->config->get('site_ssl')
            ? $this->config->get('site_ssl')
            : $this->config->get('site_base')
        );

        $params = [
            // Required
            'x_account_id' => $this->config->get('oxipay_merchant_id'),
            'x_amount' => $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false),
            'x_currency' => $order_info['currency_code'],
            'x_reference' => $this->session->data['order_id'],
            'x_shop_country' => $this->config->get('oxipay_region'),
            'x_shop_name' => $this->config->get('oxipay_shop_name'),
            'x_test' => 'false',
            'x_url_callback' => $this->url->link('payment/oxipay/callback', '', true),
            // Proxy files required as gateway doesn't append resulting request
            //  arguments to existing ones.
            // 'x_url_cancel' => $url_prefix . 'oxipay/cancel.php',
            // 'x_url_complete' => $url_prefix . 'oxipay/complete.php',
            'x_url_cancel' => $this->url->link('payment/oxipay/cancel', '', true),
            'x_url_complete' => $this->url->link('payment/oxipay/complete', '', true),

            // Optional
            'x_customer_first_name' => $order_info['payment_firstname'],
            'x_customer_last_name' => $order_info['payment_lastname'],
            'x_customer_email' => $order_info['email'],
            'x_customer_phone' => $order_info['telephone'],
            'x_customer_billing_address1' => $order_info['payment_address_1'],
            'x_customer_billing_address2' => $order_info['payment_address_2'],
            'x_customer_billing_city' => $order_info['payment_city'],
            'x_customer_billing_state' => '',
            'x_customer_billing_postcode' => $order_info['payment_postcode'],
            'x_customer_billing_country' => '',
            'x_customer_shipping_first_name' => $order_info['shipping_firstname'],
            'x_customer_shipping_last_name' => $order_info['shipping_lastname'],
            'x_customer_shipping_address1' => $order_info['shipping_address_1'],
            'x_customer_shipping_address2' => $order_info['shipping_address_2'],
            'x_customer_shipping_city' => $order_info['shipping_city'],
            'x_customer_shipping_state' => '',
            'x_customer_shipping_postcode' => $order_info['shipping_postcode'],
            'x_customer_shipping_country' => '',
            'x_description' => 'Order #' . $order_info['order_id'],
        ];

        if ($payment_country_info) {
            $params['x_customer_billing_country'] = $payment_country_info['iso_code_2'];
        }

        if ($payment_zone_info) {
            $params['x_customer_billing_state'] = $payment_zone_info['code'];
        }

        if ($shipping_country_info) {
            $params['x_customer_shipping_country'] = $shipping_country_info['iso_code_2'];
        }

        if ($shipping_zone_info) {
            $params['x_customer_shipping_state'] = $shipping_zone_info['code'];
        }

        $params['x_signature'] = $this->getSignature($params);

        

        return $params;
    }

    /**
     * @return mixed[]
     */
    public function getStatuses() {
        return [
            'completed' => $this->config->get('oxipay_order_status_completed_id'),
            'pending' => $this->config->get('oxipay_order_status_pending_id'),
            'failed' => $this->config->get('oxipay_order_status_failed_id'),
        ];
    }

    /**
     * @param string $outcome
     *
     * @return string|null
     */
    public function getStatus($outcome) {
        $statuses = $this->getStatuses();

        return (
            isset($statuses[$outcome])
            ? $statuses[$outcome]
            : 0
        );
    }

    /**
     * @return string
     */
    public function getGatewayUrl() {
        $environment = $this->config->get('oxipay_gateway_environment');

        if ($environment == 'other') {
            return $this->config->get('oxipay_gateway_url');
        }

        $region = $this->config->get('oxipay_region');

        if ($region == 'NZ') {
            $tld = 'co.nz';
        } else {
            $tld = 'com.au';
        }

        if ($environment == 'live') {
            $prefix = 'secure';
        } else {
            $prefix = 'securesandbox';
        }

        return 'https://' . $prefix . '.oxipay.' . $tld . '/Checkout?platform=Default';
    }
}
