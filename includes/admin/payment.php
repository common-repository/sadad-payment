<?php

/**
 * Settings for Sadad pay Gateway.
 */
return array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'label' => __('Enable Sadad Payment', 'sadad-woocommerce'),
        'type' => 'checkbox',
        'default' => 'no',
    ),
    'title' => array(
        'title' => __('Title', 'sadad-woocommerce'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'sadad-woocommerce'),
        'default' => __('Sadad Payment', 'sadad-woocommerce'),
        'sanitize_callback' => 'sanitize_text_field',
    ),
    'description' => array(
        'title' => __('Description', 'sadad-woocommerce'),
        'type' => 'textarea',
        'description' => __('Give the customer instructions or a brief description when paying via Sadad.', 'sadad-woocommerce'),
        'default' => __('Content here', 'sadad-woocommerce'),
    ),
    'testMode' => array(
        'title' => __('Test Mode', 'sadad-woocommerce'),
        'type' => 'checkbox',
        'description' => '',
        'default' => 'yes',
    ),
    'clientId' => array(
        'title' => __('Client Key', 'sadad-woocommerce'),
        'type' => 'text',
        'description' => '',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ),
    'clientSecret' => array(
        'title' => __('Client Secret', 'sadad-woocommerce'),
        'type' => 'text',
        'description' => '',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ),
    'orderStatus'      => array(
        'title'             => __('Order Status', 'woocommerce'),
        'type'              => 'select',
        'description'       => __('Mark the order of successful payment with processing or completed.', 'sadad-woocommerce'),
        'default'           => 'processing',
        'options'           => array(
            'processing' => __('Processing', 'woocommerce'),
            'completed'  => __('Completed', 'woocommerce'),
        ),
        'sanitize_callback' => 'sanitize_text_field'
    ),
);
