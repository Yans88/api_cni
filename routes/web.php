<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    // return $router->app->version();
    $end_member =  date('Y-m-d', strtotime('+1 years'));
    return 'CMS'.rand(10000, 99999);
});

$router->get('/payment_suite/{id_transaksi}', function ($id_transaksi)  {
	$tgl = date('Y-m-d H:i:s');
	$base64 = base64_decode($id_transaksi);
	$vowels = array("n", "c", "i", "C", "I", "N");
	$_id_transaksi = str_replace($vowels,"",$base64);
	$where = array('transaksi.status' => 0, 'id_transaksi' => $_id_transaksi);
		$data = DB::table('transaksi')->select(
            'transaksi.*',
            'members.nama as nama_member',
            'members.email'
        )
            ->where($where)
            ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
		
		$id_transaksi = $data->id_transaksi;
		$expired_payment = date("Y-m-d H:i", strtotime($data->expired_payment));
        $ttl_price = $data->nominal_doku;
		$MALLID = env('MALLID_CC');
		$shared_key = env('SHAREDKEY');
        $words = $ttl_price . '.00' . $MALLID . ''.$shared_key. '' . $id_transaksi;
		$basket = 'Paket ' . $data->nama_member . ' No. Order #' . $id_transaksi . ',' . number_format($ttl_price, 2, ".", "") . ',1,' . number_format($ttl_price, 2, ".", "");
		if($expired_payment <= $tgl){
			$res = array(
				'mall_id'		=> 0,
				'nama_member'	=> $data->nama_member,
				'email'			=> $data->email,
				'basket'		=> '',
				'ttl_price'		=> '',
				'words'			=> '',
				'tgl'			=> '',
				'session_id'	=> '',
				'id_transaksi'	=> 0
			);
		}else{
			$res = array(
				'mall_id'		=> $MALLID,
				'nama_member'	=> $data->nama_member,
				'email'			=> $data->email,
				'basket'		=> $basket,
				'ttl_price'		=> $ttl_price,
				'words_ori'		=> $words,
				'words'			=> sha1($words),
				'tgl'			=> date('YmdHis'),
				'session_id'	=> $data->session_id,
				'id_transaksi'	=> $_id_transaksi
			);
		}
		
    return view('greeting', $res);
});



$router->post('/admin', 'AdminController@index');
$router->post('/admin_detail', 'AdminController@detail');
$router->post('/simpan_admin', 'AdminController@store');
$router->post('/edit_admin', 'AdminController@edit');
$router->post('/del_admin', 'AdminController@del');
$router->post('/login_admin', 'AdminController@login_cms');

$router->post('/level', 'LevelController@index');
$router->post('/simpan_level', 'LevelController@store');
$router->post('/del_level', 'LevelController@proses_delete');
$router->post('/detail_level', 'LevelController@detail');

$router->post('/members', 'MemberController@index');
$router->post('/profile_member', 'MemberController@detail');
$router->post('/register_member', 'MemberController@reg');
$router->post('/login', 'MemberController@login_member');
$router->post('/edit', 'MemberController@edit');
$router->post('/chg_pass', 'MemberController@change_pass');
$router->post('/upload_photo', 'MemberController@upl_photo');
$router->post('/verify_phone', 'MemberController@verify_phone');
$router->post('/resend_code_phone', 'MemberController@resend_code_phone');
$router->get('/verify_email/{id}', 'MemberController@verify_email');
$router->post('/forgot_pass', 'MemberController@forgot_pass');
$router->post('/add_wishlist', 'MemberController@add_wishlist');
$router->post('/del_wishlist', 'MemberController@del_wishlist');
$router->post('/get_mywishlist', 'MemberController@get_mywishlist');
$router->post('/add_address', 'MemberController@add_address');
$router->post('/get_address', 'MemberController@get_address');
$router->post('/del_address', 'MemberController@del_address');
$router->post('/history_transaksi', 'MemberController@history_transaksi');
$router->post('/set_fb', 'MemberController@akun_fb');
$router->post('/set_ig', 'MemberController@akun_ig');
$router->post('/set_akun_bank', 'MemberController@akun_bank');
$router->post('/req_cashout', 'MemberController@req_cashout');
$router->post('/history_cashout', 'MemberController@history_cashout');
$router->post('/history_wallet', 'MemberController@history_wallet');
$router->post('/history_notif', 'MemberController@history_notif');
$router->post('/test_email', 'MemberController@test_mail');
$router->post('/test_ewallet', 'MemberController@test_ewallet');
$router->post('/test_sms', 'MemberController@test_sms');
$router->post('/test_req', 'MasterController@test_req');
$router->post('/set_token_fcm', 'MemberController@set_token_fcm');
$router->post('/add_sm', 'MemberController@add_sm');
$router->post('/get_cniid_sm', 'MemberController@get_cniid_sm');
$router->post('/get_list_sm', 'MemberController@get_list_sm');
$router->post('/list_follower_sm', 'MemberController@get_list_follower_sm');

$router->post('/category', 'CategoryController@index');
$router->post('/simpan_category', 'CategoryController@store');
$router->post('/del_category', 'CategoryController@proses_delete');
$router->get('/category', 'CategoryController@getCategory');

