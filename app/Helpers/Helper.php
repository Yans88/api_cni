<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Helper
{

    static function last_login($id_member = 0)
    {
        $tgl = date('Y-m-d H:i:s');
        DB::table('members')->where('id_member', $id_member)->update(['last_login' => $tgl]);
        return $id_member;
    }

    static function get_ewallet($cni_id = "", $param_from_fe = "", $api_name = "")
    {
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_GET_EWALLET');
        $token = env('TOKEN_GET_EWALLET');
        $curl = curl_init();
        $postfields = array(
            "nomorn" => $cni_id,
            "token" => $token
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        $dt_log = array();
        $dt_log = array(
            "api_name" => $api_name,
            "param_from_fe" => serialize($param_from_fe),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "created_at" => $tgl
        );
        $dt = array(
            "result" => isset($res[0]->RESULT) ? $res[0]->RESULT : '',
            "message" => isset ($res[0]->Message) ? $res[0]->Message : '',
            "saldo" => isset($res[0]->Saldo) || !empty($res[0]->Saldo) || (int)$res[0]->Saldo > 0 ? $res[0]->Saldo : 0,
            "statuswallet" => isset ($res[0]->statuswallet) ? $res[0]->statuswallet : ''
        );
        DB::table('log_api')->insert($dt_log);
        return $dt;
    }

    static function send_sms($nohp = "", $otp = "", $param_from_fe = "", $api_name = "")
    {
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_SEND_SMS');
        $token = env('TOKEN_SEND_SMS');
        $curl = curl_init();
        $postfields = array(
            "nomorn" => "",
            "token" => $token,
            "nohp" => $nohp,
            "otp" => $otp
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        $dt_log = array();
        $dt_log = array(
            "api_name" => $api_name,
            "param_from_fe" => serialize($param_from_fe),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        return $postfields;
    }

    static function trans_ewallet($action = "", $cni_id = "", $totalharga = "", $potongwallet = "", $id_transaksi = 0, $param_from_fe = "", $api_name = "", $tipe_trans = 1, $ket = '', $history = 0, $id_member = 0)
    {
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_' . $action);
        $token = env('TOKEN_' . $action);
        $curl = curl_init();
        $postfields = array();
        $dt = array();
        $postfields = array(
            "nomorn" => $cni_id,
            "totalharga" => $totalharga,
            "potongwallet" => $potongwallet,
            "potwallet" => $potongwallet,
            "noorder" => $id_transaksi,
            "token" => $token
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        $dt_log = array();
        $dt_history = array();
        $dt_log = array(
            "api_name" => $api_name,
            "param_from_fe" => serialize($param_from_fe),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "id_transaksi" => $id_transaksi,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        $err_code = isset($res->Errorcode) ? $res->Errorcode : '';

        if ((int)$err_code == 0 && $history == 1) {
            $tipe = 0;
            if ($action == "ALLOCATE_EWALLET" || $action == "PAID_EWALLET") {
                $tipe = 1; //pengurangan
            }
            if ($action == "REALLOCATE_EWALLET") {
                $tipe = 2; //penambahan atau pengembalian
            }
            $dt_history = array(
                "id_member" => $id_member,
                "saldo_transaksi" => $potongwallet,
                "tipe_trans" => $tipe_trans,
                "tipe" => $tipe,
                "id_act" => $id_transaksi,
                "keterangan" => $ket,
                "created_at" => $tgl
            );

            DB::table('history_ewallet')->insert($dt_history);
        }
        $dt = array(
            "err_code" => $err_code,
            "result" => isset($res->RESULT) ? $res->RESULT : '',
            "err_msg" => isset ($res->Message) ? $res->Message : '',
            "data" => $postfields

        );
        return $dt;

    }

    static function generate_qris($id_trans = 0, $amount = 0)
    {
        $result = array();
        $url_qris = env('URL_DOKU_QRIS');
        $clientSecret = env('CLIENTSECRET_DOKU_QRIS');
        $clientId = env('CLIENTID_DOKU_QRIS');
        $Sharedkey = env('SHAREDKEY_DOKU_QRIS');
        $systrace = $id_trans;
        $version = env('VERSION_SIGNON_DOKU_QRIS');
        $words = '';
        $words_ori_signon = $clientId . '' . $Sharedkey . '' . $systrace;
        $words = hash_hmac('sha1', $words_ori_signon, $clientSecret);
        $postfields = array();
        $postfields = array(
            "clientSecret" => $clientSecret,
            "clientId" => $clientId,
            "systrace" => $systrace,
            "words" => $words,
            "version" => $version
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url_qris . '/signon',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        if ((int)$res->responseCode > 0) {
            $result = array(
                'err_code' => $res->responseCode,
                'err_msg' => $res->responseMessage->id,
                'data' => $res,

            );
            return response($result);
            return false;
        }
        $words = '';
        $accessToken = isset($res->accessToken) ? $res->accessToken : '';
        $words_generate = $clientId . '' . $systrace . '' . $clientId . '' . $Sharedkey;
        $words = hash_hmac('sha1', $words_generate, $clientSecret);
        $id_transaksi = $systrace;
        $length_id = strlen($id_transaksi);
        if ($length_id < 15) {
            $id_transaksi = '00000000000000' . $systrace;
            $id_transaksi = substr($id_transaksi, -15);
        }
        $postfields = array();
        $postfields = array(
            "clientId" => $clientId,
            "accessToken" => $accessToken,
            "dpMallId" => $clientId,
            "words" => $words,
            "version" => env('VERSION_GENERATE_DOKU_QRIS'),
            "terminalId" => env('TERMINALID_DOKU_QRIS'),
            "amount" => $amount,
            "postalCode" => env('POSTALCODE_DOKU_QRIS'),
            "transactionId" => $id_transaksi,
            "feeType" => 1
        );
        $response = '';
        $res = array();
        $result = array();
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url_qris . '/generateQrAspi',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        $qrCode = $res->qrCode;
        $path = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl=' . $qrCode;
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $res,
            'qrCode' => $qrCode,
            'path' => $path,

        );
        Log::info($result);
        return $result;
    }

    static function history_cashout($cni_id = '', $id_member = 0, $param_fe = '')
    {
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_HISTORY_CASHOUT');
        $token = env('TOKEN_HISTORY_CASHOUT');
        $postfields = array(
            "nomorn" => $cni_id,
            "token" => $token
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $_res = json_encode($response);
        $res = json_decode($_res, true);

        $dt_log = array();
        $dt_history = array();
        $dt_log = array(
            "api_name" => "history_cashout",
            "param_from_fe" => $param_fe,
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "id_transaksi" => $id_member,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        $err_code = isset($res->Errorcode) ? $res->Errorcode : '';
        $dt = array(
            "err_code" => $err_code,
            "result" => isset($res->RESULT) ? $res->RESULT : '',
            "err_msg" => isset ($res->Message) ? $res->Message : '',
            "data" => $postfields

        );
        Log::info($res->RESULT);
        return $response;

        // return response($res)->header('Content-Type', "application/json");
    }

    static function upd_stok($data = array())
    {
        for ($i = 0; $i < count($data); $i++) {
            $id_product = 0;
            $id_product = $data[$i]['id_product'];
            DB::table('product')->where('id_product', $id_product)->update(['qty' => $data[$i]['qty']]);
        }
        return true;
    }

    static function send_order_cni($id_transaksi = 0, $text = "doku", $is_regmitra = 0, $is_upgrade = 0)
    {
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_SEND_ORDER_CNI');
        $token = env('TOKEN_SEND_ORDER_CNI');
        $where = array('transaksi.id_transaksi' => $id_transaksi);
        $_data = '';
        $tipetrans = 'C';
        if ($is_regmitra > 0) {
            $tipetrans = 'FPK';
            $_data = DB::table('transaksi')->select(
                'transaksi.*',
                'reg_mitra.nama as nama_member',
                'reg_mitra.email',
                'reg_mitra.type as type_member',
                'reg_mitra.phone as phone_member',
                'reg_mitra.upline_id as cni_id_ref'

            )
                ->where($where)
                ->leftJoin('reg_mitra', 'reg_mitra.id_transaksi', '=', 'transaksi.id_transaksi')->first();
        } else {
            $_data = DB::table('transaksi')->select(
                'transaksi.*',
                'members.nama as nama_member',
                'members.email',
                'members.type as type_member',
                'members.phone as phone_member',
                'members.cni_id_ref',
                'members.end_member'
            )
                ->where($where)
                ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
        }
        $cnt_details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->count();
        $list_item = null;
        $kd_produk_bonus = '';
        $id_product = 0;
        if ($cnt_details > 0) {
            $details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
            $list_item[] = array("totalline" => $cnt_details);
            foreach ($details as $d) {
                $id_product = (int)$d->id_product;
                if ((int)$d->is_bonus > 0) $kd_produk_bonus = !empty($d->kode_produk) ? $d->kode_produk : '';
                $list_item[] = array(
                    "productid" => !empty($d->kode_produk) ? $d->kode_produk : '-',
                    "productname" => $d->product_name,
                    "qty" => $d->jml,
                    "pv" => (int)$d->pv > 0 ? $d->pv : 0,
                    "pv_total" => $d->jml * $d->pv,
                    "rv" => (int)$d->rv > 0 ? $d->rv : 0,
                    "rv_total" => $d->jml * $d->rv,
                    "hargaretail" => $d->harga_konsumen,
                    "hargaretailtotal" => $d->jml * $d->harga_konsumen,
                    "hargamember" => $d->harga_member,
                    "hargamembertotal" => $d->jml * $d->harga_member,
                    "urlimage" => $d->img,
                );
            }
        }

        $jenispengirim = 'SC';
        $last_month_member = 0;
        $is_grace_periode = 0;
        $is_rne25 = 0;
        if ($id_product == 1) {
            $tgll = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($_data->end_member));
            $last_month_member_date = date('Y-m-d', strtotime("-1 months", strtotime($end_date)));
            if ($tgll >= $last_month_member_date && $tgll <= $end_date) $last_month_member = 1;
            if ($tgll > $end_date) {
                $date1 = date_create($end_date);
                $date2 = date_create($tgll);
                $diff = date_diff($date1, $date2);
                $is_grace_periode = 16 - (int)$diff->format("%R%a");
                $last_month_member = 0;
            }
            $jenispengirim = '';
            $is_rne25 = (int)$is_grace_periode > 0 || (int)$is_grace_periode > 0 ? 1 : 0;
        }

        $type_voucher = isset($_data) ? $_data->type_voucher : 0;
        $pot_voucher = $type_voucher != 3 ? $_data->pot_voucher : 0;
        $voucherproduk = $type_voucher == 3 ? $kd_produk_bonus : '';

        $ismemberid = isset($_data) && $_data->type_member == 1 ? 'Y' : 'N';
        $newmember = (isset($_data) && $is_upgrade == 1) || (int)$is_rne25 == 1 || $is_regmitra == 1 ? 'Y' : 'N';
        $akunid = isset($_data) ? $_data->id_member : 0;
        $emailakun = isset($_data) ? $_data->email : '';
        $nomorn = isset($_data) && $_data->type_member == 1 ? $_data->cni_id : '';
        $referensi = isset($_data->cni_id_ref) && $_data->cni_id_ref != '' ? $_data->cni_id_ref : '-';
        $konsumen = isset($_data) && $_data->type_member == 2 ? $_data->nama_member : '';
        $tipemember = '';
        if ($_data->type_member == 1) $tipemember = 'M';
        if ($_data->type_member == 3) $tipemember = 'R';
        $postfields = array(
            "token" => $token,
            "orderno" => $id_transaksi,
            "ismemberid" => $ismemberid,
            "newmember" => $newmember,
            "akunid" => $akunid,
            "emailakun" => $emailakun,
            "nomorn" => $nomorn,
            "referensi" => $referensi,
            "nama" => $_data->nama_member,
            "konsumen" => $konsumen,
            "tpv" => $_data->ttl_pv,
            "trv" => $_data->ttl_rv,
            "tglorder" => date('Y-m-d', strtotime($_data->created_at)),
            "thr" => $_data->ttl_hr,
            "thm" => $_data->ttl_hm,
            "totalharga" => $_data->ttl_price,
            "tdisc" => $_data->ttl_disc,
            "totalongkir" => $_data->ongkir,
            "totalpacking" => 0,
            "totalwallet" => (int)$_data->ewallet > 0 ? $_data->ewallet : 0,
            "totalbayar" => (int)$_data->nominal_doku > 0 ? $_data->nominal_doku : 0,
            "tipe_payment" => $_data->payment_name,
            "voucherid" => $_data->id_voucher,
            "vouchervalue" => $pot_voucher,
            "voucherproduk" => $voucherproduk,
            "tipemember" => $tipemember,
            "tipetrans" => $tipetrans,
            "detailline" => $list_item,
            "jenispengirim" => (int)$_data->tipe_pengiriman == 1 || (int)$_data->tipe_pengiriman == 2 ? 'DC' : $jenispengirim,
            "kodepengirim" => (int)$_data->tipe_pengiriman == 1 || (int)$_data->tipe_pengiriman == 2 ? $_data->iddc : $_data->id_wh,
            "namapenerima" => $_data->nama_penerima,
            "alamat_penerima" => $_data->alamat,
            "tlp_penerima" => $_data->phone_penerima,
            "kecamatan" => $_data->kec_name,
            "kota" => $_data->city_name,
            "provinsi_penerima" => $_data->provinsi_name,
            "jasa_pengirim" => $_data->logistic_name . '-' . $_data->service_code,
            "notelpakun" => $_data->phone_member,
            "ongkir_origin" => (int)$_data->ongkir_origin > 0 ? $_data->ongkir_origin : 0,
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $dt_log = array(
            "api_name" => $text . "/send_order_cni",
            "param_from_fe" => serialize(array('id_transaksi' => $id_transaksi)),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($response),
            "id_transaksi" => $id_transaksi,
            "created_at" => $tgl
        );

        DB::table('log_api')->insert($dt_log);
        $res = json_decode($response);
        $result = isset($res->result) ? $res->result : '';
        $gen_cni_id = 0;
        if (strtolower($result) == 'y') {
            $ismember = strtolower($res->ismember);
            //if($ismember == 'yes'){
            $gen_cni_id = isset($res->nomorn) ? $res->nomorn : 0;
            //}
        }
        return $gen_cni_id;
    }

    static function submit_voucher($custid = '', $memberid = '', $kodevoucher = '', $id_transaksi = 0)
    {
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_SUBMIT_VOUCHER_CNI');
        $token = env('TOKEN_SUBMIT_VOUCHER_CNI');
        $cnt_details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->count();
        $list_item = null;

        if ($cnt_details > 0) {
            $details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
            $list_item[] = array("totalline" => $cnt_details);
            $totalorder = 0;
            foreach ($details as $d) {
                $id_product = (int)$d->id_product;
                if ((int)$d->is_bonus > 0) $kd_produk_bonus = !empty($d->kode_produk) ? $d->kode_produk : '';
                $list_item[] = array(
                    "productid" => !empty($d->kode_produk) ? $d->kode_produk : '-',
                    "qty" => $d->jml,
                    "hargatotal" => (int)$d->ttl_harga > 0 ? $d->ttl_harga : 0,
                );
                $totalorder += (int)$d->ttl_harga > 0 ? $d->ttl_harga : 0;
            }
            $postfields = array(
                "token" => $token,
                "custid" => $custid,
                "memberid" => $memberid,
                "kodevoucher" => $kodevoucher,
                "totalorder" => $totalorder,
                "detailcart" => $list_item,
            );
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($postfields),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);

            $dt_log = array(
                "api_name" => "submit_voucher",
                "param_from_fe" => serialize(array('id_transaksi' => $id_transaksi)),
                "param_to_cni" => serialize($postfields),
                "endpoint" => $url,
                "responses" => serialize($response),
                "id_transaksi" => $id_transaksi,
                "created_at" => $tgl
            );
            DB::table('log_api')->insert($dt_log);
            $res = json_decode($response);
            //$result = isset($res->result) ? $res->result : '';
            $dt = array(
                "err_code" => '08',
                "result" => isset($res->result) ? strtolower($res->result) : '',
                "err_msg" => isset ($res->message) ? $res->message : '',
                "data" => $postfields

            );
            return $dt;
        }
    }

    static function unflagVoucher($memberid = '', $kodevoucher = '', $custid = '', $id_transaksi = 0)
    {
        $custid = empty($cni_id) ? $memberid : '';
        $memberid = $cni_id;
        $tgl = date('Y-m-d H:i:s');
        $url = env('URL_UNFLAG_VOUCHER_CNI');
        $token = env('TOKEN_UNFLAG_VOUCHER_CNI');
        $postfields = array(
            "token" => $token,
            "custid" => $custid,
            "memberid" => $memberid,
            "kodevoucher" => $kodevoucher,
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $dt_log = array(
            "api_name" => "submit_voucher",
            "param_from_fe" => serialize(array('id_transaksi' => $id_transaksi)),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($response),
            "id_transaksi" => $id_transaksi,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        $res = json_decode($response);
        $dt = array(
            "err_code" => '08',
            "result" => isset($res->result) ? strtolower($res->result) : '',
            "err_msg" => isset ($res->message) ? $res->message : '',
            "data" => $postfields
        );
        return $dt;
    }

    static function send_fcm($id_member = 0, $data_fcm = array(), $notif_fcm = array())
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $server_key = env('FCM_KEY');
        $fields = array();
        $result = array();
        $fields['data'] = $data_fcm;
        $fields['notification'] = $notif_fcm;
        $where = array('id_member' => $id_member);
        $fcm_token = DB::table('fcm_token')->select('token_fcm')->where($where)->groupBy('token_fcm')->get();
        $target = array();
        if (!empty($fcm_token)) {
            foreach ($fcm_token as $dt) {
                array_push($target, $dt->token_fcm);
            }
            $fields['registration_ids'] = $target;
            $headers = array(
                'Content-Type:application/json',
                'Authorization:key=' . $server_key
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            $result = curl_exec($ch);
            if ($result === FALSE) {
                die('FCM Send Error: ' . curl_error($ch));
            }
            curl_close($ch);
        }
        Log::info("push notif :" . $id_member);
        Log::info($result);
        return $result;
    }

    static function send_fcm_multiple($targets = array(), $data_fcm = array(), $notif_fcm = array())
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $server_key = env('FCM_KEY');
        $fields = array();
        $result = array();
        $fields['data'] = $data_fcm;
        $fields['notification'] = $notif_fcm;
        if (!empty($targets)) {
            $fields['registration_ids'] = $targets;
            $headers = array(
                'Content-Type:application/json',
                'Authorization:key=' . $server_key
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            $result = curl_exec($ch);
            if ($result === FALSE) {
                die('FCM Send Error: ' . curl_error($ch));
            }
            curl_close($ch);
        }
        Log::info($result);
        return $result;
    }

    static function notify_cart()
    {
        $tgl = date('Y-m-d H:i:s');
        $will_be_notified_on_date = date('Y-m-d H:i:s', strtotime($tgl . ' +1 day'));
        $sql = "select cart.id_member, token_fcm from cart left join fcm_token on fcm_token.id_member = cart.id_member where token_fcm != '' and will_be_notified_on_date::timestamp <= '" . $tgl . "' group by fcm_token.token_fcm, cart.id_member";
        $dt_will_be_notified_on_date = DB::select(DB::raw($sql));
        $whereIn = array();
        $target = array();
        if (count($dt_will_be_notified_on_date) > 0) {
            //update status ke expired payment
            foreach ($dt_will_be_notified_on_date as $te) {
                $whereIn[] = $te->id_member;
                if (!empty($dt->token_fcm)) array_push($target, $te->token_fcm);
            }
            $data_fcm = array(
                'id' => "cart",
                'title' => 'CNI',
                'message' => "Ada produk dikeranjang anda yang sudah siap dicheckout",
                'type' => "cart"
            );
            DB::table('cart')->whereIn('id_member', $whereIn)->update(array('will_be_notified_on_date' => $will_be_notified_on_date));
            send_fcm_multiple($target);
        }
        //return $sql;
    }

    function share_product($id_member = 0, $id_product = 0, $tipe = 1)
    { //1=>share product, 2=> transaksi
        $tgl = date('Y-m-d H:i:s');
        $where = array(
            'id_product' => $id_product,
            'id_member' => $id_member,
            'tipe' => $tipe,
        );
        $cnt_data = DB::table('share_product')->where($where)->count();
        if ((int)$cnt_data > 0) {
            $data_share = DB::table('share_product')->select('cnt_share')->where($where)->first();
            $cnt_share = (int)$data_share->cnt_share > 0 ? (int)$data_share->cnt_share + 1 : 1;
            DB::table('share_product')->where($where)->update(array('cnt_share' => $cnt_share, 'updated_at' => $tgl));
        } else {
            $where += array('cnt_share' => 1, 'updated_at' => $tgl);
            DB::table('share_product')->insert($where);
        }
    }

}
