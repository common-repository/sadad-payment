<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once SADAD_WOO_PLUGIN_PATH . 'includes/libraries/SadadLibrary.php';

/**
 * Gateway_Sadad class.
 *
 * Handle payments.
 *
 * @extends WC_Payment_Gateway
 */
class Gateway_Sadad extends WC_Payment_Gateway {

    public $enabled;
    public $title;
    public $description;
    public $testMode;
    public $clientId;
    public $clientSecret;
    public $orderStatus;
    public $notifyUrl;
    public $sadadConfig;
    public $sadadObj;

    /**
     * Constructor for payment class
     *
     * @return void
     */
    public function __construct() {
        $this->code = 'sadad';
        $this->id = 'sadad';
        $this->lang = substr(determine_locale(), 0, 2);
        $this->log = WC_LOG_DIR . $this->id . '.log';
        $this->method_description = __('Sadad Payment Debit/Credit Card payment.', 'sadad-woocommerce');
        $this->method_title = __('Sadad Payment', 'sadad-woocommerce');
        $this->has_fields = true;

        // this will appeare in the setting details page. For more customize page you override function admin_options()
        $this->supports = array(
            'products',
                // 'refunds',
        );
        // Get setting values
        $this->init_settings();

        // settings
        foreach ($this->settings as $key => $val) {
            $this->$key = $val;
        }
        $this->orderStatus = empty($this->get_option('orderStatus'))?'processing':$this->get_option('orderStatus');
        
        if (!empty($this->get_option('refreshToken'))) {
            $this->sadadConfig = array(
                'refreshToken' => $this->get_option('refreshToken'),
                'isTest' => ( $this->get_option('testMode') === 'yes' ) ? true : false,
                'log' => $this->log,
            );
        } else {
            $this->sadadConfig = array(
                'clientId' => $this->get_option('clientId'),
                'clientSecret' => $this->get_option('clientSecret'),
                'isTest' => ( $this->get_option('testMode') === 'yes' ) ? true : false,
                'log' => $this->log,
            );
        }
        $this->description = $this->get_option('description');
        $this->notifyUrl = add_query_arg(array('wc-api' => 'sadad_callback'), home_url());

        // Create plugin admin fields
        $this->init_form_fields();

        // save admin setting action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_' . $this->id, array(&$this, 'check_notify_response'));
    }

    /**
     * Define Settings Form Fields
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = include SADAD_WOO_PLUGIN_PATH . 'includes/admin/payment.php';
    }

    /**
     * Return the gateway's title.
     *
     * @return string
     */
    public function get_title() {
        return apply_filters('woocommerce_gateway_title', $this->title, $this->id);
    }

