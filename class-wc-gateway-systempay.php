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
 
 
/**
 * Actions and filters
 */
add_action('init', 'systempay_listen_for_systempay_autoresponse', 1);
add_action('plugins_loaded', 'init_systempay_gateway', 0);
add_filter('woocommerce_payment_gateways', 'add_systempay_gateway' );
  
/**
 * Initialise the Gateway Settings Form Fields
 */
function init_systempay_gateway() {
 
	 /**
	 * Main Gateway Class
	 */
	class WC_Gateway_systempay extends WC_Payment_Gateway {
			
		public function __construct() { 
			global $woocommerce;
			
			$this->id			= 'systempay';
			$this->method_title = __('Systempay', 'woothemes');
			$this->icon 		= apply_filters('woocommerce_systempay_icon', get_option('siteurl') . '/wp-content/plugins/woocommerce-gateway-systempay/images/cvm.png');
			$this->has_fields 	= false;
			
			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->liveurl 		        = $this->settings['liveurl'];
			$this->title 				= $this->settings['title'];
			$this->description 			= $this->settings['description'];
			$this->vads_ctx_mode 		= $this->settings['vads_ctx_mode'];
			$this->certificate			= $this->settings['certificate'];		
			$this->vads_site_id 		= $this->settings['vads_site_id'];
			$this->vads_currency		= $this->settings['vads_currency'];
			$this->transactionDate		= date('Y-m-d H:i:s O');
			
			//other
			$this->paybtn		= $this->settings['paybtn'];
			$this->paymsg		= $this->settings['paymsg'];
			//$this->payreturn	= $this->settings['payreturn'];

			// Actions
			//add_action('init', array($this, 'check_systempay_response'));
			add_action('woocommerce_api_wc_gateway_systempay', array( $this, 'check_systempay_response' ) );
			add_action('valid-systempay-request', array(&$this, 'successful_request'));
			add_action('woocommerce_receipt_systempay', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_thankyou_systempay', array($this, 'thankyou_page'));

		} 
		
		/**
		 * Gateway Settings Form Fields
		 */
		function init_form_fields() {
			
			// Absolute path
			$abs_path = str_replace('/wp-admin', '', getcwd());
		
			// Form fields
			$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Activer/Désactiver:', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Activer le module de paiement Systempay.', 'woothemes' ), 
								'default' => 'yes'
							), 
				'title' => array(
								'title' => __( 'Nom:', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Intitulé affiché à l\'utilisateur lors de la commande.', 'woothemes' ), 
								'default' => __( 'Systempay', 'woothemes' )
							),
				'description' => array(
								'title' => __( 'Description:', 'woothemes' ), 
								'type' => 'textarea', 
								'description' => __( 'Description affichée à l\'utilisateur lors de la commande.', 'woothemes' ), 
								'default' => __('Paiement sécurisé par carte bancaire Systempay - Banque Populaire.', 'woothemes')
							),
				'vads_ctx_mode' => array(
								'title' => __( 'Mode:', 'woothemes' ), 
								'type' => 'select', 
								'description' => __( 'Sélectionnez un mode de fonctionnement.', 'woothemes' ), 
								'default' => 'TEST',
								'options' => array(
									'TEST' => 'Test',
									'PRODUCTION' => 'Production'
								 ) 					
							),
				'certificate' => array(
								'title' => __( 'Certificat :', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Veuillez saisir votre certificat fourni par systempay. Pour le mode test, saisissez un certificat de test. Pour le mode production, saisissez un certificat de production. Ces informations sont disponibles dans votre backoffice Systempay.', 'woothemes' ), 
								'default' => ''
							),
				'vads_site_id' => array(
								'title' => __( 'Identifiant boutique:', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Veuillez saisir votre identifiant boutique fourni par systempay.', 'woothemes' ), 
								'default' => ''
							),
				'vads_currency' => array(
								'title' => __( 'Devise:', 'woothemes' ), 
								'type' => 'select', 
								'description' => __( 'Veuillez sélectionner une devise pour les paiemenents.', 'woothemes' ), 
								'default' => '978',
								'options' => array(
									'840' => 'USD',
									'978' => 'EUR',
									'124' => 'CAD',
									'392' => 'JPY',
									'826' => 'GBP',
									'036' => 'AUD' 
								 ) 					
							),
				'liveurl' => array(
								'title' => __( 'Url du serveur de paiement:', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Adresse du serveur systempay sur lequel le client est redirigé pour son paiement.', 'woothemes' ), 
								'default' => 'https://systempay.cyberpluspaiement.com/vads-payment/'
							),
				'paybtn' => array(
								'title' => __( 'Texte bouton de paiement:', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Texte affiché sur le bouton qui redirige vers le terminal de paiement.', 'woothemes' ), 
								'default' => __('Régler la commande.', 'woothemes')
							),
				'paymsg' => array(
								'title' => __( 'Message page de paiement:', 'woothemes' ), 
								'type' => 'textarea', 
								'description' => __( 'Message affiché sur la page de commande validée, avant passage sur le terminal de paiement.', 'woothemes' ), 
								'default' => __('Merci pour votre commande. Veuillez cliquer sur le bouton ci-dessous pour effectuer le règlement.', 'woothemes')
							)							
							
				);
		
		} 
		
		/**
		 * Admin Panel Options 
		 */
		public function admin_options() {

			?>
			<h3>SYSTEMPAY</h3>
			<p><?php _e('Acceptez les paiements par carte bleue grâce à Systempay.', 'woothemes'); ?></p>
			<p><?php _e('Plugin créé par David Fiaty', 'woothemes'); ?> - <a href="http://www.cmsbox.fr">http://www.cmsbox.fr</a></p>
			<table class="form-table">
			<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
			<?php
		} // End admin_options()
		
		/**
		 * There are no payment fields for systempay, but we want to show the description if set.
		 **/
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
		
		/**
		 * Generate the systempay button link
		 **/
		public function generate_systempay_form( $order_id ) {
			global $woocommerce;
			
			// Get order parameters
			$order = new WC_Order( $order_id );
			$systempay_adr = $this->liveurl;
			$shipping_name = explode(' ', $order->shipping_method);	
				
			$key = $this->certificate;
			// Initialisation des paramètres
			$params = array(); // tableau des paramètres du formulaire
			$params['vads_site_id'] = $this->vads_site_id;
			$params['vads_amount'] = $order->order_total*100; 
			$params['vads_currency'] = $this->vads_currency; 
			$params['vads_order_id'] = $order_id;
			$params['vads_ctx_mode'] = $this->vads_ctx_mode;
			$params['vads_page_action'] = "PAYMENT";
			$params['vads_action_mode'] = "INTERACTIVE";
			$params['vads_payment_config']= "SINGLE";
			$params['vads_version'] = "V2";
			$params['vads_language'] = "fr";
			$params['vads_return_mode'] = "POST";
			$params['vads_url_success'] = $this->get_return_url( $order );
			$params['vads_url_return'] = $this->get_return_url( $order );
			$params['vads_url_cancel'] = $this->get_return_url( $order );
			$params['vads_url_check'] = $this->get_return_url( $order ) . '&mode=auto';
			
			// Generate the trans_id with a timestamp
			$ts = time();
			$params['vads_trans_date'] = gmdate("YmdHis", $ts);
			$params['vads_trans_id'] = gmdate("His", $ts);
			
			// Generate the signature
			ksort($params); 
			$contenu_signature = "";
			foreach ($params as $nom => $valeur)
			{
				$contenu_signature .= $valeur."+";
			}
			$contenu_signature .= $key; 
			$params['signature'] = sha1($contenu_signature);
				
			$systempay_args = $params;
			$systempay_args_array = array();

			foreach ($systempay_args as $key => $value) {
				$systempay_args_array[] = '<input type="hidden" name="'.$key.'" value="'. $value .'" />';
			}
			
			// Return the form
			return '<form action="'.$systempay_adr.'" method="post" id="systempay_payment_form">
					' . implode('', $systempay_args_array) . '
					&nbsp;&nbsp;&nbsp;
					<input type="submit" class="button-alt button cancel" id="submit_systempay_payment_form" value="'.__($this->paybtn, 'woothemes').'" /> 
					<script type="text/javascript">
						/*jQuery(function(){
							jQuery("body").block(
								{ 
									message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to cardsave to make payment.', 'woothemes').'", 
									overlayCSS: 
									{ 
										background: "#fff", 
										opacity: 0.6 
									},
									css: { 
										padding:        20, 
										textAlign:      "center", 
										color:          "#555", 
										border:         "3px solid #aaa", 
										backgroundColor:"#fff", 
										cursor:         "wait",
										lineHeight:		"32px"
									} 
								});
							jQuery("#submit_systempay_payment_form").click(); 
							*/
						});
					</script>
				</form>';
		}
		
		/**
		 * Check the payment terminal response
		 **/
		function check_systempay_response() {
			global $woocommerce;
			wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); 
			exit();
		}						
		
		/**
		 * Process a successful request
		 **/
		function successful_request($systempay_response) {

			global $woocommerce;

			if (isset($systempay_response)) {
			
				try {
					switch ($systempay_response['code_reponse']) {
						// transaction authorised
						case '00':
							$transauthorised = true;
							break;
						default:
							$transauthorised = false;
							break;
					}
				
					if ($transauthorised == true) {
						
						// Put code here to update/store the order with the a successful transaction result
						$order_id 	  	= $systempay_response['vads_order_id'];
						$order = new WC_Order( $order_id );
		
						if ($order->status != 'completed') {
						
							if ($order->status == 'processing') {
								// This is the second call - do nothing
							} else {
								$order->payment_complete();
								
								//Add admin order note
								$order->add_order_note('Paiement Systempay: REUSSI<br><br>Référence Transaction: ' . $order_id);								
								
								//Add customer order note
								$order->add_order_note('Paiement réussi','customer');
								
								// Empty the Cart
								$woocommerce->cart->empty_cart();
								
								//update order status
								$order->update_status('processing');
							}					
					}
					} else {
						// put code here to update/store the order with the a failed transaction result
						$order_id 	  	= $systempay_response['vads_order_id'];
						$order 			= &new WC_Order( (int) $order_id );					
						$order->update_status('failed');
						
						//Add admin order note
						$order->add_order_note('Paiement Systempay: ECHEC<br><br>Référence Transaction: ' . $order_id);
						
						//Add customer order note
						$order->add_order_note('Paiement échoué','customer');
					}
				} catch (Exception $e) {
					
					//Log exceptions here
					//$szOutputMessage = "Error updating website system, please ask the developer to check code";
				}
			}
			
			// Manage automatic response
			if ($systempay_response['mode'] != 'auto')
			{
				wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); 
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0.14', '>=' ) ): /* WC 2.1 */
					$redirect = $order->get_checkout_payment_url( true ); /* WC 2.1 */
				else:
					$redirect = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
				endif;
				wp_redirect( $redirect); 
				exit;
			}
		}	
	
		/**
		 * Process the payment and return the result - Check the WoocCommerce version
		 **/
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.14', '>=' ) ): /* WC 2.1 */
				$redirect = $order->get_checkout_payment_url( true ); /* WC 2.1 */
			else:
				$redirect = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
			endif;
			return array(
				'result' 	=> 'success',
				'redirect'	=> $redirect
			);
		}
		
		/**
		 * Receipt_page
		 **/
		function receipt_page( $order) {
			
			echo '<p>'.__($this->paymsg, 'woothemes').'</p>';
			
			echo $this->generate_systempay_form( $order );
		}
					
		/**
		 * Server callback was valid, process callback (update order as passed/failed etc).
		 **/
		function thankyou_page () {
			
			global $woocommerce;
			
			//grab the order ID from the querystring
			$order_id = @$_GET['order'];
			
			//lookup the order details
			$order = new WC_Order( (int) $order_id );
			
			//check the status of the order
			if ($order->status == 'completed') {                                                     
                //display additional success message
				echo "<p>Félicitations! Votre paiement a été validé avec succès. <a href=\"". esc_url( add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_view_order_page_id'))) ) ."\">Cliquez ici pour voir votre commande</a></p>";
			} else {
				//display additional failed message
				echo "<br>&nbsp;<p>La transaction n'a pas été validée. Pour plus d'information, <a href=\"". esc_url( add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_view_order_page_id'))) ) ."\">cliquez ici pour voir votre commande</a>.</p>";
			}
		}		
	}
}

