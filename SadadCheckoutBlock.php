<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Sadad_Woo_Blocks extends AbstractPaymentMethodType
{
    protected $name = 'sadad';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_sadad_settings', []);
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'sadad-blocks-integration',
            plugin_dir_url(__FILE__) . 'assets/js/sadad-checkout-block.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('paymob-blocks-integration');
        }

        return ['sadad-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->settings['title'],
            'description' => $this->settings['description'],
            'logo' => $this->settings['logo'],

        ];
    }
}