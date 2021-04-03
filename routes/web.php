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
    return $router->app->version();
});

$router->post('/admin', 'AdminController@index');
$router->post('/admin_detail', 'AdminController@detail');
$router->post('/simpan_admin', 'AdminController@store');
$router->post('/edit_admin', 'AdminController@edit');
$router->post('/del_admin', 'AdminController@del');
$router->post('/login_admin', 'AdminController@login_cms');

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
$router->post('/test_email', 'MemberController@test_mail');
$router->post('/test_ewallet', 'MemberController@test_ewallet');
$router->post('/test_sms', 'MemberController@test_sms');

$router->post('/category', 'CategoryController@index');
$router->post('/simpan_category', 'CategoryController@store');
$router->post('/del_category', 'CategoryController@proses_delete');
$router->get('/category', 'CategoryController@getCategory');

$router->post('/banner', 'BannerController@index');
$router->post('/simpan_banner', 'BannerController@store');
$router->post('/del_banner', 'BannerController@proses_delete');

$router->post('/product', 'ProductController@index');
$router->post('/simpan_product', 'ProductController@store');
$router->post('/del_product', 'ProductController@proses_delete');
$router->post('/product_detail', 'ProductController@detail');
$router->post('/product_img', 'ProductController@get_img');
$router->post('/upl_img_prod', 'ProductController@upload_img');
$router->post('/del_img_prod', 'ProductController@proses_delete_list_img');
$router->get('/product', 'ProductController@getProduct');

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
$router->post('/get_list_ongkir', 'MasterController@get_ongkir');
$router->get('/test_ongkir', 'MasterController@test_ongkir');

$router->post('/submit_transaksi', 'TransaksiController@store');
$router->post('/list_transaksi', 'TransaksiController@index');
$router->post('/transaksi_detail', 'TransaksiController@transaksi_detail');

$router->post('/redirect', 'DokuController@redirect_va');
$router->post('/notify', 'DokuController@notify');