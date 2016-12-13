<?php
/**
 * Plugin Name: WooCommerce Systempay Gateway
 * Plugin URI: http://www.cmsbox.fr
 * Description: Extends WooCommerce. Provides an Systempay Redirect gateway for WooCommerce.
 * Version: 1.0
 * Author: David Fiaty
 * Author URI: http://www.davidfiaty.com
 * Copyright 2016 - David Fiaty
 **/
 
// Uninstall - removes all Systempay options from DB when user deletes the plugin via WordPress backend.

if ( !defined('WP_UNINSTALL_PLUGIN') ) {
    exit();
}
	delete_option( 'woocommerce_Systempay_settings' );		
?>