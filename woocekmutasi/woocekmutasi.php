<?php
/*
Plugin Name: WooCekmutasi
Plugin URI: https://cekmutasi.co.id
Description: Cekmutasi for WooCommerce. Sistem validasi pembayaran bank otomatis oleh https://cekmutasi.co.id
Version: 1.0.0
Author: Cekmutasi.co.id
Author URI: https://cekmutasi.co.id
WC requires at least: 3.1.0
WC tested up to: 3.3.3
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: woocekmutasi
Domain Path: /languages
------------------------------------------------------------------------
*/
require(__DIR__ . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'config.php');
register_activation_hook(__FILE__, 'woocekmutasi_install');

function woocekmutasi_install()
{
	global $wpdb;
	$woocekmutasi_version = get_option("woocekmutasi_database_version");
	if ($woocekmutasi_version != WOOCEKMUTASI_VERSION) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php' );
		// Table transactions
		$sql = sprintf("CREATE TABLE IF NOT EXISTS %s%s (
			`seq` INT(11) NOT NULL AUTO_INCREMENT,
			`order_id` INT(11) NOT NULL,
			`order_key` VARCHAR(128) NOT NULL,
			`order_customer` INT(11) NOT NULL,
			`order_address_billing` TEXT NOT NULL,
			`order_address_shipping` TEXT NOT NULL,
			`order_datetime` DATETIME NULL DEFAULT NULL,
			`order_currency` CHAR(3) NOT NULL DEFAULT 'IDR',
			`order_amount_shipping` DECIMAL(18,2) NOT NULL,
			`order_amount_tax` DECIMAL(18,2) NOT NULL,
			`order_amount_total` DECIMAL(18,2) NOT NULL,
			`unique_amount` SMALLINT(4) NOT NULL,
			`unique_type` ENUM('increase','decrease') NOT NULL DEFAULT 'increase',
			`order_total` DECIMAL(18,2) NOT NULL,
			`order_status` VARCHAR(64) NOT NULL,
			`order_datetime_create` DATETIME NULL DEFAULT NULL,
			`order_datetime_update` DATETIME NULL DEFAULT NULL,
			`payment_bank` VARCHAR(16) NOT NULL DEFAULT 'all',
			`payment_cekmutasi_durasi_unit` ENUM('day','week') NOT NULL DEFAULT 'day',
			`payment_cekmutasi_durasi_amount` SMALLINT(4) NOT NULL DEFAULT '7',
			`payment_insert` DATETIME NULL DEFAULT NULL,
			`payment_data` MEDIUMTEXT NOT NULL,
			`ipn_data` MEDIUMTEXT NOT NULL,
			PRIMARY KEY (`seq`),
			INDEX `order_id` (`order_id`),
			INDEX `order_currency_unique_amount_order_status` (`order_currency`, `unique_amount`, `order_status`)
		)",
			$wpdb->prefix,
			WOOCEKMUTASI_TABLE_TRANSACTION
		);
		dbDelta($sql);
		$sql = sprintf("CREATE TABLE %s%s (
			`seq` INT(11) NOT NULL AUTO_INCREMENT,
			`payment_bank` VARCHAR(16) NOT NULL,
			`input_data` TEXT NOT NULL,
			`input_datetime` DATETIME NOT NULL,
			PRIMARY KEY (`seq`),
			INDEX `payment_bank` (`payment_bank`)
		)",
			$wpdb->prefix,
			WOOCEKMUTASI_TABLE_TRANSACTION_IPN
		);
		dbDelta($sql);
		// Table transaction unique
		$sql = sprintf("CREATE TABLE IF NOT EXISTS %s%s (
			`seq` INT(11) NOT NULL AUTO_INCREMENT,
			`trans_seq` INT(11) NOT NULL,
			`trans_user` INT(11) NOT NULL DEFAULT '0',
			`unique_payment_gateway` VARCHAR(64) NOT NULL DEFAULT 'woocekmutasi',
			`unique_unit_name` ENUM('day','hour','minute') NOT NULL DEFAULT 'day',
			`unique_unit_amount` SMALLINT(4) NOT NULL,
			`unique_label` TINYTEXT NOT NULL,
			`unique_amount` SMALLINT(4) NOT NULL,
			`unique_date` DATE NULL DEFAULT NULL,
			`unique_datetime` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`seq`),
			INDEX `trans_seq` (`trans_seq`),
			INDEX `trans_seq_trans_user` (`trans_seq`, `trans_user`),
			INDEX `unique_datetime` (`unique_datetime`)	
		)",
			$wpdb->prefix,
			WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE
		);
		dbDelta($sql);
		update_option("woocekmutasi_database_version", WOOCEKMUTASI_VERSION);
	}
}
register_deactivation_hook(__FILE__, 'woocekmutasi_deactivate');
function woocekmutasi_deactivate() {
	delete_option('woocekmutasi_database_version');
}
register_uninstall_hook(__FILE__, 'woocekmutasi_uninstall');
function woocekmutasi_uninstall() {
	delete_option('woocekmutasi_database_version');
	global $wpdb;
	$sql = sprintf("DROP TABLE IF EXISTS %s%s",
		$wpdb->prefix,
		WOOCEKMUTASI_TABLE_TRANSACTION
	);
	$wpdb->query($sql);
	$sql = sprintf("DROP TABLE IF EXISTS %s%s",
		$wpdb->prefix,
		WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE
	);
	$wpdb->query($sql);
}
// INIT
function woocekmutasi_check() {
    if (get_site_option('woocekmutasi_database_version') != WOOCEKMUTASI_VERSION) {
        woocekmutasi_install() ;
    }
}
//---------------------
// Start Classes
//---------------------
add_action('plugins_loaded', 'woocommerce_gateway_woocekmutasi_init', 0);
function woocommerce_gateway_woocekmutasi_init() {

	if (!class_exists( 'WC_Payment_Gateway' )) {
		return;
	}

	class WC_WooCekmutasi_Gateway extends WC_Payment_Gateway
	{
		private $bank_local = array(
			array('code' => 'bri', 'name' =>'Bank BRI'),
			array('code' => 'bni', 'name' =>'Bank BNI'),
			array('code' => 'mandiri_online', 'name' =>'Bank Mandiri'),
			array('code' => 'bca', 'name' =>'Bank BCA'),
			array('code' => 'bptn_jenius', 'name' =>'BTPN Jenius'),
			array('code' => 'ovo', 'name' =>'OVO'),
			array('code' => 'gopay', 'name' =>'GoPay'),
		);

		public static $already_calculate_unique_number_fee = false;
		public static $instance = null;
		protected $woocekmutasi_settings;
		public $id = 'woocekmutasi';
		public $title = "WooCekmutasi";
		public $method_title = 'WooCekmutasi';
		public $description = "WooCekmutasi";
		function __construct() {
			
			$plugin_dir = plugin_dir_url(__FILE__);
			$this->icon = apply_filters( 'woocommerce_gateway_icon', $plugin_dir.'/assets/images/logo.png' );
			$this->has_fields = true;
			$this->form_fields = get_woocekmutasi_settings();
			$this->init_settings();
			// Set settings
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			
			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
				add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
				add_action('woocommerce_api_'. strtolower(get_class($this)), array($this, 'check_ipn_response'));
			} else {
				add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
				add_action('woocommerce_receipt', array(&$this, 'receipt_page'));
				add_action('woocommerce_api', array($this, 'check_ipn_response'));
			}
			$this->woocommerce_woocekmutasi_calculate_unique();

			include_once(__DIR__ . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'cURL.php');
			include_once(__DIR__ . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Cekmutasi.php');
			$this->curl = new cURL();
			$this->cekmutasi = new Cekmutasi($this->settings);
			// GET headers
			if (isset($this->cekmutasi->cekmutasi_headers)) {
				if (is_array($this->cekmutasi->cekmutasi_headers) && (count($this->cekmutasi->cekmutasi_headers) > 0)) {
					foreach ($this->cekmutasi->cekmutasi_headers as $headKey => $headVal) {
						$this->curl->add_headers($headKey, $headVal);
					}
				}
			}
			// Payment Hook Listener
			add_action('valid_woocekmutasi_ipn_request', array($this, 'woocekmutasi_success_ipn_request'));
			// Success Payment
			add_action('valid_woocekmutasi_trans_payment', array($this, 'woocekmutasi_success_trans_payment'));
		}

		function woocekmutasi_ipn_querystring($vars) {
			$vars[] = 'type';
			$vars[] = 'bank';
			return $vars;
		}

		function check_ipn_response()
		{
			$input_params = $this->curl->php_input_request();
			$query_string = $this->curl->php_input_querystring();
			$bank_local = array();

			foreach ($this->bank_local as $bank)
			{
				$bank_local[] = $bank['code'];
			}

			if (isset($query_string['type']) && isset($query_string['bank']))
			{
				if ($query_string['type'] != FALSE)
				{
					$query_string['type'] = (is_string($query_string['type']) ? strtolower($query_string['type']) : 'ipn');
					$query_string['bank'] = (is_string($query_string['bank']) ? strtolower($query_string['bank']) : 'all');
					if (!in_array($query_string['bank'], $bank_local))
					{
						exit("Bank code not in bank local listed");
					}

					// Cek if type = ipn
					$Datezone = new DateTime();
					$Datezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
					$Datetime_Range = array(
						'current'		=> $Datezone->format('Y-m-d H:i:s'),
					);

					if ($query_string['type'] === 'ipn')
					{
						global $wpdb;
						$insert_ipn_params = array(
							'payment_bank'			=> $query_string['bank'],
							'input_data'			=> json_encode($input_params, JSON_UNESCAPED_UNICODE),
							'input_datetime'		=> $Datetime_Range['current'],
						);

						$x = json_decode($insert_ipn_params['input_data'], true);
						
						if( !empty($x['header']['Api-Signature']) )
						{
						    if( !hash_equals($this->cekmutasi->api_signature, $x['header']['Api-Signature']) )
						    {
			        	        exit("Invalid Api Signature");
			        	    }
						}
						else
						{
						    exit("Undefined Api Signature");
						}

						$wpdb->insert(sprintf("%s%s", $wpdb->prefix, WOOCEKMUTASI_TABLE_TRANSACTION_IPN), $insert_ipn_params);
						$new_ipn_seq = $wpdb->insert_id;
						$sql = sprintf("SELECT input_data FROM %s%s WHERE seq = '%d'",
							$wpdb->prefix,
							WOOCEKMUTASI_TABLE_TRANSACTION_IPN,
							$new_ipn_seq
						);

						$ipn_data = $wpdb->get_row($sql);
						if (isset($ipn_data->input_data))
						{
							try
							{
								$ipn_input_data = json_decode($ipn_data->input_data);
							}
							catch (Exception $ex)
							{
								exit("Cannot json decoded input IPN: {$ex->getMessage()}");
								//$ipn_input_data = false;
							}
						}
						else
						{
							exit("No input data from ipn logs db.");
						}
						
						
						$mutasi_data = array();
						if (isset($ipn_input_data->body->action) && isset($ipn_input_data->body->content->data))
						{
							$ipn_input_data->body->action = strtolower($ipn_input_data->body->action);
							if ($ipn_input_data->body->action == 'payment_report')
							{
								if (is_array($ipn_input_data->body->content->data) && (count($ipn_input_data->body->content->data) > 0))
								{
									foreach ($ipn_input_data->body->content->data as $content_data)
									{
										if (isset($content_data->type) && isset($content_data->amount) && isset($content_data->description))
										{
											if (strtolower($content_data->type) === 'credit')
											{
												$cekmutasiDatezone = new DateTime();
												$cekmutasiDatezone->setTimestamp($content_data->unix_timestamp);
												$cekmutasiDatezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
												$mutasi_data[] = array(
													'payment_bank'				=> $ipn_input_data->body->content->service_code,
													'payment_amount'			=> sprintf('%.02f', $content_data->amount),
													'payment_description'		=> sprintf("%s", $content_data->description),
													'payment_datetime'			=> $cekmutasiDatezone->format('Y-m-d H:i:s'),
													'payment_type'				=> sprintf("%s", $content_data->type),
												);
											}
										}
									}
								}
							}
						}
						
						$Datetime_Range['payment_expired'] = '';
						$all = 'all';
						if (count($mutasi_data) > 0)
						{
							foreach ($mutasi_data as $mutasi)
							{
								$sql = sprintf("SELECT * FROM %s%s WHERE (payment_bank IN('%s', '%s') AND order_total = '%s' AND order_status IN('pending', 'waiting', 'on-hold')) AND ('%s' BETWEEN payment_insert AND DATE_ADD(payment_insert, INTERVAL %d DAY)) ORDER BY payment_insert DESC LIMIT 1",
									$wpdb->prefix,
									WOOCEKMUTASI_TABLE_TRANSACTION,
									$mutasi['payment_bank'],
									$all,
									$mutasi['payment_amount'],
									$mutasi['payment_datetime'],
									$this->settings['change_day']
								);
						        
								//echo $sql;
								$order_payment_data = $wpdb->get_row($sql);
						
								if (isset($order_payment_data->order_id))
								{
									$update_trans_params = array(
									    'payment_bank'              => $mutasi['payment_bank'],
										'order_status'				=> $this->settings['success_status'],
										'order_datetime_update'		=> $Datetime_Range['current'],
										'ipn_data'					=> json_encode($mutasi, JSON_UNESCAPED_UNICODE),
									);
									$wpdb->update(sprintf("%s%s", $wpdb->prefix, WOOCEKMUTASI_TABLE_TRANSACTION), $update_trans_params, array('seq' => $order_payment_data->seq));
									do_action('valid_woocekmutasi_ipn_request', $order_payment_data);
									continue;
								}
							}
						}
					}
					else
					{
						exit("Type should be ipn.");
					}
				}
				else
				{
					exit("No type params (should be ipn) and bank params.");
				}
			}
			exit;
		}

		function woocekmutasi_success_ipn_request($order_payment_data)
		{
			global $woocommerce;
			$order = new WC_Order($order_payment_data->order_id);
			$order_data = $order->get_data();

			if( in_array(strtoupper($order_data['status']), ['PENDING', 'ON-HOLD']) )
			{
				$order->update_status($this->settings['success_status']);
				
				if( $this->settings['verify_ipn'] == 'yes' )
				{
					$this->set_woocekmutasi_payment_status($order, $order_payment_data);
				}
				else
				{
					$order->payment_complete();
				}
			}
		}

		function woocekmutasi_success_trans_payment($order_payment_data)
		{
			global $woocommerce;
			$order = new WC_Order($order_payment_data->order_id);
			$order->payment_complete();
		}

		private function set_woocekmutasi_payment_status($order, $order_payment_data)
		{
			$collect = array(
				'cekmutasi'				=> array(),
			);

			// Get Transaction Data
			$collect['transaction_data'] = $this->get_trans_data_by_seq($order_payment_data->seq);

			if (!isset($collect['transaction_data']->order_id))
			{
				exit("Order data not exists on database");
			}

			$Datezone = new DateTime();
			$Datezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
			$Datetime_Range = array(
				'current'		=> $Datezone->format('Y-m-d H:i:s'),
			);

			$collect['bank_local'] = array();
			foreach ($this->bank_local as $bank) {
				$collect['bank_local'][] = $bank['code'];
			}

			if (!in_array($order_payment_data->payment_bank, $collect['bank_local']))
			{
				$collect['input_params']['payment_bank'] = 'all';
			}

			if ($collect['transaction_data']->order_status != 'pending')
			{
				$collect['cekmutasi']['input_params'] = $this->cekmutasi->generate_search_params($collect['transaction_data']);
				
				try
				{
					$apiEndpoint = '/bank/search';
					switch($collect['input_params']['payment_bank'])
					{
						case 'ovo':
							$apiEndpoint = '/ovo/search';
							break;

						case 'gopay':
							$apiEndpoint = '/gopay/search';
							break;

						default:
							break;
					}

					$collect['cekmutasi']['api'] = $this->curl->create_curl_request('POST', $this->cekmutasi->get_api_url($apiEndpoint), $this->curl->UA, $this->curl->generate_curl_headers($this->cekmutasi->cekmutasi_headers), $collect['cekmutasi']['input_params']);
				}
				catch (Exception $ex)
				{
					throw $ex;
					$collect['cekmutasi']['api'] = false;
				}

				if (isset($collect['cekmutasi']['api']['response']['body']))
				{
					try
					{
						$collect['cekmutasi']['tmp_data'] = json_decode($collect['cekmutasi']['api']['response']['body']);
					}
					catch (Exception $ex)
					{
						throw $ex;
						$collect['cekmutasi']['tmp_data'] = false;
					}

					$mutasi_data = array();
					if ( $collect['cekmutasi']['tmp_data']->success === true)
					{
						if (isset($collect['cekmutasi']['tmp_data']->response))
						{
							if (is_array($collect['cekmutasi']['tmp_data']->response) && (count($collect['cekmutasi']['tmp_data']->response) > 0))
							{
								foreach ($collect['cekmutasi']['tmp_data']->response as $data)
								{
									if ( $data->credit > 0 )
									{
										$cekmutasiDatezone = new DateTime();
										$cekmutasiDatezone->setTimestamp(strtotime($data->created_at));
										$cekmutasiDatezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
										$mutasi_data[] = array(
											'payment_bank'				=> (isset($data->service_code) ? $data->service_code : ''),
											'payment_amount'			=> sprintf('%.02f', $data->amount),
											'payment_description'		=> sprintf("%s", $data->description),
											'payment_datetime'			=> $cekmutasiDatezone->format('Y-m-d H:i:s'),
											'payment_type'				=> sprintf("%s", $data->type),
										);
									}
								}
							}
						}
					}

					if (count($mutasi_data) > 0)
					{
						global $wpdb;
						$all = 'all';
						foreach ($mutasi_data as $mutasi)
						{
							$sql = sprintf("SELECT * FROM %s%s WHERE (payment_bank IN('%s', '%s') AND order_total = '%s' AND order_status IN('pending', 'waiting', 'on-hold')) AND ('%s' BETWEEN payment_insert AND DATE_ADD(payment_insert, INTERVAL %d DAY)) ORDER BY payment_insert DESC LIMIT 1",
								$wpdb->prefix,
								WOOCEKMUTASI_TABLE_TRANSACTION,
								$mutasi['payment_bank'],
								$all,
								$mutasi['payment_amount'],
								$mutasi['payment_datetime'],
								$this->settings['change_day']
							);

							$order_payment_data = $wpdb->get_row($sql);
							if (isset($order_payment_data->order_id))
							{
								$update_trans_params = array(
								    'payment_bank'              => $mutasi['payment_bank'],
									'order_status'				=> $this->settings['success_status'],
									'order_datetime_update'		=> $Datetime_Range['current'],
									'ipn_data'					=> json_encode($mutasi, JSON_UNESCAPED_UNICODE),
								);
								$wpdb->update(sprintf("%s%s", $wpdb->prefix, WOOCEKMUTASI_TABLE_TRANSACTION), $update_trans_params, array('seq' => $order_payment_data->seq));
								// Accept payment
								# Do Accept
								do_action('valid_woocekmutasi_trans_payment', $order_payment_data);
							}
						}
					}
				}
			}
		}
		
		// FORM
		public function admin_options()
		{
			echo '<h2>'.__('Cekmutasi For WooCommerce', 'woocekmutasi').'</h2>';
			echo '<p>' .__('Cekmutasi for WooCommerce. Sistem validasi pembayaran bank otomatis oleh <a href="https://cekmutasi.co.id">https://cekmutasi.co.id</a>', 'woocekmutasi').'</p>';
			echo "<h3>WooCekmutasi Parameters</h3>\r\n";
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
			echo '<h3>URL IPN/Callback Notifikasi</h3>';
			echo '<table class="form-table">';
			echo "<tr><th style='width:15%;'>Bank</th><th>Address</th></tr>";
			foreach ($this->bank_local as $bank) {
				echo "<tr><td>{$bank['name']}</td><td>{$this->get_protocol_hostname()}/?wc-api=wc_woocekmutasi_gateway&type=ipn&bank={$bank['code']}</td></tr>";
			}
			echo '</table>';
		}
		
		//=============================================================
		// Transactions
		public function process_payment($order_id)
		{
			global $woocommerce;
			$cart = $woocommerce->cart;
			$order = new WC_Order($order_id);
			$unique_seq_session = WC()->session->get('unique_seq_session');
			$order_data = $order->get_data();
			// Set Order Data
			try
			{
				$new_trans_seq = $this->add_woocekmutasi_order_trans($order_data, $unique_seq_session);
			}
			catch (Exception $ex)
			{
				throw $ex;
				return false;
			}

			if ((int)$new_trans_seq > 0)
			{
				$transaction_data = $this->get_trans_data_by_seq($new_trans_seq);
			}
			else
			{
				$transaction_data = false;
			}

			if ($transaction_data != FALSE)
			{
				// Clear cart
				$this->clear_order_cart();
				if ($unique_seq_session != FALSE)
				{
					// Set session trans seq
					WC()->session->set('new_trans_seq', $new_trans_seq);
					// Clear unique session
					WC()->session->__unset('unique_seq_session');
				}
			
				$return = array(
					'result' 	=> 'success',
					'redirect' 	=> add_query_arg('order-pay', $order_data['id'], add_query_arg('key', $order_data['order_key'], get_permalink(woocommerce_get_page_id('pay')))),
				);
			}
			else
			{
				$return = array(
					'result'	=> 'failure',
				);
			}

			return $return;
		}

		function clear_order_cart()
		{
			add_action('init', 'woocommerce_clear_cart_url');
			global $woocommerce;
			$woocommerce->cart->empty_cart();
		}

		function add_woocekmutasi_order_trans($order_data, $unique_seq)
		{
			if (!is_array($order_data)) {
				return false;
			}

			$Datezone = new DateTime();
			$Datezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
			// Get Unique Data
			$unique_seq = (int)$unique_seq;
			global $wpdb;
			$sql = sprintf("SELECT * FROM %s%s WHERE seq = '%d'",
				$wpdb->prefix,
				WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE,
				$unique_seq
			);

			try
			{
				$unique_data = $wpdb->get_row($sql);
			}
			catch (Exception $ex)
			{
				throw $ex;
				return false;
			}

			if (!isset($unique_data->unique_amount))
			{
				return false;
			}

			$insert_trans_params = array(
				'order_id'							=> (isset($order_data['id']) ? $order_data['id'] : 0),
				'order_key'							=> $order_data['order_key'],
				'order_customer'					=> $order_data['customer_id'],
				'order_address_billing'				=> json_encode($order_data['billing'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
				'order_address_shipping'			=> json_encode($order_data['shipping'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
				'order_datetime'					=> (isset($order_data['date_created']->date) ? $order_data['date_created']->date : $Datezone->format('Y-m-d H:i:s')),
				'order_currency'					=> $order_data['currency'],
				// Amount
				'order_amount_shipping'				=> $order_data['shipping_total'],
				'order_amount_tax'					=> $order_data['total_tax'],
				'order_amount_total'				=> $order_data['total'],
				// Unique
				'unique_amount'						=> $unique_data->unique_amount,
				'unique_type'						=> $this->settings['unique_type'],
				'order_total'						=> $order_data['total'],
				'order_status'						=> $order_data['status'],
				'order_datetime_create'				=> $Datezone->format('Y-m-d H:i:s'),
				'order_datetime_update'				=> NULL,
				//======================================================================
				'payment_bank'						=> 'all', // Must be separated by plugin
				//======================================================================
				'payment_cekmutasi_durasi_unit'		=> 'day',
				'payment_cekmutasi_durasi_unit'		=> (int)$this->settings['change_day'],
				'payment_insert'					=> $Datezone->format('Y-m-d H:i:s'),
				'payment_data'						=> json_encode($order_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
				'ipn_data'							=> '',
			);

			$wpdb->insert(($wpdb->prefix . WOOCEKMUTASI_TABLE_TRANSACTION), $insert_trans_params);
			$new_trans_seq = $wpdb->insert_id;
			$sql = sprintf("UPDATE %s%s SET trans_seq = '%d' WHERE seq = '%d'",
				$wpdb->prefix,
				WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE,
				$new_trans_seq,
				$unique_data->seq
			);

			$wpdb->query($sql);
			// Delete another
			$sql = sprintf("DELETE FROM %s%s WHERE (trans_seq = '%d' AND trans_user = '%d')",
				$wpdb->prefix,
				WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE,
				0,
				intval(get_current_user_id())
			);

			$wpdb->query($sql);
			// Return new_trans_seq
			return $new_trans_seq;
		}

		private function get_trans_data_by_seq($seq)
		{
			global $wpdb;
			$sql = sprintf("SELECT * FROM %s%s WHERE seq = '%d'",
				$wpdb->prefix,
				WOOCEKMUTASI_TABLE_TRANSACTION,
				$seq
			);
			return $wpdb->get_row($sql);
		}

		private function get_trans_data_by_orderid($order_id)
		{
			$order_id = (is_numeric($order_id) ? (int)$order_id : 0);
			global $wpdb;
			$sql = sprintf("SELECT * FROM %s%s WHERE order_id = '%d' ORDER BY payment_insert DESC LIMIT 1",
				$wpdb->prefix,
				WOOCEKMUTASI_TABLE_TRANSACTION,
				$order_id
			);
			return $wpdb->get_row($sql);
		}

		private function set_trans_data_payment_bank_by_seq($seq = 0, $bank_code = 'all')
		{
			$seq = (is_numeric($seq) ? (int)$seq : 0);
			$bank_code = (is_string($bank_code) ? strtolower($bank_code) : 'all');
			global $wpdb;
			$sql = sprintf("UPDATE %s%s SET payment_bank = '%s' WHERE seq = '%d'",
				$wpdb->prefix,
				WOOCEKMUTASI_TABLE_TRANSACTION,
				$bank_code,
				$seq
			);
			$wpdb->query($sql);
		}
		
		public function receipt_page($order)
		{
			$collect = array();
			$collect['order_pay_id'] = get_query_var('order-pay', 0);
			$collect['order_pay_id'] = (int)$collect['order_pay_id'];
			$collect['new_trans_seq'] = WC()->session->get('new_trans_seq');
			$collect['new_trans_seq'] = (int)$collect['new_trans_seq'];
			// Get Transaction Data
			$collect['transaction_data'] = $this->get_trans_data_by_seq($collect['new_trans_seq']);
			if (!isset($collect['transaction_data']->order_id)) {
				exit("Your order is awaiting payment validation");
			}
			$Datezone = new DateTime();
			$Datezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
			$Datetime_Range = array(
				'current'		=> $Datezone->format('Y-m-d H:i:s'),
			);

			$collect['input_post'] = file_get_contents("php://input");
			
			try
			{
				parse_str($collect['input_post'], $collect['input_params']);
			}
			catch (Exception $ex)
			{
				throw $ex;
				$collect['input_params'] = false;
			}

			if ($collect['input_params'] != FALSE)
			{
				if (isset($collect['input_params']['payment_bank']))
				{
					$collect['input_params']['payment_bank'] = (is_string($collect['input_params']['payment_bank']) ? strtolower($collect['input_params']['payment_bank']) : '');
					$collect['bank_local'] = array();
					foreach ($this->bank_local as $bank) {
						$collect['bank_local'][] = $bank['code'];
					}
					if (strlen($collect['input_params']['payment_bank']) > 0)
					{
						if (!in_array($collect['input_params']['payment_bank'], $collect['bank_local']))
						{
							$collect['input_params']['payment_bank'] = 'all';
						}

						if ($collect['transaction_data']->order_status == 'pending')
						{
							$this->set_trans_data_payment_bank_by_seq($collect['transaction_data']->seq, $collect['input_params']['payment_bank']);
							# Remove session
							WC()->session->__unset('new_trans_seq');
							// Set to setting_success
							if (isset($collect['transaction_data']->order_id))
							{
								global $woocommerce;
								$order = new WC_Order($collect['transaction_data']->order_id);
							}
						}

						$collect['transaction_data'] = $this->get_trans_data_by_seq($collect['transaction_data']->seq);
						$order = new WC_Order($collect['transaction_data']->order_id);
					}
				}
			}

			$collect['order_is_valid'] = isset($collect['transaction_data']->order_id) ? true : false;
			$collect['payment_instruction'] =  $this->generate_woocekmutasi_order_form($order, $collect['transaction_data']->payment_bank, $collect['transaction_data'], $collect['order_is_valid']);
			echo $collect['payment_instruction'];
		}

		private function generate_woocekmutasi_order_form($order = null, $bank_code, $order_transaction_data, $is_valid = false)
		{
			if (!isset($order)) {
				return false;
			}	
			
			$order_form = array(
				'customer_details'			=> array(),
				'billing_address'			=> array(),
			);

			$bank_code = (is_string($bank_code) ? strtolower($bank_code) : 'all');
			if ($is_valid === true)
			{
				$html_order = <<<HTMLORDER
				<div id="payment-instruction">
					<h3 class="alert alert-info">Silahkan transfer TEPAT sesuai nominal agar pembayaran tervalidasi otomatis</h3>
					<form action="" method="post">
						<div class="alert alert-info">
							{$this->description}
						</div>
						<label for="payment-bank">Konfirmasi bank yang Anda gunakan untuk melakukan pembayaran</label>
						<br/>
						<select id="payment-bank" class="form-control" name="payment_bank">
							[#BANK_LOCAL#]
						</select>
						<br/>
						<button type="submit" id="payment-instruction-btn" class="button alt">
							Submit
						</button>
					</form>
					
				</div>
HTMLORDER;
				$bank_string = "";
				foreach ($this->bank_local as $bank)
				{
					$bank_is_selected = ($bank['code'] == $bank_code ? ' selected="selected"' : '');
					$bank_string .= "<option value='{$bank['code']}'{$bank_is_selected}>{$bank['name']}</option>";
				}
				$html_order = str_replace('[#BANK_LOCAL#]', $bank_string, $html_order);
			}
			else
			{
				$html_order = '<h3 class="alert alert-info">Order seem not valid</h3>';
			}
			
			return $html_order;
		}
		
		function clear_cart()
		{
			global $woocommerce;
			add_action('init', 'woocommerce_clear_cart_url');
			$woocommerce->cart->empty_cart();
		}
		
		// Backward compatibility WC v3 & v2
		function getOrderProperty($order, $property)
		{
			$functionName = "get_".$property;
			if (method_exists($order, $functionName))
			{ // WC v3
				return (string)$order->{$functionName}();
			}
			else
			{ // WC v2
				return (string)$order->{$property};
			}
		}
		
		//------------------------------------------------------------------
		// Utilities Functions
		private function get_protocol_hostname()
		{
			$protocol_hostname = '';
			if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
				if ( $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
					$_SERVER['HTTPS']       = 'on';
					$_SERVER['SERVER_PORT'] = 443;
				}
			}
			if (isset($_SERVER['HTTPS'])) {
				$protocol_hostname = (($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http');
			} else {
				$protocol_hostname = (isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'http');
				$protocol_hostname = ((strtolower(substr($protocol_hostname, 0, 5)) =='https') ? 'https://': 'http://');
			}
			if (isset($_SERVER['HTTP_HOST'])) {
				$protocol_hostname .= $_SERVER['HTTP_HOST'];
			} else {
				$protocol_hostname .= (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
			}
			return $protocol_hostname;
		}

		//--------------------------------------------------------------------------------------------------------------
		// Unique number calculation
		//--------------------------------------------------------------------------------------------------------------
		function woocommerce_woocekmutasi_calculate_unique()
		{
			global $woocommerce;
			if (is_admin() && !defined( 'DOING_AJAX' ) ) {
				return;
			}
			if (strtolower($this->settings['unique_status']) != 'yes') {
				return;
			}
			if ($woocommerce->cart->subtotal <= 0) {
				return;
			}
			if (self::$already_calculate_unique_number_fee === FALSE)
			{
				$unique_params = array();
				//----------------------------------------------------------------
				$Datezone = new DateTime();
				$Datezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
				
				$unique_params['unique_amount'] = $this->woocekmutasi_generate_new_unique($this->settings['unique_range_unit'], $this->settings['unique_range_amount']);
				if ($this->settings['unique_type'] == 'decrease') {
					$unique_params['unique_amount'] = (int) -$unique_params['unique_amount'];
				}
				// Insert new to db
				global $wpdb;
				$insert_params = array(
					'trans_seq'					=> 0,
					'trans_user'				=> intval(get_current_user_id()),
					'unique_payment_gateway'	=> $this->id,
					'unique_unit_name'			=> $this->settings['unique_range_unit'],
					'unique_unit_amount'		=> $this->settings['unique_range_amount'],
					'unique_label'				=> $this->settings['unique_label'],
					'unique_amount'				=> $unique_params['unique_amount'],
					'unique_date'				=> $Datezone->format('Y-m-d'),
					'unique_datetime'			=> $Datezone->format('Y-m-d H:i:s'),
				);
				$wpdb->insert(($wpdb->prefix . WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE), $insert_params);
				$new_unique_seq = $wpdb->insert_id;
				
				$get_unique_session = WC()->session->get('unique_seq_session');
				if (($get_unique_session == FALSE) || ($get_unique_session == NULL)) {
					WC()->session->set('unique_seq_session', $new_unique_seq);
				}
				$this->woocekmutasi_validate_unique($woocommerce, $new_unique_seq);
			}
		}

		private function woocekmutasi_generate_new_unique($string_unit = 'day', $int_amount = 0)
		{
			$string_unit = (is_string($string_unit) ? strtolower($string_unit) : 'day');
			if (!in_array($string_unit, array('minute', 'hour', 'day'))) {
				$string_unit = 'day';
			}

			$int_amount = (int)$int_amount;
			// Include libraries
			include_once(__DIR__ . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DateZone.php');
			$datezone = new DateZone();
			$date_stopping = new DateTime();
			$date_stopping->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
			switch (strtolower($string_unit)) {
				case 'minute':
					$date_starting = $datezone->reduce_date_by('MINUTE', $int_amount, $date_stopping);
				break;
				case 'hour':
					$date_starting = $datezone->reduce_date_by('HOUR', $int_amount, $date_stopping);
				break;
				case 'day':
				default:
					$date_starting = $datezone->reduce_date_by('DAY', $int_amount, $date_stopping);
				break;
			}

			// Query by unit
			$sql = sprintf("SELECT COUNT(seq) AS value FROM %s%s WHERE (unique_unit_name = '%s' AND unique_unit_amount = '%d')",
				'[#WPDB_PREFIX#]',
				'[#TABLE_UNIQUE#]',
				$string_unit,
				$int_amount
			);

			switch (strtolower($string_unit))
			{
				case 'minute':
					$sql .= sprintf(" AND (unique_datetime BETWEEN '%s' AND '%s')",
						$date_starting->format('Y-m-d H:i'),
						$date_stopping->format('Y-m-d H:i')
					);
					break;

				case 'hour':
					$sql .= sprintf(" AND (unique_datetime BETWEEN '%s' AND '%s')",
						$date_starting->format('Y-m-d H'),
						$date_stopping->format('Y-m-d H')
					);
					break;

				case 'day':
				default:
					$sql .= sprintf(" AND (DATE(unique_datetime) BETWEEN '%s' AND '%s')",
						$date_starting->format('Y-m-d'),
						$date_stopping->format('Y-m-d')
					);
					break;
			}
			//--------------------------------------------------------------------------------

			do {
				$unique_number = $this->woocekmutasi_get_new_unique_number();
				$rows = $this->woocekmutasi_check_new_unique($unique_number, $sql);
			} while ($rows > 0);

			return $unique_number;
		}

		private function woocekmutasi_get_new_unique_number()
		{
			$int_random = mt_rand($this->settings['unique_starting'], $this->settings['unique_stopping']);
			return (int)$int_random;
		}

		private function woocekmutasi_check_new_unique($unique_number, $sql_string)
		{
			global $wpdb;
			$rows = 0;
			$unique_number = (int)$unique_number;
			$sql = (is_string($sql_string) ? $sql_string : '');

			if (strlen($sql) > 0)
			{
				$sql .= sprintf(" AND (unique_amount = '%d')", $unique_number);
				$sql .= " AND (trans_user != 0)";
			}
			$sql = str_replace('[#WPDB_PREFIX#]', $wpdb->prefix, $sql);
			$sql = str_replace('[#TABLE_UNIQUE#]', WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE, $sql);
			
			try
			{
				$row_data = $wpdb->get_row($sql);
			}
			catch (Exception $ex)
			{
				throw $ex;
				return false;
			}

			$rows = (isset($row_data->value) ? $row_data->value : 0);
			return $rows;
		}

		private function woocekmutasi_validate_unique($woocommerce, $unique_seq)
		{
			$unique_seq = (int)$unique_seq;
			self::$already_calculate_unique_number_fee = TRUE;
			/*
			global $wpdb;
			if ($unique_seq > 0) {
				$sql = sprintf("SELECT * FROM %s%s WHERE seq = '%d' LIMIT 1",
					$wpdb->prefix,
					WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE,
					$unique_seq
				);
				try {
					$unique_data = $wpdb->get_row($sql);
				} catch (Exception $ex) {
					throw $ex;
					return false;
				}
				$Datezone = new DateTime();
				$Datezone->setTimezone(new DateTimeZone(WOOCEKMUTASI_TIMEZONE));
				if ($unique_data != FALSE) {
					self::$already_calculate_unique_number_fee = TRUE;
					// Add to cart
					if(isset($unique_data->unique_amount)) {
						$woocommerce->cart->add_fee($this->settings['unique_label'], $unique_data->unique_amount, true, '');
					}
				}
			} else {
				return false;
			}
			*/
		}

		//=======================================================
		public static function get_instance() {
			$instance = new WC_WooCekmutasi_Gateway();
			return $instance;
		}
	}
	
	// Add to WooCommerce Gateway
	function add_woocekmutasi_payment_gateway($methods) {
		$methods[] = 'WC_WooCekmutasi_Gateway';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_woocekmutasi_payment_gateway');
	//----------------------------------------

}
add_action('init', 'woocekmutasi_check');

// Unique number calculation
//---------------------------------
function add_unique_amount_to_cart()
{
	global $wpdb, $woocommerce;
	$get_unique_session_seq = WC()->session->get('unique_seq_session');
	if ((int)$get_unique_session_seq > 0)
	{
		$sql = sprintf("SELECT * FROM %s%s WHERE seq = '%d'",
			$wpdb->prefix,
			WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE,
			$get_unique_session_seq
		);

		$unique_data = $wpdb->get_row($sql);
		if (isset($unique_data->unique_label) && isset($unique_data->unique_amount) && isset($unique_data->unique_payment_gateway))
		{
			if ($woocommerce->session->chosen_payment_method == $unique_data->unique_payment_gateway)
			{
				if (!is_cart())
				{
					$woocommerce->cart->add_fee($unique_data->unique_label, $unique_data->unique_amount, true, '');
				}
			}
		}
	}
	else
	{
		return false;
	}
}
add_action('woocommerce_cart_calculate_fees', 'add_unique_amount_to_cart');