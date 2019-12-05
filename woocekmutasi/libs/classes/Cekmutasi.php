<?php

if (!defined('ABSPATH')) { exit('No direct script access allowed'); }

class Cekmutasi
{
	public $api_endpoint = 'https://api.cekmutasi.co.id/v1';
	public $api_key = null;
	public $api_signature = null;
	
	public $cekmutasi_headers = array();

	function __construct($config = array())
	{
		if (isset($config['api_key'])) {
			$this->api_key = $config['api_key'];
		}

		if (isset($config['api_signature'])) {
			$this->api_signature = $config['api_signature'];
		}

		$this->cekmutasi_headers = $this->create_cekmutasi_headers();
	}
	
	private function create_cekmutasi_headers()
	{
		$cekmutasi_headers = array(
		    'Content-Type'      => 'application/x-www-form-urlencoded',
			'Accept'			=> 'application/json',
			'Api-Key'			=> $this->api_key
		);
		return $cekmutasi_headers;
	}
	
	function generate_search_params($transaction_data = null)
	{
		if (isset($transaction_data->payment_bank))
		{
			switch ($transaction_data->payment_bank)
			{
				case 'all':
					break;

				default:
					$query_params['service_code'] = $transaction_data->payment_bank;
					break;
			}
		}

		if (isset($transaction_data->order_total))
		{
			$transaction_data->order_total = sprintf("%.02f", $transaction_data->order_total);
			$query_params['amount'] = intval($transaction_data->order_total);
		}

		if (isset($transaction_data->payment_insert))
		{
			try
			{
				$payment_insert = new DateTime($transaction_data->payment_insert);
			}
			catch (Exception $ex)
			{
				throw $ex;
				$payment_insert = false;
			}

			if ($payment_insert != false)
			{
				$query_params['date'] = array(
					'from'			=> $payment_insert->format('Y-m-d H:i:s'),
				);

				switch (strtolower($transaction_data->payment_cekmutasi_durasi_unit))
				{
					case 'week':
						$payment_cekmutasi_durasi_amount = ($transaction_data->payment_cekmutasi_durasi_amount * 7);
						break;

					case 'day':
					default:
						$payment_cekmutasi_durasi_amount = $transaction_data->payment_cekmutasi_durasi_amount;
						break;
				}

				$payment_insert->add(new DateInterval("P{$payment_cekmutasi_durasi_amount}D"));
				$query_params['date']['to'] = $payment_insert->format('Y-m-d H:i:s');
			}

			$transaction_data->order_total = sprintf("%.02f", $transaction_data->order_total);
			$query_params['amount'] = intval($transaction_data->order_total);
		}

		return array('search' => $query_params);
	}

	function get_api_url($endpoint)
	{
		return $this->api_endpoint.'/'.ltrim($endpoint, '/');
	}

	function get_search_api($type, $input_params) {
		$collect = array();
		$type = (is_string($type) ? strtolower($type) : 'get');
		switch ($type) {
			
			
			case 'get':
			default:
				
			break;
		}
	}
}