    /**
     * Return the gateway's icon.
     *
     * @return string
     */
    public function get_icon() {
        $url = plugins_url(SADAD_WOO_PLUGIN_NAME);
        $icon = "<img width='30px' src='$url/assets/images/logo.svg'/>";

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function process_payment($orderId) {
        $response = $this->getPaymentUrl($orderId);

        if (0 === $response['status']) {
            wc_add_notice($response['error'], 'error');
        } else {
            return array(
                'result' => 'success',
                'redirect' => $response['redirect'],
            );
        }
    }

    /**
     * Generate the Payment link
     * */
    public function getPaymentUrl($orderId) {
        global $woocommerce;
        $order = new WC_Order($orderId);
        $order->update_status('pending-payment', 'Awaiting Sadad Payment <br/>');
        try {
            $currency = $order->get_currency();
            $totalAmount = number_format($order->get_total(), 3, '.', '');
            if ('KWD' !== $currency) {
                $totalAmount = SadadLibrary::getKWDAmount($currency, $totalAmount, ( $this->get_option('testMode') === 'yes' ) ? true : false);
            }
            $phoneNumber = $order->get_billing_phone();
            $email = $order->get_billing_email();

            $billFirstName = $order->get_billing_first_name();
            $billLastName = $order->get_billing_last_name();

            $invoice = array(
                'ref_Number' => "$orderId",
                'amount' => $totalAmount,
                'customer_Name' => $billFirstName . ' ' . $billLastName,
                'customer_Mobile' => $phoneNumber,
                'customer_Email' => $email,
                'lang' => ( 'ar' === $this->lang ) ? 'ar' : 'en',
                'currency_Code' => $currency,
                'Success_ReturnURL' => $this->notifyUrl,
                'Fail_ReturnURL' => $this->notifyUrl,
            );

            $request = array('Invoices' => array($invoice));
            $this->sadadObj = new SadadLibrary($this->sadadConfig);
            $sadadInvoice = $this->sadadObj->createInvoice($request, $this->sadadObj->refreshToken);
            $order = wc_get_order($orderId);
            $order->update_meta_data('sadadInvoiceId', $sadadInvoice['InvoiceId']);
            $order->save();
            return array(
                'status' => 1,
                'redirect' => $sadadInvoice['InvoiceURL'],
            );
        } catch (Exception $ex) {
            return array(
                'status' => 0,
                'error' => $ex->getMessage(),
            );
        }
    }

    /**
     * Don't enable this payment, if there is no configuration keys
     * 
     * @param type $key
     * @param type $value
     * 
     * @return string
     */
    public function validate_enabled_field($key, $value) {
        if (is_null($value)) {
            return 'no';
        }
        $clientKey = $this->get_field_value('clientId', $this->form_fields['clientId']);
        $clientSecret = $this->get_field_value('clientSecret', $this->form_fields['clientSecret']);
        if (!$clientKey && !$clientSecret) {
            $message = __('Error: you need to add the client and secrect keys, to enable Sadad payment method', 'sadad-woocommerce');
            error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . $message, 3, WC_LOG_DIR . $this->id . '.log');
            WC_Admin_Settings::add_error($message);
            return 'no';
        }

        $testMode = $this->get_field_value('testMode', $this->form_fields['testMode']);

        try {
            $this->sadadConfig = array(
                'clientId' => $clientKey,
                'clientSecret' => $clientSecret,
                'isTest' => ($testMode == 'yes') ? true : false
            );

            $this->sadadObj = new SadadLibrary($this->sadadConfig);
            $this->update_option('refreshToken', $this->sadadObj->refreshToken);
            return ($value == null) ? 'no' : 'yes';
        } catch (Exception $ex) {
            error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . $ex->getMessage(), 3, WC_LOG_DIR . $this->id . '.log');
            WC_Admin_Settings::add_error($ex->getMessage());
            $this->update_option('refreshToken', null);
            return 'no';
        }
    }

    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the error in field out.
     *
     * @return bool was anything saved?
     */
    public function process_admin_options() {
        return parent::process_admin_options();
    }

    public function admin_options() {
        parent::admin_options();
    }

    public function checkOrderInfo($invoiceId, $webhook = false) {
        $redirect = wc_get_cart_url();
        $this->sadadObj = new SadadLibrary($this->sadadConfig);
        $response = $this->sadadObj->getInvoiceInfo($invoiceId, $this->sadadObj->refreshToken);

        if (isset($response['isValid']) && 'true' == $response['isValid']) {
            $ref_Number = $response['response']['ref_Number'];
            $order = wc_get_order($ref_Number);
            $sadadInvoiceId = $order->get_meta('sadadInvoiceId', true);
            if ((int) $sadadInvoiceId === (int) $invoiceId) {
                $paymentMethod = $order->get_payment_method();
                if ('sadad' !== $paymentMethod) {
                    $message = __('Error: The Order is not found. ', 'sadad-woocommerce');
   	    	    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') .$webhook. $message, 3, WC_LOG_DIR . $this->id . '.log');
                    if (!$webhook){
                        wc_add_notice($message, 'error');
                        wp_redirect(wc_get_checkout_url());
                    }else{
                        header('X-PHP-Response-Code: 404', true, 404);
                        exit();
                    }
                }

                if ('Paid' === $response['response']['status']) {
                    // payment success
                    $transactionID = $response['response']['transactionID'];
                    $message = __('Sadad Payment is completed', 'sadad-woocommerce') .__('<br/> Transaction ID: ', 'sadad-woocommerce') . $transactionID. __('<br/> Invoice ID: ', 'sadad-woocommerce') .$invoiceId;
                    $order->update_status($this->orderStatus, $webhook.$message.'<br/>');
                    $order->save();
       	    	    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') .'Transaction for orderId# '.$ref_Number . ' ' .$webhook. $message, 3, WC_LOG_DIR . $this->id . '.log');
                    if (!$webhook){
                        wp_redirect($order->get_checkout_order_received_url());
                    }else{
                        header('X-PHP-Response-Code: 200', true, 200);
                    }
                    exit();
                } else {

                    $message = __('Sadad invoice is not Paid', 'sadad-woocommerce') . __('<br/> Invoice ID: ', 'sadad-woocommerce') .$invoiceId;;
                    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') .'Payment for orderId# '.$ref_Number . ' ' .$webhook. $message, 3, WC_LOG_DIR . $this->id . '.log');
                    $order->update_status('failed', $webhook.$message.'<br/>');
                    $order->save();
                }
            } else {
                $message =__('Payment return: Invoice ID not match', 'sadad-woocommerce');
            }
        } else {
            $message =__('Payment return: Invoice not found', 'sadad-woocommerce');
        }
   	error_log(PHP_EOL . gmdate('d.m.Y h:i:s') .$webhook. $message, 3, WC_LOG_DIR . $this->id . '.log');
        if (!$webhook){
            wc_add_notice($message, 'error');
            wp_redirect(wc_get_checkout_url());
        }else{
            header('X-PHP-Response-Code: 404', true, 404);
        }

        exit();
    }

    public function payment_fields() {
        echo '<!-- Sadad version ' . esc_attr(SADAD_WOO_PLUGIN_VERSION) . ' -->';
        include_once SADAD_WOO_PLUGIN_PATH . 'templates/payment.php';
    }

    public function get_parent_payment_fields() {
        parent::payment_fields();
    }

    /**
     * Return whether or not this gateway still requires setup to function.
     *
     * When this gateway is toggled on via AJAX, if this returns true a
     * redirect will occur to the settings page instead.
     * @since 3.4.0
     *
     * @return bool
     */
    public function needs_setup() {

        if (empty($this->get_option('clientId')) && empty($this->get_option('clientSecret'))) {
   	    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - Wrong configuration ', 3, WC_LOG_DIR . $this->id . '.log');
            return true;
        }

        if (empty($this->get_option('refreshToken'))) {
   	    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - Wrong configuration - empty refresh Token ', 3, WC_LOG_DIR . $this->id . '.log');
            return true;
        }
        return false;
    }
}