$router->post('/banner', 'BannerController@index');
$router->post('/simpan_banner', 'BannerController@store');
$router->post('/del_banner', 'BannerController@proses_delete');

$router->post('/news', 'NewsController@index');
$router->post('/simpan_news', 'NewsController@store');
$router->post('/del_news', 'NewsController@proses_delete');
$router->get('/download_news/{id_news}', 'NewsController@downloadFile');

$router->post('/product', 'ProductController@index');
$router->post('/simpan_product', 'ProductController@store');
$router->post('/del_product', 'ProductController@proses_delete');
$router->post('/product_detail', 'ProductController@detail');
$router->post('/product_img', 'ProductController@get_img');
$router->post('/upl_img_prod', 'ProductController@upload_img');
$router->post('/del_img_prod', 'ProductController@proses_delete_list_img');
$router->get('/product', 'ProductController@getProduct');
$router->post('/simpan_pricelist', 'ProductController@store_pricelist');
$router->post('/del_pricelist', 'ProductController@del_pricelist');
$router->post('/get_pricelist', 'ProductController@get_pricelist');
$router->post('/test_pricelist', 'ProductController@test_get_hrg');

$router->post('/provinsi', 'ProvinsiController@index');
$router->post('/simpan_provinsi', 'ProvinsiController@store');
$router->post('/del_provinsi', 'ProvinsiController@proses_delete');
$router->post('/get_warehouse', 'ProvinsiController@get_wh');
$router->post('/add_warehouse', 'ProvinsiController@add_wh');
$router->post('/del_warehouse', 'ProvinsiController@del_wh');
$router->post('/get_area', 'ProvinsiController@get_area');
$router->post('/assign_area', 'ProvinsiController@assign_area');
$router->post('/remove_area', 'ProvinsiController@remove_area');

$router->post('/city', 'CityController@index');
$router->post('/simpan_city', 'CityController@store');
$router->post('/del_city', 'CityController@proses_delete');

$router->post('/kecamatan', 'KecController@index');
$router->post('/simpan_kec', 'KecController@store');
$router->post('/del_kec', 'KecController@proses_delete');

$router->post('/master_data', 'MasterController@index');
$router->post('/upd_setting', 'MasterController@upd_setting');
$router->post('/get_list_ongkir', 'MasterController@get_ongkirs');
$router->post('/get_ongkir_lion', 'MasterController@get_ongkir_lp');
$router->post('/get_list_dc', 'MasterController@get_dc');
$router->post('/cek_stok_dc', 'MasterController@cek_stok_dc');
$router->post('/generate_resi', 'MasterController@generate_resi');
$router->get('/test_ongkir', 'MasterController@test_ongkir');
$router->get('/test_ongkir_lion', 'MasterController@test_ongkir_lion');
$router->get('/test_send_order/{id_transaksi}', 'MasterController@test_send_order');

$router->post('/submit_transaksi', 'TransaksiController@store');
$router->post('/list_transaksi', 'TransaksiController@index');
$router->post('/transaksi_detail', 'TransaksiController@transaksi_detail');
$router->post('/upd_cnote', 'TransaksiController@set_cnote');
$router->post('/tracking', 'TransaksiController@tracking');
$router->post('/set_onprocess', 'TransaksiController@set_onprocess');
$router->post('/signon_qris', 'TransaksiController@signon_qris');
$router->post('/submit_ulasan', 'TransaksiController@submit_ulasan');
$router->post('/appr_rej_ulasan', 'TransaksiController@approve_rej_ulasan');
$router->post('/list_ulasan', 'TransaksiController@list_ulasan');
$router->post('/set_complete', 'TransaksiController@set_stts');
$router->post('/set_hold', 'TransaksiController@set_hold');

$router->post('/redirect', 'DokuController@redirect_va');
$router->post('/notify', 'DokuController@notify');
$router->post('/notify_qris', 'DokuController@notify_qris');
$router->post('/generate_words', 'DokuController@generate_words');
// $router->get('/payment_suite/{id}', 'DokuController@payment_cc');

$router->get('/refund_ewallet', 'CronController@index');

$router->post('/voucher', 'Vouchers@index');
$router->post('/list_vouchers', 'Vouchers@get_list_vouchers');
$router->post('/simpan_voucher', 'Vouchers@store');
$router->post('/del_voucher', 'Vouchers@proses_delete');
$router->post('/publish_voucher', 'Vouchers@publish');
$router->post('/voucher_detail', 'Vouchers@detail');
$router->post('/list_produk', 'Vouchers@list_produk');
$router->post('/list_produk_available', 'Vouchers@list_produk_available');
$router->post('/assign_produk', 'Vouchers@assign_produk');
$router->post('/remove_produk', 'Vouchers@remove_produk');
$router->post('/list_member', 'Vouchers@list_member');
$router->post('/list_member_available', 'Vouchers@list_member_available');
$router->post('/assign_member', 'Vouchers@assign_member');
$router->post('/remove_member', 'Vouchers@remove_member');
$router->post('/voucher_available', 'Vouchers@get_voucher_available');

$router->post('/blast_notif', 'BlastController@index');
$router->post('/simpan_blast', 'BlastController@store');
$router->post('/blast_detail', 'BlastController@detail');