/**
 * Listen for auto response
 **/
function systempay_listen_for_systempay_autoresponse() {
	if (@$_REQUEST['mode'] == 'auto') 
	{
		$url = explode('/', $_SERVER['REQUEST_URI']);
		$parts = explode('?', $url[3]);
		$order_id = $parts[0];
		
		$sp = new WC_Gateway_systempay();
		$order = new WC_Order( $order_id );
		
		
		//custom parameter for transaction processing
		$systempay_response = array();
		//systempay code
		$key = $sp->certificate;
		$contenu_signature = "";
		$params = $_POST;
		ksort($params);
		foreach ($params as $nom => $valeur)
		{
			if(substr($nom,0,5) == 'vads_')
			{
			// C'est un champ utilisé pour calculer la signature
			$contenu_signature .= $valeur."+";
			}
		}
		$contenu_signature .= $key; // On ajoute le certificat (dernier paramètre)
		$signature_calculee = sha1($contenu_signature);

		if (isset($_REQUEST['signature']) && $signature_calculee == $_REQUEST['signature'])
		{
		
			// Requête authentifiée
			// Attention cependant à bien vérifier les paramètres passés
			// Notamment le vads_site_id et le vads_ctx_mode

			
			if ($_REQUEST['vads_result'] == "00")
			{
				$systempay_response['code_reponse'] = $_REQUEST['vads_result'];
				$systempay_response['vads_order_id'] = $order_id;
				$systempay_response['mode'] = $_REQUEST['mode'];
				$sp->successful_request($systempay_response);
			}
			else
			{
				//echo("StatusCode=100&Message=Une erreur est survenue");
				//Log some error here
			}
		}
		else
		{
				//echo("StatusCode=200&Message=Une erreur est survenue");
				//Log some error here
		}
		exit();	
	}
}

/**
 * Add the gateway to WooCommerce
 **/
function add_systempay_gateway( $methods ) {
	$methods[] = 'WC_Gateway_systempay'; 
	return $methods;
}

