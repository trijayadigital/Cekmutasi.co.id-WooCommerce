=== WooCekmutasi ===
Contributors: pstorenet
Tags: ecommerce, e-commerce, store, sales, sell, shop, cart, checkout, downloadable, downloads, payment, bca, mandiri, bni, bri, otomatis, mutasi, cekmutasi
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 3.4.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCekmutasi merupakan addon WooCommerce untuk mengintegrasikan layanan cekmutasi.co.id ke WooCommerce. Plugin ini akan melakukan validasi pembayaran bank secara otomatis

== Description ==

Cekmutasi.co.id merupakan layanan pengelolaan rekening terintegrasi yang membantu Anda mengelola banyak rekening dalam satu dashboard. Selain itu, Cekmutasi.co.id juga mendukung sistem validasi pembayaran otomatis berdasarkan nominal unik melalui konektivitas API

Beberapa bank nasional yang kami support, diantaranya :
* BCA,
* MANDIRI,
* BNI,
* BRI,
* dan terus bertambah.

Silahkan melakukan pendaftaran terlebih dahulu di (https://cekmutasi.co.id) untuk bisa menggunakan plugin ini.

== Installation ==

= Langkah ke-1 =
Cara menginstall plugin WooCekmutasi sangatlah mudah.

1. Pastikan Anda telah menginstall plugin WooCommerce karena ini merupakan addon untuk WooCommerce. Versi WooCommerce minimum untuk plugin ini adalah 3.1.0
2. Unggah plugin ini ke folder `/wp-content/plugins/woocekmutasi`, atau install langsung melalui WordPress plugin secara instan.
3. Aktifkan di menu 'Plugins' WordPress Anda.
4. Masuk ke menu WooCommerce -> Settings -> Payments lalu klik Manage pada WooCekmutasi.
5. Salin "URL IPN/Callback Notifikasi" berupa link contoh: `http://webtokoonline.com/?wc-api=wc_woocekmutasi_gateway&type=ipn&bank=bri`
6. Lalu lakukan langkah ke-2 di bawah ini.

= Langkah ke-2 =
Pastikan Anda daftar di web https://cekmutasi.co.id dan mempunyai minimal 1 akun rekening yang telah didaftarkan.

1. Kunjungi web https://cekmutasi.co.id/app/login lalu login.
2. Edit rekening yang akan digunakan untuk integrasi.
3. Masukkan `URL IPN/Callback Notifikasi` pada langkah pertama tadi.
5. Lalu simpan.

Dan silahkan mulai berjualan.

= Tutorial =
Selengkapnya silahkan kunjungi tutorial integrasi Cekmutasi.co.id dengan WooCommerce di sini:
[https://cekmutasi.co.id/app/docs/cara-install-dan-setting-plugin-integrasi-cekmutasi-di-wordpress-woocommerce/5]

== Frequently Asked Questions ==

= Bagaimana cara install dan integrasi dengan toko online saya? =

Selengkapnya silahkan kunjungi tutorial integrasi Cekmutasi.co.id dengan WooCommerce di sini:
[https://cekmutasi.co.id/app/docs/cara-install-dan-setting-plugin-integrasi-cekmutasi-di-wordpress-woocommerce/5]

= Apakah ada biaya langganan? =
Ya, untuk menggunakan layanan Cekmutasi.co.id kami menerapkan sistem deposit. Dan layanan mutasi ini akan dikenakan biaya sesuai paket yang dipilih.

= Apakah data saya aman? =
Ya, kami menjamin keamanan data Anda. Karena kami akan mengenkripsi data Anda, juga menggunakan protocol khusus dan menggunakan SSL yang akan mengenkripsi aktivitas Anda di browser. Sehingga keamanannya akan lebih optimal.

= Berapa kali mutasi akan update? =
Pengecekkan mutasi dilakukan 5 menit sekali.

= Apakah saya bisa akses Internet Banking saya bila menggunakan layanan ini? =
Anda bisa buka iBanking Anda kapanpun, tanpa terganggu oleh Cekmutasi.co.id. Cukup nonaktifkan mutasi di dashboard Cekmutasi.co.id lalu Anda bisa login ke ibanking

= Melalui apa saja saya akan menerima notifikasi? =
Sistem akan mengirim notifikasi setiap ada transaksi masuk kepada Anda melalui Email, API dan SMS (ringkasan harian).


== Changelog ==

= 1.0.0 =
* Inisialisasi rilis

= 2.0.0 =
* Perbaikan bug verifikasi IPN data
* Perbaikan kosa kata pengaturan
* Perbaikan struktur database

= 2.0.1 =
* Perbaikan versi & dokumentasi

== Upgrade Notice ==