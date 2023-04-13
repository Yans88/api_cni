<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SimpleXMLElement;

class DokuController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    //

    public function redirect_va(Request $request)
    {
        // $raw_notification = json_decode(file_get_contents('php://input'), true);
        // $input  = $request->all();
        // Log::info('raw_notification');
        // Log::info($raw_notification);
        // Log::info('inputan');
        // Log::info($input);
        $tgl = date('Y-m-d H:i:s');
        $PAYMENTCODE = $request->PAYMENTCODE;
        $MALLID = $request->MALLID;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = array('transaksi.status' => 0, 'key_payment' => $PAYMENTCODE, 'payment' => 2);
        $data = array();
        $data = DB::table('transaksi')->select(
            'transaksi.*',
            'members.nama as nama_member',
            'members.email'
        )
            ->where($where)
            ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
            ->orderByRaw($sort_column)->first();
        $id_transaksi = 0;
        $expired_payment = date("Y-m-d H:i", strtotime($data->expired_payment));

        $xml = new SimpleXMLElement('<INQUIRY_RESPONSE/>');
        $xml_item = $xml->addChild('INQUIRY_RESPONSE');
        if ($expired_payment <= $tgl) {
            $xml_item->addChild('RESPONSECODE', '08');
            $xml_item->addChild('MESSAGE', 'Transaction expired');
            $res = $xml_item->asXML();
            return response($res);
            return false;
        }
        if (!empty($data) && (int)$data->id_transaksi > 0) {
            $id_transaksi = $data->id_transaksi;
            $ttl_price = $data->nominal_doku;
            $words = $ttl_price . '' . $MALLID . 'NRd509eQng1F' . '' . $id_transaksi;
            $charactersLength = strlen($words);
            $randomString = '';
            for ($i = 0; $i < 20; $i++) {
                $randomString .= $words[rand(0, $charactersLength - 1)];
            }

            $basket = 'Paket ' . $data->nama_member . ' No. Order #' . $id_transaksi . ',' . number_format($ttl_price, 2, ".", "") . ',1,' . number_format($ttl_price, 2, ".", "");
            // $details =  DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
            // $i = 1;
            // $jml_data = count($details);
            // if (count($details) > 0) {
            //     foreach ($details as $row) {
            //         if ($jml_data == $i) {
            //             $basket .= $row->product_name . ',' . number_format($row->harga, 2, ".", "") . ',' . $row->jml . ',' . number_format($row->ttl_harga, 2, ".", "");
            //         } else {
            //             $basket .= $row->product_name . ',' . number_format($row->harga, 2, ".", "") . ',' . $row->jml . ',' . number_format($row->ttl_harga, 2, ".", "") . ';';
            //         }
            //         $i++;
            //     }
            // }
        }

        $tgl = date('YmdHis');

        if ((int)$id_transaksi > 0) {
            $xml_item->addChild('PAYMENTCODE', $PAYMENTCODE);
            $xml_item->addChild('AMOUNT', $ttl_price . '.00');
            $xml_item->addChild('PURCHASEAMOUNT', $ttl_price . '.00');
            $xml_item->addChild('TRANSIDMERCHANT', $id_transaksi);
            $xml_item->addChild('WORDS', sha1($words));
            $xml_item->addChild('REQUESTDATETIME', $tgl);
            $xml_item->addChild('CURRENCY', 360);
            $xml_item->addChild('PURCHASECURRENCY', 360);
            $xml_item->addChild('SESSIONID', $data->session_id);
            $xml_item->addChild('NAME', $data->nama_member);
            $xml_item->addChild('EMAIL', $data->email);
            $xml_item->addChild('BASKET', $basket);
        } else {
            $xml_item->addChild('MESSAGE', 'Transaction no found');
        }
        $res = $xml_item->asXML();
        // $result = array(
        //     'err_code'      => '00',
        //     'err_msg'          => 'ok',
        //     'data_xml'      => $xml_item->asXML(),

        // );
        return response($res);
    }

    function notify(Request $request)
    {
        // $raw_notification = json_decode(file_get_contents('php://input'), true);
        // Log::info('raw_notification');
        // Log::info($raw_notification);
        $tgl = date('Y-m-d H:i:s');
        $input = $request->all();
        Log::info('inputan notify doku');
        Log::info($input);
        $id_transaksi = $request->TRANSIDMERCHANT;
        Log::info($id_transaksi);
        $status = $request->RESULTMSG;
        $status_code = $request->RESPONSECODE;
        $bank_issuer = $request->BANK;
        $brand_cc = $request->BRAND;
        $payment_date = !empty($request->PAYMENTDATETIME) ? date('Y-m-d H:i:s', strtotime($request->PAYMENTDATETIME)) : '';
        $where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
        $count = DB::table('transaksi')->where($where)->count();
        $is_upgrade = 0;
        if ((int)$count > 0) {
            $data_s = serialize($input);
            if ((int)$status_code == 0) {
                $dt_trans = '';
                $dt_members = '';
                $dt_trans = DB::table('transaksi')->where($where)->first();
                $ewallet = isset($dt_trans->ewallet) && (int)$dt_trans->ewallet > 0 ? (int)$dt_trans->ewallet : 0;
                $ttl_price = isset($dt_trans->ttl_price) && (int)$dt_trans->ttl_price > 0 ? (int)$dt_trans->ttl_price : 0;

                $id_member = $dt_trans->id_member;
                $dt_members = DB::table('members')->where(array("id_member" => $id_member))->first();
                $tipe_member = (int)$dt_members->type;
                $is_rne25 = (int)$dt_trans->is_rne25;
                $is_regmitra = (int)$dt_trans->is_regmitra;
                $is_upgrade = (int)$dt_trans->is_upgrade;

                if ($ewallet > 0) {
                    $sub_ttl = $dt_trans->ttl_price;
                    $cni_id = isset($dt_members->cni_id) && (int)$dt_members->cni_id > 0 ? (int)$dt_members->cni_id : 0;
                    //Helper::trans_ewallet("PAID_EWALLET", $cni_id, $sub_ttl, $ewallet, $id_transaksi, $input, "doku/notify", 1, '', 0, $id_member);
                }
                $gen_cni_id = Helper::send_order_cni($id_transaksi, 'doku');
                if ($is_upgrade == 1 && $is_regmitra == 0) {
                    $start_member = date('Y-m-d');
                    $_end_member = date('Y-m-d', strtotime('+1 years'));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'type' => 1,
                    );
                    if ($tipe_member == 2) $upd_member += array('end_member' => $end_member);
                    if ((int)$is_rne25 == 0) {
                        $upd_member += array('start_member' => $start_member, 'cni_id' => $gen_cni_id);
                    }
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if ((int)$is_rne25 == 1 || (int)$is_rne25 == 2) {
                    $is_upgrade = 1;
                    $end_date = date('Y-m-d', strtotime($dt_members->end_member));
                    $_end_member = date('Y-m-d', strtotime("+1 years", strtotime($end_date)));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'end_member' => $end_member,
                        'type' => 1,
                    );
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if ((int)$is_upgrade > 0 && $is_regmitra == 0) {
                    $chk_nmorn = DB::table('history_nomorn')->where(array('id_member' => $id_member, 'nomorn' => $gen_cni_id))->count();
                    if ((int)$chk_nmorn <= 0) {
                        $histori_nomorn = array(
                            'id_member' => $id_member,
                            'nomorn' => $gen_cni_id,
                            'created_at' => $tgl,
                        );
                        DB::table('history_nomorn')->insertGetId($histori_nomorn, "id");
                    }
                }
                if ($is_regmitra == 1) {
                    $id_reg_mitra = $id_member;
                    $cni_id = $gen_cni_id;
                    $where = array('reg_mitra.id_reg_mitra' => $id_reg_mitra);
                    $_data = DB::table('reg_mitra')->where($where)->first();
                    $id_transaksi = $_data->id_transaksi;
                    $id_address = $_data->id_address;
                    $type = $_data->type;
                    $dt_members = array();
                    $dt_members = array(
                        'cni_id' => $cni_id,
                        'email' => $_data->email,
                        'nama' => $_data->nama,
                        'phone' => $_data->phone,
                        'cni_id_ref' => $_data->upline_id,
                        'type' => $_data->type,
                        'status' => 1,
                        'verify_phone' => 1,
                        'verify_email' => 1,
                        'pass' => Crypt::encryptString('123456'),
                        'created_at' => $tgl,
                        'updated_at' => $tgl,
                    );
                    if ($type == 1 or $type == 3) {
                        $start_member = date('Y-m-d');
                        $_end_member = date('Y-m-d', strtotime('+1 years'));
                        $end_member = date("Y-m-t", strtotime($_end_member));
                        $dt_members += array(
                            'end_member' => $end_member
                        );
                    }
                    $id_member = DB::table('members')->insertGetId($dt_members, "id_member");
                    $where = array("id_member" => $id_reg_mitra, "id_transaksi" => $id_transaksi);
                    $where_address = array("id_address" => $id_address);
                    $dt_upd = array("id_member" => $id_member);
                    DB::table('transaksi')->where($where)->update($dt_upd);
                    DB::table('address_member')->where($where_address)->update($dt_upd);
                    DB::table('reg_mitra')->where(array("id_reg_mitra" => $id_reg_mitra, "id_transaksi" => $id_transaksi))->update(array('status' => 3, 'cni_id' => $cni_id, 'updated_at' => $tgl));
                    $histori_nomorn = array(
                        'id_member' => $id_member,
                        'nomorn' => $gen_cni_id,
                        'created_at' => $tgl,
                    );
                    DB::table('history_nomorn')->insertGetId($histori_nomorn, "id");
                }
                $data = array();
                $data = array(
                    'payment_date' => $payment_date,
                    'status' => (int)$is_rne25 > 0 ? 5 : 1,
                    'bank_issuer' => $bank_issuer,
                    'brand_cc' => $brand_cc,
                    'expired_payment' => null,
                    'log_payment' => $data_s,
                    'is_upgrade' => $is_upgrade
                );
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($data);

                DB::connection()->enableQueryLog();
                $notif_fcm = array(
                    'body' => 'Pembayaran anda sudah kami terima dan pesanan anda akan kami proses segera',
                    'title' => 'CNI',
                    'badge' => '1',
                    'sound' => 'Default'
                );
                $dt_insert_notif = array();
                $dt_insert_notif = array(
                    'id' => $id_transaksi,
                    'id_member' => $id_member,
                    'content' => 'Pembayaran anda sudah kami terima dan pesanan anda akan kami proses segera',
                    'type' => 1,
                    'created_at' => $tgl,
                    'created_by' => $id_member
                );
                $id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
                $data_fcm = array(
                    'id_notif' => $id_notif,
                    'id' => $id_transaksi,
                    'title' => 'CNI',
                    'status' => (int)$is_rne25 > 0 ? 5 : 1,
                    'message' => 'Pembayaran anda sudah kami terima dan pesanan anda akan kami proses segera',
                    'type' => '1'
                );
                Log::info(DB::getQueryLog());
                Helper::send_fcm($id_member, $data_fcm, $notif_fcm);

                echo 'Transaction #' . $id_transaksi . ': ' . $status;
                echo '<script>console.log(\'RECEIVEOK\');</script>';
            } else {
                echo 'Transaction #' . $id_transaksi . ' Failed : ' . $status;
                echo '<script>console.log(\'RECEIVEFALSE\');</script>';
            }
        } else {
            echo 'Transaction #' . $id_transaksi . ' Not Found : ' . $status;
            echo '<script>console.log(\'RECEIVEFALSE\');</script>';
        }
        echo "CONTINUE";
        //return response($raw_notification);
    }

    function notify_qris(Request $request)
    {
        // $raw_notification = json_decode(file_get_contents('php://input'), true);
        // Log::info('raw_notification');
        // Log::info($raw_notification);
        $tgl = date('Y-m-d H:i:s');
        $input = $request->all();
        // Log::info('inputan notify QRIS');
        // Log::info($input);
        $_id_transaksi = $request->TRANSACTIONID;
        $status = $request->TXNSTATUS;
        $payment_date = !empty($request->TXNDATE) ? date('Y-m-d H:i:s', strtotime($request->TXNDATE)) : '';

        $base64 = base64_decode($_id_transaksi);
        $vowels = array("n", "c", "i", "C", "I", "N");
        $id_transaksi = str_replace($vowels, "", $base64);
        // Log::info($id_transaksi);
        $where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
        // Log::info($where);
        $is_upgrade = 0;
        $dt_trans = '';
        $dt_members = '';
        $count = DB::table('transaksi')->where($where)->count();
        if ((int)$count > 0) {
            $data_s = serialize($input);
            if ((int)$status == "S") {
                $dt_trans = DB::table('transaksi')->where($where)->first();
                $ewallet = isset($dt_trans->ewallet) && (int)$dt_trans->ewallet > 0 ? (int)$dt_trans->ewallet : 0;
                $ttl_price = isset($dt_trans->ttl_price) && (int)$dt_trans->ttl_price > 0 ? $dt_trans->ttl_price : 0;

                $id_member = $dt_trans->id_member;
                $dt_members = DB::table('members')->where(array("id_member" => $id_member))->first();
                $tipe_member = (int)$dt_members->type;
                $is_rne25 = (int)$dt_trans->is_rne25;
                $is_regmitra = (int)$dt_trans->is_regmitra;
                $is_upgrade = (int)$dt_trans->is_upgrade;
                if ($ewallet > 0) {
                    $sub_ttl = $dt_trans->ttl_price;
                    $cni_id = isset($dt_members->cni_id) && (int)$dt_members->cni_id > 0 ? (int)$dt_members->cni_id : 0;
                    Helper::trans_ewallet("PAID_EWALLET", $cni_id, $sub_ttl, $ewallet, $id_transaksi, $input, "doku/notify", 1, '', 0, $id_member);
                }
                $gen_cni_id = Helper::send_order_cni($id_transaksi, 'doku', $is_regmitra);
                if (($ttl_price >= 1000000 && ($tipe_member == 2 or $tipe_member == 3)) && $is_upgrade == 1) {
                    $start_member = date('Y-m-d');
                    $_end_member = date('Y-m-d', strtotime('+1 years'));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'type' => 1,
                    );
                    if ($tipe_member == 2) $upd_member += array('end_member' => $end_member);
                    if ((int)$is_rne25 == 0) {
                        $upd_member += array('start_member' => $start_member, 'cni_id' => $gen_cni_id);
                    }
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if (($ttl_price >= 500000 && $ttl_price < 1000000) && $is_upgrade == 1 && $tipe_member == 2 && $is_regmitra == 0) {
                    $is_upgrade = 1;
                    $start_member = date('Y-m-d');
                    $_end_member = date('Y-m-d', strtotime('+1 years'));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'end_member' => $end_member,
                        'type' => 3,
                    );
                    if ((int)$is_rne25 == 0) {
                        $upd_member += array('start_member' => $start_member, 'cni_id' => $gen_cni_id);
                    }
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if ((int)$is_rne25 == 1 || (int)$is_rne25 == 2) {
                    $is_upgrade = 1;
                    $end_date = date('Y-m-d', strtotime($dt_members->end_member));
                    $_end_member = date('Y-m-d', strtotime("+1 years", strtotime($end_date)));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'end_member' => $end_member,
                        'type' => 1,
                    );
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if ((int)$is_upgrade > 0 && $is_regmitra == 0) {
                    $chk_nmorn = DB::table('history_nomorn')->where(array('id_member' => $id_member, 'nomorn' => $gen_cni_id))->count();
                    if ((int)$chk_nmorn <= 0) {
                        $histori_nomorn = array(
                            'id_member' => $id_member,
                            'nomorn' => $gen_cni_id,
                            'created_at' => $tgl,
                        );
                        DB::table('history_nomorn')->insertGetId($histori_nomorn, "id");
                    }
                }
                if ($is_regmitra == 1) {
                    $id_reg_mitra = $id_member;
                    $cni_id = $gen_cni_id;
                    $where = array('reg_mitra.id_reg_mitra' => $id_reg_mitra);
                    $_data = DB::table('reg_mitra')->where($where)->first();
                    $id_transaksi = $_data->id_transaksi;
                    $id_address = $_data->id_address;
                    $type = $_data->type;
                    $dt_members = array();
                    $dt_members = array(
                        'cni_id' => $cni_id,
                        'email' => $_data->email,
                        'nama' => $_data->nama,
                        'phone' => $_data->phone,
                        'cni_id_ref' => $_data->upline_id,
                        'type' => $_data->type,
                        'status' => 1,
                        'verify_phone' => 1,
                        'verify_email' => 1,
                        'pass' => Crypt::encryptString('123456'),
                        'created_at' => $tgl,
                        'updated_at' => $tgl,
                    );
                    if ($type == 1 or $type == 3) {
                        $start_member = date('Y-m-d');
                        $_end_member = date('Y-m-d', strtotime('+1 years'));
                        $end_member = date("Y-m-t", strtotime($_end_member));
                        $dt_members += array(
                            'end_member' => $end_member
                        );
                    }
                    $id_member = DB::table('members')->insertGetId($dt_members, "id_member");
                    $where = array("id_member" => $id_reg_mitra, "id_transaksi" => $id_transaksi);
                    $where_address = array("id_address" => $id_address);
                    $dt_upd = array("id_member" => $id_member);
                    DB::table('transaksi')->where($where)->update($dt_upd);
                    DB::table('address_member')->where($where_address)->update($dt_upd);
                    DB::table('reg_mitra')->where(array("id_reg_mitra" => $id_reg_mitra, "id_transaksi" => $id_transaksi))->update(array('status' => 3, 'cni_id' => $cni_id, 'updated_at' => $tgl));
                    $histori_nomorn = array(
                        'id_member' => $id_member,
                        'nomorn' => $gen_cni_id,
                        'created_at' => $tgl,
                    );
                    DB::table('history_nomorn')->insertGetId($histori_nomorn, "id");
                    $dt_members = DB::table('members')->where(array("id_member" => $id_member))->first();
                }
                $data = array();
                $data = array(
                    'payment_date' => $payment_date,
                    'status' => (int)$is_rne25 > 0 ? 5 : 1,
                    'expired_payment' => null,
                    'log_payment' => $data_s,
                    'is_upgrade' => $is_upgrade
                );
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($data);
                $payment_channel = isset($dt_trans) ? (int)$dt_trans->payment_channel : 0;
                $payment = isset($dt_trans) ? (int)$dt_trans->payment : 0;

                $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/default_payment.png';
                if ($payment == 1) $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/cc.png';
                if ($payment == 3) $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/wallet.png';
                if ($payment == 2) {
                    if ($payment_channel == 29) {
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/29.png';
                    }
                    if ($payment_channel == 32) {
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/32.jpg';
                    }
                    if ($payment_channel == 33) {
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/33.jpg';
                    }
                    if ($payment_channel == 34) {
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/34.png';
                    }
                    if ($payment_channel == 36) {
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/36.png';
                    }
                }
                $setting = DB::table('setting')->get()->toArray();
                $out = array();
                if (!empty($setting)) {
                    foreach ($setting as $val) {
                        $out[$val->setting_key] = $val->setting_val;
                    }
                }
                $data_item = DB::table('transaksi_detail')->select('product_name', 'img', 'harga', 'jml')->where('id_trans', $id_transaksi)->get();
                $nama = isset($dt_members) ? $dt_members->nama : '';
                $email = isset($dt_members) ? $dt_members->email : '';
                $content_email_payment_complete = $out['content_email_payment_complete'];
                $content_email_payment_complete = str_replace('[#nama#]', $nama, $content_email_payment_complete);
                $content_email_payment_complete = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_payment_complete);
                $content_email_payment_complete = str_replace('[#ttl_bayar#]', number_format($ttl_price), $content_email_payment_complete);
                $content_email_payment_complete = str_replace('https://mcni.cni.co.id/api_cni/uploads/default_payment.png', $metode_pembayaran, $content_email_payment_complete);
                $html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
                        <tbody>';
                foreach ($data_item as $di) {
                    $html .= '<tr>';
                    $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
					<img src="' . $di->img . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
                    $html .= '<td valign="top" style="padding:0 0 16px 16px">
								<div style="margin:0 0 4px;line-height:16px">' . $di->product_name . '</div>
								<p style="font-weight:bold;margin:4px 0 0">' . number_format($di->jml) . ' x
									<span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($di->harga) . '</span>
								</p>
							</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';

                $data_email = array();
                $content_email_payment_complete = str_replace('[#detail_pesanan#]', $html, $content_email_payment_complete);
                Log::info($content_email_payment_complete);
                $data_email['nama'] = $nama;
                $data_email['email'] = $email;
                $data_email['subject'] = 'Pembayaran mcni No Order ' . $id_transaksi . ' Telah diterima';
                $data_email['content_email'] = $content_email_payment_complete;
                $mail = Mail::send([], ['users' => $data_email], function ($message) use ($data_email) {
                    $message->to($data_email['email'], $data_email['nama'])->subject($data_email['subject'])->setBody($data_email['content_email'], 'text/html');
                });
                Log::info(serialize($mail));
                echo 'Transaction #' . $id_transaksi . ': ' . $status;
                echo '<script>console.log(\'RECEIVEOK\');</script>';
            } else {
                echo 'Transaction #' . $id_transaksi . ' Failed : ' . $status;
                echo '<script>console.log(\'RECEIVEFALSE\');</script>';
            }
        } else {
            echo 'Transaction #' . $id_transaksi . ' Not Found : ' . $status;
            echo '<script>console.log(\'RECEIVEFALSE\');</script>';
        }
        echo "CONTINUE";
        //return response($raw_notification);
    }

    function generate_words(Request $request)
    {
        $clientId = $request->clientId;
        $clientSecret = $request->clientSecret;
        $Sharedkey = $request->Sharedkey;
        $systrace = $request->systrace;
        $format_result = $request->format_result;
        $generate = (int)$request->generate;
        $words_ori = $clientId . '' . $Sharedkey . '' . $systrace;
        if ($generate > 0) $words_ori = $clientId . '' . $systrace . '' . $clientId . '' . $Sharedkey;
        $words = '';
        $format = '';
        if ($format_result == 2) {
            $format = "HSMAC SHA1 => hash_hmac('sha1', $words_ori,'b7cfdcfc247f11fcffde5b2b784f5381')";
            $words = hash_hmac('sha1', $words_ori, 'b7cfdcfc247f11fcffde5b2b784f5381');
        }
        if ($format_result == 1) {
            $format = "SHA1 => sha1($words_ori)";
            $words = sha1($words_ori);
        }
        $result = array();
        $result = array(
            'clientId' => $clientId,
            'Sharedkey' => $Sharedkey,
            'systrace' => $systrace,
            'words_ori' => $words_ori,
            'format' => $format,
            'words' => $words
        );
        return response($result);
    }

    function mitra_to_member(Request $request)
    {
        $id_reg_mitra = $request->id_reg_mitra;
        $cni_id = $request->cni_id;
        $tgl = date('Y-m-d H:i:s');
        $where = array('reg_mitra.id_reg_mitra' => $id_reg_mitra);
        $_data = DB::table('reg_mitra')->where($where)->first();
        $id_transaksi = $_data->id_transaksi;
        $id_address = $_data->id_address;
        $type = $_data->type;
        $dt_members = array();
        $dt_members = array(
            'cni_id' => $cni_id,
            'email' => $_data->email,
            'phone' => $_data->phone,
            'cni_id_ref' => '',
            'type' => $_data->type,
            'status' => 1,
            'verify_phone' => 1,
            'verify_email' => 1,
            'pass' => Crypt::encryptString('123456'),
            'created_at' => $tgl,
            'updated_at' => $tgl,
        );
        if ($type == 1 or $type == 2) {
            $start_member = date('Y-m-d');
            $_end_member = date('Y-m-d', strtotime('+1 years'));
            $end_member = date("Y-m-t", strtotime($_end_member));
            $dt_members += array(
                'end_member' => $end_member
            );
        }
        $id_member = DB::table('members')->insertGetId($dt_members, "id_member");
        $where = array("id_member" => $id_reg_mitra, "id_transaksi" => $id_transaksi);
        $where_address = array("id_address" => $id_address);
        $dt_upd = array("id_member" => $id_member);
        DB::table('transaksi')->where($where)->update($dt_upd);
        DB::table('address_member')->where($where_address)->update($dt_upd);
        DB::table('reg_mitra')->where(array("id_reg_mitra" => $id_reg_mitra, "id_transaksi" => $id_transaksi))->update(array('status' => 3, 'cni_id' => $cni_id, 'updated_at' => $tgl));
    }

    function notify_jokul(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $input = $request->all();
        Log::info('inputan notify jokul');
        Log::info($input);
        $order = $input['order'];
        $transaction = $input['transaction'];
        $id_transaksi = $order['invoice_number'];
        $status = strtolower($transaction['status']);
        $where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
        $count = DB::table('transaksi')->where($where)->count();
        $is_upgrade = 0;
        $dt_trans = '';
        $dt_members = '';
        DB::connection()->enableQueryLog();
        if ((int)$count > 0) {
            $data_s = serialize($input);
            if ($status == "success") {

                $dt_trans = DB::table('transaksi')->where($where)->first();
                $ewallet = isset($dt_trans->ewallet) && (int)$dt_trans->ewallet > 0 ? (int)$dt_trans->ewallet : 0;
                $ttl_price = isset($dt_trans->ttl_price) && (int)$dt_trans->ttl_price > 0 ? (int)$dt_trans->ttl_price : 0;

                $id_member = $dt_trans->id_member;
                $is_rne25 = (int)$dt_trans->is_rne25;
                $is_regmitra = (int)$dt_trans->is_regmitra;
                $is_upgrade = (int)$dt_trans->is_upgrade;
                if ($is_regmitra > 0) {
                    $dt_members = DB::table('reg_mitra')->where(array("id_transaksi" => $id_transaksi))->first();
                } else {
                    $dt_members = DB::table('members')->where(array("id_member" => $id_member))->first();
                }
                $tipe_member = (int)$dt_members->type;

                if ($ewallet > 0) {
                    $sub_ttl = $dt_trans->ttl_price;
                    $cni_id = isset($dt_members->cni_id) && (int)$dt_members->cni_id > 0 ? (int)$dt_members->cni_id : 0;
                    Helper::trans_ewallet("PAID_EWALLET", $cni_id, $sub_ttl, $ewallet, $id_transaksi, $input, "doku/notify", 1, '', 0, $id_member);
                }
                $gen_cni_id = Helper::send_order_cni($id_transaksi, 'doku', $is_regmitra, $is_upgrade);
                if ($ttl_price >= 1000000 && $is_regmitra == 0 && ($tipe_member == 2 || $tipe_member == 3) && $is_upgrade == 1) {

                    $start_member = date('Y-m-d');
                    $_end_member = date('Y-m-d', strtotime('+1 years'));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'type' => 1,
                    );
                    if ($tipe_member == 2) $upd_member += array('end_member' => $end_member);
                    if ((int)$is_rne25 == 0) {
                        $upd_member += array('start_member' => $start_member, 'cni_id' => $gen_cni_id);
                    }
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                    //Log::info(DB::getQueryLog());
                }
                if (($ttl_price >= 500000 && $ttl_price < 1000000) && $tipe_member == 2 && $is_regmitra == 0 && $is_upgrade == 1) {
                    $is_upgrade = 1;
                    $start_member = date('Y-m-d');
                    $_end_member = date('Y-m-d', strtotime('+1 years'));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'end_member' => $end_member,
                        'type' => 3,
                    );
                    if ((int)$is_rne25 == 0) {
                        $upd_member += array('start_member' => $start_member, 'cni_id' => $gen_cni_id);
                    }
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if ((int)$is_rne25 == 1 || (int)$is_rne25 == 2) {
                    $is_upgrade = 1;
                    $end_date = date('Y-m-d', strtotime($dt_members->end_member));
                    $_end_member = date('Y-m-d', strtotime("+1 years", strtotime($end_date)));
                    $end_member = date("Y-m-t", strtotime($_end_member));
                    $upd_member = array();
                    $upd_member = array(
                        'end_member' => $end_member,
                        'type' => 1,
                    );
                    DB::table('members')->where('id_member', $id_member)->update($upd_member);
                }
                if ((int)$is_upgrade > 0 && $is_regmitra == 0) {
                    $chk_nmorn = DB::table('history_nomorn')->where(array('id_member' => $id_member, 'nomorn' => $gen_cni_id))->count();
                    if ((int)$chk_nmorn <= 0) {
                        $histori_nomorn = array(
                            'id_member' => $id_member,
                            'nomorn' => $gen_cni_id,
                            'created_at' => $tgl,
                        );
                        DB::table('history_nomorn')->insertGetId($histori_nomorn, "id");
                    }
                }
                if ($is_regmitra == 1) {

                    $id_reg_mitra = $id_member;
                    $cni_id = $gen_cni_id;
                    // $where = array('reg_mitra.id_reg_mitra' => $id_reg_mitra);
                    $_data = $dt_members;
                    $id_transaksi = $_data->id_transaksi;
                    $id_address = $_data->id_address;
                    $type = $_data->type;
                    $dt_members = array();
                    $dt_members = array(
                        'cni_id' => $cni_id,
                        'email' => $_data->email,
                        'nama' => $_data->nama,
                        'phone' => $_data->phone,
                        'cni_id_ref' => $_data->upline_id,
                        'type' => $_data->type,
                        'status' => 1,
                        'verify_phone' => 1,
                        'verify_email' => 1,
                        'pass' => Crypt::encryptString('123456'),
                        'created_at' => $tgl,
                        'updated_at' => $tgl,
                    );
                    Log::info(serialize($dt_members));
                    if ($type == 1 or $type == 3) {
                        $start_member = date('Y-m-d');
                        $_end_member = date('Y-m-d', strtotime('+1 years'));
                        $end_member = date("Y-m-t", strtotime($_end_member));
                        $dt_members += array(
                            'end_member' => $end_member
                        );
                    }
                    Log::info(serialize($dt_members));
                    $id_member = DB::table('members')->insertGetId($dt_members, "id_member");
                    $where = array("id_transaksi" => $id_transaksi);
                    $where_address = array("id_address" => $id_address);
                    $dt_upd = array("id_member" => $id_member);
                    DB::table('transaksi')->where($where)->update($dt_upd);
                    Log::info(DB::getQueryLog());
                    DB::table('address_member')->where($where_address)->update($dt_upd);
                    Log::info(DB::getQueryLog());
                    DB::table('reg_mitra')->where(array("id_transaksi" => $id_transaksi))->update(array('status' => 3, 'cni_id' => $cni_id, 'updated_at' => $tgl));
                    Log::info(DB::getQueryLog());
                    $histori_nomorn = array(
                        'id_member' => $id_member,
                        'nomorn' => $cni_id,
                        'created_at' => $tgl,
                    );
                    DB::table('history_nomorn')->insertGetId($histori_nomorn, "id");
                    Log::info(DB::getQueryLog());
                }
                $data = array();
                $data = array(
                    'payment_date' => $tgl,
                    'status' => (int)$is_rne25 > 0 ? 5 : 1,
                    // 'bank_issuer'  	=> $bank_issuer,
                    // 'brand_cc'   	=> $brand_cc,
                    'expired_payment' => null,
                    'log_payment' => $data_s,
                    'is_upgrade' => $is_upgrade
                );
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($data);
                $payment_channel = isset($dt_trans) ? (int)$dt_trans->payment_channel : 0;
                $payment = isset($dt_trans) ? (int)$dt_trans->payment : 0;
                $metode_pembayaran = 'http://202.158.64.238/api_cni/uploads/29.png';
                if ($payment_channel == 29) {
                    $metode_pembayaran = 'http://202.158.64.238/api_cni/uploads/29.png';
                }
                if ($payment_channel == 32) {
                    $metode_pembayaran = 'http://202.158.64.238/api_cni/uploads/32.jpg';
                }
                if ($payment_channel == 33) {
                    $metode_pembayaran = 'http://202.158.64.238/api_cni/uploads/33.jpg';
                }
                if ($payment_channel == 34) {
                    $metode_pembayaran = 'http://202.158.64.238/api_cni/uploads/34.png';
                }
                if ($payment_channel == 36) {
                    $metode_pembayaran = 'http://202.158.64.238/api_cni/uploads/36.png';
                }
                $setting = DB::table('setting')->get()->toArray();
                $out = array();
                if (!empty($setting)) {
                    foreach ($setting as $val) {
                        $out[$val->setting_key] = $val->setting_val;
                    }
                }
                $data_item = DB::table('transaksi_detail')->select('product_name', 'img', 'harga', 'jml')->where('id_trans', $id_transaksi)->get();
                $nama = isset($dt_members) ? $dt_members->nama : '';
                $email = isset($dt_members) ? $dt_members->email : '';
                $content_email_payment_complete = $out['content_email_payment_complete'];
                $content_email_payment_complete = str_replace('[#nama#]', $nama, $content_email_payment_complete);
                $content_email_payment_complete = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_payment_complete);
                $content_email_payment_complete = str_replace('[#ttl_bayar#]', number_format($ttl_price), $content_email_payment_complete);
                $content_email_payment_complete = str_replace('http://202.158.64.238/api_cni/uploads/29.png', $metode_pembayaran, $content_email_payment_complete);
                $html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
                        <tbody>';
                foreach ($data_item as $di) {
                    $html .= '<tr>';
                    $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
					<img src="' . $di->img . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
                    $html .= '<td valign="top" style="padding:0 0 16px 16px">
								<div style="margin:0 0 4px;line-height:16px">' . $di->product_name . '</div>
								<p style="font-weight:bold;margin:4px 0 0">' . number_format($di->jml) . ' x
									<span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($di->harga) . '</span>
								</p>
							</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';

                $data_email = array();
                $content_email_payment_complete = str_replace('[#detail_pesanan#]', $html, $content_email_payment_complete);
                Log::info($content_email_payment_complete);
                $data_email['nama'] = $nama;
                $data_email['email'] = $email;
                $data_email['subject'] = 'Pembayaran mcni No Order ' . $id_transaksi . ' Telah diterima';
                $data_email['content_email'] = $content_email_payment_complete;
                $mail = Mail::send([], ['users' => $data_email], function ($message) use ($data_email) {
                    $message->to($data_email['email'], $data_email['nama'])->subject($data_email['subject'])->setBody($data_email['content_email'], 'text/html');
                });
                Log::info(serialize($data_email));
                Log::info(serialize($mail));
                echo 'Transaction #' . $id_transaksi . ': ' . $status;
                echo '<script>console.log(\'RECEIVEOK\');</script>';
            } else {
                echo 'Transaction #' . $id_transaksi . ' Failed : ' . $status;
                echo '<script>console.log(\'RECEIVEFALSE\');</script>';
            }
        } else {
            echo 'Transaction #' . $id_transaksi . ' Not Found : ' . $status;
            echo '<script>console.log(\'RECEIVEFALSE\');</script>';
        }
        echo "CONTINUE";
    }

    // function payment_cc($id_transaksi=0){
    // $id_transaksi = isset($request->id_transaksi) ? (int)$request->id_transaksi : 0;
    // $where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
    // $data = DB::table('transaksi')->select(
    // 'transaksi.*',
    // 'members.nama as nama_member',
    // 'members.email'
    // )
    // ->where($where)
    // ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();

    // $id_transaksi = $data->id_transaksi;
    // $ttl_price = $data->nominal_doku;
    // $MALLID = env('MALLID_CC');
    // $shared_key = env('SHAREDKEY');
    // $words = $ttl_price . '' . $MALLID . ''.$shared_key. '' . $id_transaksi;
    // $basket = 'Paket ' . $data->nama_member . ' No. Order #' . $id_transaksi . ',' . number_format($ttl_price, 2, ".", "") . ',1,' . number_format($ttl_price, 2, ".", "");
    // $res = array(
    // 'mall_id'		=> $MALLID,
    // 'nama_member'	=> $data->nama_member,
    // 'email'			=> $data->email,
    // 'basket'		=> $data->basket,
    // 'ttl_price'		=> $data->ttl_price,
    // 'words'			=> sha1($words),
    // 'tgl'			=> date('YmdHis'),
    // 'session_id'	=> $data->session_id,
    // );
    // }

    // return view('greeting',$res);
}
