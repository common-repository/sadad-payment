<?php

/*
  Plugin Name: Sadad Payment for Woocommerce
  Plugin URI: https://sadadpay.readme.io/reference/wordpresswoocommerce

  Description: Sadad Payment supports local KNET, VISA/MASTER, and Apple Pay.
  Version: 1.0.2

  Author: Sadad Payment Team
  Author URI: https://sadadpay.net
  Auther Email: plugins@sadadpay.net

  Text Domain: sadad-woocommerce
  Domain Path: /i18n/languages/

  Requires at least: 5.6
  Tested up to: 6.4

  Requires PHP: 7.4

  WC requires at least: 5.3
  WC tested up to: 8.5

  Copyright 2023  sadadpay.net
 */

if (!defined('ABSPATH'))
    exit;
if (!defined('WPINC'))
    exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

define('SADAD_WOO_PLUGIN_VERSION', '1.0.2');
define('SADAD_WOO_PLUGIN', plugin_basename(__FILE__));
define('SADAD_WOO_PLUGIN_NAME', dirname(SADAD_WOO_PLUGIN));
define('SADAD_WOO_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
  Sadad Class
 */
class Sadad {

    /**
      Constructor
     */
    public function __construct() {
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        // actions
        add_action('activate_plugin', array($this, 'activateSadad'), 0);
        add_action('plugins_loaded', array($this, 'init'), 0);

        //WooCommerce order tables
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        // WooCommerce checkout blocks
        add_action('before_woocommerce_init', function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            }
        });
        // WooCommerce block loaded
        add_action('woocommerce_blocks_loaded', [$this, 'sadad_woocommerce_block_loaded']);
    }

    /**
      Show row meta on the plugin screen.
     *
      @param mixed $links Plugin Row Meta.
      @param mixed $file  Plugin Base file.
     *
      @return array
     */
    public static function plugin_row_meta($links, $file) {

        if (SADAD_WOO_PLUGIN === $file) {
            $row_meta = array(
                'apidocs' => '<a href="' . esc_url('https://sadadpay.readme.io') . '" aria-label="' . __('API docs', 'sadad-woocommerce') . '">' . __('API docs', 'sadad-woocommerce') . '</a>',
                'support' => '<a href="mailto:plugins@sadadkw.com" aria-label="' . __('Support', 'sadad-woocommerce') . '">' . __('Support', 'sadad-woocommerce') . '</a>',
            );

            return array_merge($links, $row_meta);
        }

        return (array) $links;
    }

    public function activateSadad($plugin) {
        // it is very important to say that the plugin is Sadad
        if (SADAD_WOO_PLUGIN === $plugin && !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !array_key_exists('woocommerce/woocommerce.php', apply_filters('active_plugins', get_site_option('active_sitewide_plugins')))) {
            wp_die(__('WooCommerce plugin needs to be activated first to activate Sadad plugin', 'sadad-woocommerce'));
        }
    }
    function sadad_woocommerce_block_loaded() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType') && class_exists('Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry')) {
            require_once dirname(__FILE__) . '/SadadCheckoutBlock.php';
            add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                        $container = Automattic\WooCommerce\Blocks\Package::container();
                        $container->register(
                                Sadad_Woo_Blocks::class,
                                function () {
                                    return new Sadad_Woo_Blocks();
                                }
                        );
                        $payment_method_registry->register($container->get(Sadad_Woo_Blocks::class));
                    }
            );
        }
    }
    /**
      Init localizations and files
     */
    public function init() {
        // Localisation
        load_plugin_textdomain('sadad-woocommerce', false, SADAD_WOO_PLUGIN_NAME . '/i18n/languages');
    }
}

new Sadad();
// load payment
if (!class_exists('SadadPayment')) {
    include_once 'SadadWoocommercePayment.php';
    new SadadPayment();
}


