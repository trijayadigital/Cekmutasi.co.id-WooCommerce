<?php
defined('WOOCEKMUTASI_TIMEZONE') OR define('WOOCEKMUTASI_TIMEZONE', 'Asia/Jakarta');
defined('WOOCEKMUTASI_VERSION') OR define('WOOCEKMUTASI_VERSION', '1.0.0');
defined('WOOCEKMUTASI_TABLE_TRANSACTION') OR define('WOOCEKMUTASI_TABLE_TRANSACTION', "woocommerce_cekmutasi_transactions");
defined('WOOCEKMUTASI_TABLE_TRANSACTION_IPN') OR define('WOOCEKMUTASI_TABLE_TRANSACTION_IPN', 'woocommerce_cekmutasi_transactions_ipn');
defined('WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE') OR define('WOOCEKMUTASI_TABLE_TRANSACTION_UNIQUE', "woocommerce_cekmutasi_transactions_unique");

function get_woocekmutasi_settings()
{
    $settings = array(
		'title' => array(
            'title' => __('Judul', 'woocekmutasi' ),
            'type' => 'text',
            'description' => __('WooCekmutasi', 'woocekmutasi'),
            'default' => __('WooCekmutasi', 'woocekmutasi'),
            'desc_tip'      => true,
        ),
        'description' => array(
			'title' => __('Deskripsi', 'woocommerce' ),
            'type' => 'textarea',
            'description' => __('Deskripsi yang dilihat user saat checkout, sebaiknya diisi informasi rekening bank tujuan pembayaran yang telah terdaftar di cekmutasi', 'woocekmutasi' ),
            'default' => '',
			'desc_tip' => false,
        ),
		'enabled' => array(
			'title' => __( 'Aktifkan/Nonaktifkan', 'woocekmutasi' ),
			'type' => 'checkbox',
			'label' => __( 'Aktifkan WooCekmutasi Payment Gateway', 'woocekmutasi' ),
			'default' => 'yes'
		),
		'mode' => array(
            'title' => __( 'Mode', 'woocekmutasi' ),
            'type' => 'select',
            'label' => __( '<br>Pilih Mode', 'woocekmutasi' ),
            'default'   =>  'testing',
            'options' => array(
                'sandbox'       => 'Sandbox',
                'live'    		=> 'Production'
            ),
            'id'   => 'woocekmutasi_mode'
        ),
		'unique_status' => array(
            'title' => __( 'Nominal Unik?', 'woocekmutasi' ),
            'type' => 'checkbox',
            'label' => __( 'Centang, untuk aktifkan fitur penambahan 3 angka unik di setiap akhir pesanan / order. Sebagai pembeda dari order satu dengan yang lainnya.', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_unique_status'
        ),
		'unique_label' => array(
            'title' => __( 'Label Nominal Unik', 'woocekmutasi' ),
            'type' => 'text',
            'default' => 'Kode Unik',
            'css'      => 'min-width:420px;',
            'label' => __( '<br>Label yang akan muncul di form checkout', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_unique_label',
			'desc_tip' => true,
        ),
		'unique_starting' => array(
            'title' => __( 'Batas Awal Angka Unik', 'woocekmutasi' ),
            'type' => 'number',
            'label' => __( '<br>Masukan batas awal angka unik', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_unique_starting',
            'default' => 1,
            'custom_attributes' => array(
                'min'  => 1,
                'max'  => 999
            ),
			'desc_tip' => true,
        ),
        'unique_stopping' => array(
            'title' => __( 'Batas Akhir Angka Unik', 'woocekmutasi' ),
            'type' => 'number',
            'label' => __( '<br>Masukan batas akhir angka unik', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_unique_stopping',
            'default' => 999,
            'custom_attributes' => array(
                'min'  => 1,
                'max'  => 999
            ),
			'desc_tip' => true,
        ),
		'unique_type' => array(
            'title' => __( 'Tipe Kalkulasi', 'woocekmutasi' ),
            'type' => 'select',
			'lable'	=> 'Kalkulasi kode unik',
            'description' => __( 'Tambahkan = Menambah unik number ke total harga<br/>Kurangi = Mengurangi total harga dengan unik number', 'woocekmutasi' ),
            'default'   =>  'increase',
            'options' => array(
                'increase'      => 'Tambahkan',
                'decrease'      => 'Kurangi'
            ),
            'id'   => 'woocekmutasi_unique_type',
			'desc_tip' => false,
        ),
		'unique_range_unit' => array(
            'title' => __( 'Satuan unik berdasarkan unit', 'woocekmutasi' ),
			'label' => 'Perhitungan Nominal Unik: Unit',
			'description' => __( 'Batas satuan perhitungan angka unik, default menggunakan satuan (hari)', 'woocekmutasi' ),
            'type' => 'select',
            'default'   =>  'day',
            'options' => array(
                'minute'	=> 'Minutes',
                'hour'		=> 'Hours',
				'day'		=> 'Days',
            ),
            'id'   => 'woocekmutasi_unique_range_unit',
			'desc_tip' => false,
        ),
		'unique_range_amount' => array(
            'title' => __( 'Satuan unik berdasarkan nominal', 'woocekmutasi' ),
            'type' => 'number',
			'label' => 'Perhitungan Unique Number: Amount',
            'description' => __( 'Jumlah berapa kali didalam unit untuk perhitungan angka unik, jika 1 hari berarti nominal unik akan berlaku selama 1 hari (24 jam) penuh', 'woocekmutasi' ),
			'id'   => 'woocekmutasi_unique_range_amount',
            'default' => 1,
            'custom_attributes' => array(
                'min'  => 1,
                'max'  => 2
            ),
			'desc_tip' => false,
        ),
		'success_status' => array(
            'title' => __( 'Status Berhasil', 'woocekmutasi' ),
            'type' => 'select',
			'label' => 'Status Berhasil',
            'description' => __( '<br>Status setelah berhasil menemukan order yang telah dibayar', 'woocekmutasi' ),
            'default'   =>  'processing',
            'options' => array(
                'completed'     => 'Completed',
                'on-hold'       => 'On Hold',
                'processing'    => 'Processing'
            ),
            'id'   => 'woocekmutasi_success_status'
        ),
        'change_day' => array(
            'title' => __( 'Perubahan status di hari ke?', 'woocekmutasi' ),
			'label' => 'Perubahan status di hari ke?',
            'type' => 'select',
            'description' => __( '<br>Setelah konsumen checkout dan belum bayar, pilih hari ke berapa status order berubah otomatis dari ON-HOLD ke PENDING.', 'woocekmutasi' ),
            'default'   =>  '0',
            'options' => array(
                '1'				=> 'H+1',
                '2'      		=> 'H+2',
                '3'      		=> 'H+3',
                '4'      		=> 'H+4',
                '5'      		=> 'H+5',
                '6'      		=> 'H+6',
                '7'      		=> 'H+7',
            ),
            'id'   => 'woocekmutasi_change_day'
        ),
        'verify_ipn' => array(
            'title' => __( 'Verifikasi Data IPN', 'woocekmutasi' ),
            'type' => 'checkbox',
            'label' => __( 'Aktifkan Untuk Mode Transaksi Riil dan Nonaktifkan untuk Testing', 'woocekmutasi' ),
            'default' => 'no'
        ),
        'server_ip' => array(
            'title' => __( 'IP Server Anda', 'woocekmutasi' ),
			'label' => __( 'IP Server Anda', 'woocekmutasi' ),
            'type' => 'text',
            'css'      => 'min-width:400px;',
            'description' => __('Tambahkan IP ini pada kolom Whitelist IP di menu <a href="https://cekmutasi.co.id/app/integration" target="_new">https://cekmutasi.co.id/app/integration</a>', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_server_ip',
            'default' => $_SERVER['SERVER_ADDR'],
			'dect_tip' => false,
        ),
		'api_key' => array(
            'title' => __( 'Api Key', 'woocekmutasi' ),
			'label' => __( 'Api Key', 'woocekmutasi' ),
            'type' => 'text',
            'css'      => 'min-width:400px;',
            'description' => __('Dapatkan API Key melalui : <a href="https://cekmutasi.co.id/app/integration" target="_new">https://cekmutasi.co.id/app/integration</a>', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_api_key',
			'dect_tip' => false,
        ),
		'api_signature' => array(
            'title' => __( 'Api Signature', 'woocekmutasi' ),
			'label' => __( 'Api Signature', 'woocekmutasi' ),
            'type' => 'text',
            'css'      => 'min-width:400px;',
            'description' => __('Dapatkan Api Signature melalui : <a href="https://cekmutasi.co.id/app/integration" target="_new">https://cekmutasi.co.id/app/integration</a>', 'woocekmutasi' ),
            'id'   => 'woocekmutasi_api_signature',
			'dect_tip' => false,
        ),

	);

	return $settings;
}


