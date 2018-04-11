<?php
class ControllerExtensionPaymentOxipay extends Controller {
    private $error = [];

    /**
     * @return string
     */
    public function index() {
        $language_data = $this->load->language('extension/payment/oxipay');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_oxipay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        // Error Strings
        $keys = [
            'oxipay_warning',
            'oxipay_region',
            'oxipay_gateway_environment',
            'oxipay_gateway_url',
            'oxipay_merchant_id',
            'oxipay_api_key',
        ];

        foreach ($keys as $key) {
            if (isset($this->error[$key])) {
                $data['error_' . $key] = $this->error[$key];
            } else {
                $data['error_' . $key] = '';
            }
        }

        // Language Strings
        foreach ($language_data as $key => $value) {
            $data[$key] = $value;
        }

        // Breadcrumbs
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
            ], [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
            ], [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/oxipay', 'user_token=' . $this->session->data['user_token'], true),
            ],
        ];

        // Actions / Links
        $data['action'] = $this->url->link('extension/payment/oxipay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        // Dropdown Data
        $this->load->model('localisation/geo_zone');
        $this->load->model('localisation/order_status');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['regions'] = $this->getRegions();
        $data['gateway_environments'] = $this->getGatewayEnvironments();

        // Form Values
        $keys = [
            'payment_oxipay_title',
            'payment_oxipay_description',
            'payment_oxipay_shop_name',
            'payment_oxipay_region',
            'payment_oxipay_gateway_environment',
            'payment_oxipay_gateway_url',
            'payment_oxipay_merchant_id',
            'payment_oxipay_api_key',
            'payment_oxipay_order_status_completed_id',
            'payment_oxipay_order_status_pending_id',
            'payment_oxipay_order_status_failed_id',
            'payment_oxipay_geo_zone_id',
            'payment_oxipay_status',
            'payment_oxipay_sort_order',
        ];

        $defaults = [
            'payment_oxipay_title' => 'Oxipay',
            'payment_oxipay_description' => 'Pay the easier way',
            'payment_oxipay_order_status_completed_id' => 5,
            'payment_oxipay_order_status_pending_id'=> 1,
            'payment_oxipay_order_status_failed_id' => 10,
        ];

        foreach ($keys as $key) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else if (!$this->config->has($key) && isset($defaults[$key])) {
                $data[$key] = $defaults[$key];
            } else if ($this->config->has($key)) {
                $data[$key] = $this->config->get($key);
            } else {
                $data[$key] = "";
            }
        }

        // Layout
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        // Render Output
        $this->response->setOutput($this->load->view('extension/payment/oxipay', $data));
    }

    /**
     * @return bool
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/oxipay')) {
            $this->error['oxipay_warning'] = $this->language->get('error_permission');
        }

        $keys = [
            'payment_oxipay_title' => 'Title',
            'payment_oxipay_region' => 'Region',
            'payment_oxipay_merchant_id' => 'Merchant ID',
            'payment_oxipay_api_key' => 'API Key',
        ];

        foreach ($keys as $key => $name) {
            if (!isset($this->request->post[$key]) || empty($this->request->post[$key])) {
                $this->error[$key] = sprintf($this->language->get('error_required'), $name);
            }
        }

        if ( 
            $this->request->post['payment_oxipay_gateway_environment'] == 'other' && (!isset($this->request->post['payment_oxipay_gateway_url']) || preg_match('@^https://@', $this->request->post['payment_oxipay_gateway_url']) !== 1 )
        ) {
            $this->error['oxipay_gateway_url'] = $this->language->get('error_gateway_url_format');            
        }

        return !$this->error;
    }

    /**
     * @return mixed[]
     */
    private function getRegions() {
        return [
            [
                'code' => 'AU',
                'name' => 'Australia',
            ],
            [
                'code' => 'NZ',
                'name' => 'New Zealand',
            ],
        ];
    }

    /**
     * @return mixed[]
     */
    private function getGatewayEnvironments() {
        return [
            [
                'code' => 'sandbox',
                'name' => 'Sandbox',
            ],
            [
                'code' => 'live',
                'name' => 'Live',
            ],
            [
                'code' => 'other',
                'name' => 'Other',
            ],
        ];
    }
}