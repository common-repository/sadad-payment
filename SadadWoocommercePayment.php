<?php

/**
 * Sadad WooCommerce Class
 */
class SadadPayment {

    /**
     * Constructor
     */
    public function __construct() {
        $this->code = 'sadad';
        $this->id = 'sadad';
        $this->gateway = 'Gateway_Sadad';
        // filters
        add_filter('woocommerce_payment_gateways', array($this, 'register'), 0);
        add_filter('plugin_action_links_' . SADAD_WOO_PLUGIN, array($this, 'plugin_action_links'));
        add_action('woocommerce_api_sadad_callback', array($this, 'callback'));
        add_action('woocommerce_api_sadad_webhook', array($this, 'webhook'));
    }

    /**
     * Register the gateway to WooCommerce
     */
    public function register($gateways) {
        include_once 'includes/payments/class-gateway-sadad.php';
        $gateways[] = $this->gateway;
        return $gateways;
    }

    /**
     * Show action links on the plugin screen.
     *
     * @param mixed $links Plugin Action links.
     *
     * @return array
     */
    public function plugin_action_links($links) {
        $plugin_links[] = '<a href="' . esc_url_raw( admin_url('admin.php?page=wc-settings&tab=checkout&section=gateway_sadad') ) . '">'.__( 'Settings', 'Sadad-woocommerce').'</a>';

        return array_merge($links, $plugin_links);
    }

    public function callback() {
        $gateway = new Gateway_Sadad();
        try {
            $gateway->checkOrderInfo(SadadLibrary::filterInput('invoice_id'));
        } catch (Exception $ex) {
            $error = $ex->getMessage();
            wc_add_notice($error, 'error');
            wp_redirect(wc_get_checkout_url());
        }
    }

    public function webhook() {
        $gateway = new Gateway_Sadad();
        try {
            $content = $this->getContent();
            $request= "php://input";
            $body = $content($request);
            $webhook = json_decode($body);
            if (empty($webhook)) {
                die('Error, Empty data');
            }

            error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - Webhook : ' . print_r($webhook, 1), 3, WC_LOG_DIR . $this->id . '.log');
            $invoiceId = $webhook->invoiceId;
            $gateway->checkOrderInfo($invoiceId, 'Sadad Webhook : ');
        } catch (Exception $ex) {
            header('X-PHP-Response-Code: 404', true, 404);
        }
    }

    public function getContent() {
        return 'file_get_contents';
    }
}
