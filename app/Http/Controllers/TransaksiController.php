<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransaksiController extends Controller
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

    public function index(Request $request)
    {
        $tgl = Carbon::now();

        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $sql = "select id_transaksi,ewallet,ttl_price, id_member,type_member,type_voucher from transaksi where status=0 and expired_payment::timestamp <= '" . $tgl . "'";
        $trans_expired = DB::select(DB::raw($sql));
        $whereIn = array();
        $dt_refund_ewallet = array();
        $dt_refund_voucher = array();
        if (count($trans_expired) > 0) {
            //update status ke expired payment
            foreach ($trans_expired as $te) {
                $whereIn[] = $te->id_transaksi;
                if ((int)$te->ewallet > 0 && (int)$te->type_member == 1) {
                    $dt_refund_ewallet[] = array(
                        'id_transaksi' => $te->id_transaksi,
                        'ewallet' => $te->ewallet,
                        'ttl_price' => $te->ttl_price,
                        'id_member' => $te->id_member,
                        'status' => 0,
                        'created_at' => $tgl
                    );
                }
                if ((int)$te->type_voucher == 4) {
                    $dt_refund_voucher[] = array(
                        'id_transaksi' => $te->id_transaksi,
                        'kodevoucher' => $te->kode_voucher,
                        'custid' => (int)$te->type_member == 2 ? $te->id_member : '',
                        'memberid' => (int)$te->type_member != 2 ? $te->cni_id : '',
                        'status' => 0,
                        'created_at' => $tgl
                    );
                }
            }
            DB::table('transaksi')->where(array("status" => 0))
                ->whereIn('id_transaksi', $whereIn)->update(array("status" => 2, "cek_refund_ewallet" => 1));
            if (!empty($dt_refund_ewallet)) DB::table('refund_ewallet')->insert($dt_refund_ewallet);
            if (!empty($dt_refund_voucher)) DB::table('unflag_voucher')->insert($dt_refund_voucher);
        }
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status >= 0 ? (int)$request->status : -1;
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = array();
        $where = $status >= 0 ? array('transaksi.status' => $status) : array();
        $count = 0;
        $_data = array();
        $data = null;
        $data_admin = DB::table('admin')->where('id_admin', $id_operator)->first();
        $id_level = isset($data_admin) ? (int)$data_admin->id_level : 0;
        $id_wh = isset($data_admin) ? (int)$data_admin->id_wh : 0;
        if ($id_level > 1 && $id_wh > 0) {
            $where += array('transaksi.id_wh' => $id_wh);
        }
        if ($id_wh > 0 || $id_level == 1) {
            if (!empty($keyword)) {
                $_data = DB::table('transaksi')->select(
                    'transaksi.*',
                    'members.nama as nama_member',
                    'members.email',
                    'members.phone as phone_member'

                )
                    ->where($where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
                    ->whereRaw("LOWER(id_transaksi) like '%" . $keyword . "%'")->get();
                $count = count($_data);
            } else {
                $count = DB::table('transaksi')->where($where)->count();
                //$count = count($ttl_data);
                $per_page = $per_page > 0 ? $per_page : $count;
                $offset = ($page_number - 1) * $per_page;
                $_data = DB::table('transaksi')->select(
                    'transaksi.*',
                    'members.nama as nama_member',
                    'members.email',
                    'members.phone as phone_member'

                )
                    ->where($where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
                    ->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
            }
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            // foreach ($_data as $d) {
            // $data[] = $d;
            // }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $_data
            );
        }
        return response($result);
    }

    function list_ulasan(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'tgl_ulasan';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status > 0 ? (int)$request->status : 1;
        $column_int = array("id_transaksi", "rating");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;

        $_data = array();
        $result = array();
        $where = array();
        $where = array('status_ulasan' => $status);
        $count = 0;
        if (!empty($keyword)) {
            $_data = DB::table('transaksi_detail')->where($where)->whereRaw("LOWER(id_transaksi) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('transaksi_detail')->where($where)->count();
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('transaksi_detail')->where($where)->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
                $d->unique_key = base64_encode($d->id_trans . '' . $d->id_product . '' . $d->id_category);
                $data[] = $d;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $data
            );
        }
        return response($result);
    }

    function store(Request $request)
    {
        //Log::info(serialize($request->all()));
        $url_path_doku = env('URL_JOKUL');
        $clientId = env('CLIENT_ID_JOKUL');
        $secretKey = env('SECRET_KEY_JOKUL');
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('d-m-Y H:i');
        $result = array();
        $id_member = (int)$request->id_member > 0 ? $request->id_member : 0;
        $id_address = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $tipe_pengiriman = (int)$request->tipe_pengiriman > 0 ? (int)$request->tipe_pengiriman : 0;
        $type_logistic = (int)$request->type_logistic > 0 ? (int)$request->type_logistic : 1;
        $service_code = !empty($request->service_code) ? $request->service_code : '';
        $payment = (int)$request->payment > 0 ? (int)$request->payment : 0;

        $payment_channel = !empty($request->payment_channel) ? $request->payment_channel : '';
        $ongkir = $request->ongkir > 0 ? str_replace('.', '', $request->ongkir) : 0;
        $ongkir = str_replace(',', '', $request->ongkir);
        $ongkir_origin = $request->ongkir_origin > 0 ? str_replace('.', '', $request->ongkir_origin) : 0;
        $ongkir_origin = str_replace(',', '', $request->ongkir_origin);
        $ttl_weight = $request->ttl_weight > 0 ? str_replace('.', '', $request->ttl_weight) : 0;
        $ewallet = !empty($request->ewallet) ? (int)$request->ewallet : 0;
        $id_voucher = !empty($request->id_voucher) ? (int)$request->id_voucher : 0;

        $iddc = !empty($request->iddc) ? (int)$request->iddc : 0;
        $namadc = !empty($request->namadc) ? $request->namadc : '';
        $tipe_dc = !empty($request->tipe) ? $request->tipe : '';
        $jneorigin = !empty($request->jneorigin) ? $request->jneorigin : '';
        $etd = !empty($request->etd) ? $request->etd : '';

        $is_regmitra = (int)$request->is_regmitra > 0 ? (int)$request->is_regmitra : 0;
        $is_upgrade = (int)$request->is_upgrade > 0 ? (int)$request->is_upgrade : 0;

        $kodevoucher = !empty($request->kodevoucher) ? $request->kodevoucher : '';
        $vouchervalue = !empty($request->namavoucher) ? $request->vouchervalue : '';
        $freeproduk = !empty($request->freeproduk) ? $request->freeproduk : '';

        $kode_voucher = '';
        $type_voucher = 0;
        $pot_voucher = 0;
        $is_bonus = 0;
        $is_limited = 0;
        $user_tertentu = 0;

        //total PV, total RV, total Harga Retail, total Harga Member, Total diskon
        $totalPV = 0;
        $totalRV = 0;
        $totalHargaRetail = 0;
        $totalHargaMember = 0;
        $totalDiskon = 0;

        $cara_bayar = 'no content';
        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/default_payment.png';

        if ($id_voucher > 0 && empty($kodevoucher)) {
            $where = array('vouchers.deleted_at' => null, 'id_voucher' => $id_voucher);
            $dt_voucher = DB::table('vouchers')->where($where)->first();
            $kode_voucher = $dt_voucher->kode_voucher;
            $type_voucher = (int)$dt_voucher->tipe;
            $user_tertentu = (int)$dt_voucher->user_tertentu;
            $is_limited = (int)$dt_voucher->is_limited;
            $kuota = (int)$dt_voucher->sisa;
            $cnt_used = (int)$dt_voucher->cnt_used + 1;
            $sisa = (int)$kuota - $cnt_used;
            $pot_voucher = !empty($request->potongan) ? $request->potongan : 0;
            if ($type_voucher == 3) {
                $pot_voucher = 0;
            }
        }
        if (!empty($kodevoucher) && (int)$id_voucher <= 0) {
            $kode_voucher = $kodevoucher;
            $pot_voucher = $vouchervalue;
            $type_voucher = 4;
        }
        $saldo_awal = $ewallet;
        $ttl_weight = str_replace(',', '', $ttl_weight);
        if ($tipe_pengiriman == 2) {
        }

        if ($id_member <= 0) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($tipe_pengiriman <= 0) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'tipe_pengiriman required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($tipe_pengiriman > 1 && $id_address <= 0) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'id_address required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $kode_origin = '';
        $token_mitra = 0;
        $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
        if ($is_regmitra > 0) {
            $token_mitra = random_int(100000, 999999);
            $data_member = DB::table('reg_mitra')->where(array('reg_mitra.id_reg_mitra' => $id_member))->first();
        }
        $type = !empty($data_member) ? (int)$data_member->type : 0;
        $list_item = json_decode($request->list_item);

        $nama = !empty($data_member) ? $data_member->nama : '';
        $email = !empty($data_member) ? $data_member->email : '';
        $phone_member = !empty($data_member) ? $data_member->phone : '';
        $cni_id = !empty($data_member) ? $data_member->cni_id : '';
        $where = array('address_member.deleted_at' => null, 'address_member.id_address' => (int)$id_address);
        $data_address = DB::table('address_member')->where($where)
            ->select(
                'address_member.*',
                'provinsi_name',
                'provinsi.kode_jne as kode_jne_prov',
                'provinsi.kode_lp as kode_lp_prov',
                'city_name',
                'city.kode_jne as kode_jne_city',
                'city.kode_lp as kode_lp_city',
                'kec_name',
                'kecamatan.kode_jne as kode_jne_kec',
                'kecamatan.kode_lp as kode_lp_kec',
                'warehouse.id_wh',
                'warehouse.wh_name',
                'warehouse.id_prov as id_prov_origin'
            )
            ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
            ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
            ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
            ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')->first();

        $payment_name = '';
        $key_payment = '';
        $no_va = '';
        $id_wh = !empty($data_address) && (int)$data_address->id_wh > 0 ? (int)$data_address->id_wh : 0;
        $wh_name = !empty($data_address) && (int)$data_address->id_wh ? $data_address->wh_name : 0;
        $id_prov_origin = !empty($data_address) && (int)$data_address->id_prov_origin ? (int)$data_address->id_prov_origin : 0;
        $data_origin = '';
        $prov_origin = '';
        $logistic_name = '';

        if ($tipe_pengiriman != 2) {
            $data_origin = DB::table('provinsi')->where(array('id_provinsi' => $id_prov_origin))->first();
            $prov_origin = $data_origin->provinsi_name;
            if ($type_logistic == 1) {
                $logistic_name = "JNE";
                $kode_origin = $data_origin->kode_jne;
            }
            if ($type_logistic == 2) {
                $logistic_name = "Lion Parcel";
                $kode_origin = $data_origin->kode_lp;
            }
        }
        if ($tipe_pengiriman == 2) {
            $logistic_name = "JNE";
            $id_prov_origin = -1;
            $wh_name = $namadc;
            $kode_origin = $jneorigin;
            $data_origin = DB::table('provinsi')->where(array('kode_jne' => $kode_origin))->first();
            $prov_origin = isset($data_origin) ? $data_origin->provinsi_name : '';
        }

        if ($tipe_pengiriman == 1) {
            $logistic_name = "";
            $service_code = "";
            $ongkir = 0;
            $data_address->label_alamat = "";
            $data_address->nama_penerima = "";
            $data_address->phone_penerima = "";
            $data_address->id_provinsi = "";
            $data_address->id_city = "";
            $data_address->id_kec = "";
            $data_address->alamat = "";
            $data_address->kode_pos = "";
            $data_address->provinsi_name = "";
            $data_address->kode_jne_prov = "";
            $data_address->kode_lp_prov = "";
            $data_address->city_name = "";
            $data_address->kode_jne_city = "";
            $data_address->kode_lp_city = "";
            $data_address->kec_name = "";
            $data_address->kode_jne_kec = "";
            $data_address->kode_lp_kec = "";

            if ($type_voucher == 1) {
                $id_voucher = "";
                $kode_voucher = "";
                $type_voucher = "";
                $pot_voucher = 0;
                $is_limited = 0;
            }
        }

        if (!empty($request->ewallet)) $payment_name .= "eWallet";
        if ($payment == 1) $payment_name .= !empty($payment_name) ? " & Credit Card" : "Credit Card";
        if ($payment == 4) $payment_name .= !empty($payment_name) ? " & Doku QRIS" : "Doku QRIS";
        if ($payment == 2) {
            $payment_channel_va[29] = array(
                'kode' => 29,
                'payment_name' => 'Bank BCA Virtual Account',
                'prefix' => 39355000
            );
            $payment_channel_va[32] = array(
                'kode' => 32,
                'payment_name' => 'Bank CIMB Virtual Account',
                'prefix' => 51491125
            );
            $payment_channel_va[33] = array(
                'kode' => 33,
                'payment_name' => 'Bank Danamon Virtual Account',
                'prefix' => 89220088
            );
            $payment_channel_va[34] = array(
                'kode' => 34,
                'payment_name' => 'Bank BRI Virtual Account',
                'prefix' => 45664000
            );
            $payment_channel_va[36] = array(
                'kode' => 36,
                'payment_name' => 'Bank PERMATA Virtual Account',
                'prefix' => 88560936
            );
            $payment_channel_va[37] = array(
                'kode' => 37,
                'payment_name' => 'Bank MANDIRI Virtual Account',
                'prefix' => ''
            );

            $_phone = !empty($phone_member) ? str_replace('62', '', $phone_member) : mt_rand(100000, 999999);
            $no_va = $_phone . '' . $id_member;
            $_paymentName = $payment_channel_va[$payment_channel]['payment_name'];
            $key_payment = $payment_channel_va[$payment_channel]['prefix'];
            $payment_name .= !empty($payment_name) ? " & " . $_paymentName : $_paymentName;
        }

        $expired_payment = (int)$is_regmitra > 0 ? date("Y-m-d H:i", strtotime('+3 hours', strtotime($tgl))) : date("Y-m-d H:i", strtotime('+24 hours', strtotime($tgl)));
        $status = 0;
        $dt_trans = array(
            "id_member" => (int)$is_regmitra > 0 ? 0 : $id_member,
            "id_alamat" => $id_address,
            "type_member" => $type,
            "cni_id" => $cni_id,
            "is_dropship" => (int)$request->is_dropship > 0 ? 1 : 0,
            "tipe_pengiriman" => $tipe_pengiriman,
            "ewallet" => $ewallet,
            "payment" => $payment,
            "payment_channel" => $payment_channel,
            "ttl_weight" => $ttl_weight,
            "ongkir" => $ongkir,
            "ongkir_origin" => $ongkir_origin,
            "status" => $status,
            "payment_name" => $payment_name,
            "expired_payment" => $expired_payment,
            "created_at" => $tgl,
            "id_wh" => (int)$id_wh,
            "iddc" => (int)$iddc,
            "wh_name" => $wh_name,
            "id_prov_origin" => (int)$id_prov_origin,
            "prov_origin" => $prov_origin,
            "kode_origin" => $kode_origin,
            "type_logistic" => $type_logistic,
            "logistic_name" => $logistic_name,
            "cek_refund_ewallet" => 0,
            "pot_voucher" => $pot_voucher,
            "kode_voucher" => $kode_voucher,
            "tipe_dc" => $tipe_dc,
            "is_rne25" => 0,
            "is_regmitra" => $is_regmitra,
            "is_upgrade" => $is_upgrade,
            "token_mitra" => $token_mitra,
            "etd" => $etd,
        );
        if ($tipe_pengiriman != 1) {
            $dt_trans += array(
                "service_code" => $service_code,
                "label_alamat" => $data_address->label_alamat,
                "nama_penerima" => $data_address->nama_penerima,
                "phone_penerima" => $data_address->phone_penerima,
                "id_provinsi" => $data_address->id_provinsi,
                "id_city" => $data_address->id_city,
                "id_kec" => $data_address->id_kec,
                "alamat" => $data_address->alamat,
                "kode_pos" => $data_address->kode_pos,
                "provinsi_name" => $data_address->provinsi_name,
                "kode_jne_prov" => $data_address->kode_jne_prov,
                "kode_lp_prov" => $data_address->kode_lp_prov,
                "city_name" => $data_address->city_name,
                "kode_jne_city" => $data_address->kode_jne_city,
                "kode_lp_city" => $data_address->kode_lp_city,
                "kec_name" => $data_address->kec_name,
                "kode_jne_kec" => $data_address->kode_jne_kec,
                "kode_lp_kec" => $data_address->kode_lp_kec,
            );
        }

        if ($id_voucher > 0 && empty($kodevoucher)) {
            $dt_trans += array(
                'id_voucher' => $id_voucher,
                'kode_voucher' => $kode_voucher,
                'type_voucher' => $type_voucher
            );
        }

        if (!empty($kodevoucher) && (int)$id_voucher <= 0) {
            $dt_trans += array(
                'kode_voucher' => $kode_voucher,
                'type_voucher' => $type_voucher
            );
        }
        $id_transaksi = 0;
        $id_transaksi = DB::table('transaksi')->insertGetId($dt_trans, "id_transaksi");

        $whereIn = array();
        $_pricelist = array();
        $myPriceId = array();
        $_whereIn = '';
        $sql_pricelist = '';
        DB::beginTransaction();
        try {
            for ($i = 0; $i < count($list_item); $i++) {
                $whereIn[] = $list_item[$i]->id_product;
                $_whereIn = implode(', ', $whereIn);
                $jml = $list_item[$i]->jml;
                $dt_product[$list_item[$i]->id_product] = array(
                    'id_product' => $list_item[$i]->id_product,
                    'jml' => $jml
                );
            }

            $sql_pricelist = "select * from pricelist
            where id_product in (" . $_whereIn . ") and deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";

            $pricelist_active = DB::select(DB::raw($sql_pricelist));
            if (!empty($pricelist_active)) {
                foreach ($pricelist_active as $pa) {
                    array_push($myPriceId, $pa->id_product);
                    $_pricelist['id_pricelist'][$pa->id_product] = $pa->id_pricelist;
                    $_pricelist['start_date_price'][$pa->id_product] = $pa->start_date;
                    $_pricelist['end_date_price'][$pa->id_product] = $pa->end_date;
                    $_pricelist['harga_member'][$pa->id_product] = $pa->harga_member;
                    $_pricelist['harga_konsumen'][$pa->id_product] = $pa->harga_konsumen;
                    $_pricelist['pv'][$pa->id_product] = $pa->pv;
                    $_pricelist['rv'][$pa->id_product] = $pa->rv;
                    $_pricelist['hm_non_ppn'][$pa->id_product] = $pa->hm_non_ppn;
                    $_pricelist['hk_non_ppn'][$pa->id_product] = $pa->hk_non_ppn;
                    $_pricelist['ppn_hm'][$pa->id_product] = $pa->ppn_hm;
                    $_pricelist['ppn_hk'][$pa->id_product] = $pa->ppn_hk;
                }
            }
            $err_stok = array();
            $err_hrg = array();
            $dt_insert = array();
            $upd_product = array();
            $where = array('product.deleted_at' => null);
            $_data = DB::table('product')->select('product.*', 'category_name')
                ->whereIn('id_product', $whereIn)
                ->leftJoin('category', 'category.id_category', '=', 'product.id_category')->where($where)->get();

            $sub_ttl = 0;
            $jdp = 0;

            if (count($_data) > 0) {
                foreach ($_data as $dt) {
                    $harga = 0;
                    $non_ppn = 0;
                    $dt->id_pricelist = isset($_pricelist['id_pricelist'][$dt->id_product]) ? $_pricelist['id_pricelist'][$dt->id_product] : 0;
                    $dt->harga_member = isset($_pricelist['harga_member'][$dt->id_product]) ? $_pricelist['harga_member'][$dt->id_product] : 0;
                    $dt->harga_konsumen = isset($_pricelist['harga_konsumen'][$dt->id_product]) ? $_pricelist['harga_konsumen'][$dt->id_product] : 0;
                    $dt->start_date_price = isset($_pricelist['start_date_price'][$dt->id_product]) ? $_pricelist['start_date_price'][$dt->id_product] : null;
                    $dt->end_date_price = isset($_pricelist['end_date_price'][$dt->id_product]) ? $_pricelist['end_date_price'][$dt->id_product] : null;
                    $dt->pv = isset($_pricelist['pv'][$dt->id_product]) ? $_pricelist['pv'][$dt->id_product] : 0;
                    $dt->rv = isset($_pricelist['rv'][$dt->id_product]) ? $_pricelist['rv'][$dt->id_product] : 0;
                    $dt->hm_non_ppn = isset($_pricelist['hm_non_ppn'][$dt->id_product]) ? $_pricelist['hm_non_ppn'][$dt->id_product] : 0;
                    $dt->hk_non_ppn = isset($_pricelist['hk_non_ppn'][$dt->id_product]) ? $_pricelist['hk_non_ppn'][$dt->id_product] : 0;
                    $dt->ppn_hm = isset($_pricelist['ppn_hm'][$dt->id_product]) ? $_pricelist['ppn_hm'][$dt->id_product] : 0;
                    $dt->ppn_hk = isset($_pricelist['ppn_hk'][$dt->id_product]) ? $_pricelist['ppn_hk'][$dt->id_product] : 0;
                    $harga = $type == 1 || $type == 3 || $is_upgrade == 1 || $is_regmitra == 1 ? $dt->harga_member : $dt->harga_konsumen;
                    $non_ppn = $type == 1 || $type == 3 || $is_upgrade == 1 || $is_regmitra == 1 ? $dt->hm_non_ppn : $dt->hk_non_ppn;
                    $ttl_harga = $harga * (int)$dt_product[$dt->id_product]['jml'];
                    $ttl_non_ppn = $non_ppn * (int)$dt_product[$dt->id_product]['jml'];
                    $sub_ttl += $ttl_harga;
                    $jdp += $ttl_non_ppn;
                    $dt_insert[] = array(
                        "id_trans" => $id_transaksi,
                        "kode_produk" => !empty($dt->kode_produk) ? $dt->kode_produk : '-',
                        "id_product" => $dt->id_product,
                        "jml" => (int)$dt_product[$dt->id_product]['jml'],
                        "img" => !empty($dt->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $dt->img : '',
                        "id_category" => $dt->id_category,
                        "product_name" => $dt->product_name,
                        "category_name" => $dt->category_name,
                        "special_promo" => (int)$dt->special_promo,
                        "berat" => (int)$dt->berat,
                        "harga" => $harga,
                        "ttl_harga" => $ttl_harga,
                        "ttl_berat" => (int)$dt->berat * (int)$dt_product[$dt->id_product]['jml'],
                        "id_pricelist" => $dt->id_pricelist,
                        "harga_member" => $dt->harga_member,
                        "harga_konsumen" => $dt->harga_konsumen,
                        "start_date_price" => $dt->start_date_price,
                        "end_date_price" => $dt->end_date_price,
                        "pv" => $dt->pv,
                        "rv" => $dt->rv,
                        "hm_non_ppn" => $dt->hm_non_ppn,
                        "hk_non_ppn" => $dt->hk_non_ppn,
                        "ppn_hm" => $dt->ppn_hm,
                        "ppn_hk" => $dt->ppn_hk,
                        "is_bonus" => 0
                    );
                    $jml_prod = (int)$dt_product[$dt->id_product]['jml'];
                    $totalPV += ($jml_prod * $dt->pv);
                    $totalRV += ($jml_prod * $dt->rv);
                    $totalHargaRetail += ($jml_prod * $dt->harga_konsumen);
                    $totalHargaMember += ($jml_prod * $dt->harga_member);
                    $upd_product[] = array(
                        "id_product" => $dt->id_product,
                        "qty" => (int)$dt->qty - (int)$dt_product[$dt->id_product]['jml']
                    );
                    if ((int)$dt->qty < (int)$dt_product[$dt->id_product]['jml'] && $tipe_pengiriman == 3) {
                        $err_stok[] = $dt;
                    }
                    if (!in_array($dt->id_product, $myPriceId)) {
                        $err_hrg[] = $dt;
                    }
                }
                if ($type == 1 || $type == 3 || $is_upgrade == 1 || $is_regmitra == 1) {
                    $totalDiskon = $totalHargaRetail - $totalHargaMember;
                }
                if ($type_voucher == 3) {
                    $pot_voucher = 0;
                    $produk_utama = (int)$dt_voucher->produk_utama;
                    $produk_bonus = (int)$dt_voucher->produk_bonus;
                    $where = array();
                    $where = array('product.deleted_at' => null, 'product.id_product' => $produk_bonus);
                    $dt_produk_bonus = DB::table('product')->where($where)
                        ->leftJoin('category', 'category.id_category', '=', 'product.id_category')->first();
                    $produk_bonus_name = isset($dt_produk_bonus) ? $dt_produk_bonus->product_name : '';
                    $produk_bonus_kode = isset($dt_produk_bonus) ? $dt_produk_bonus->kode_produk : '';
                    $is_bonus = $produk_utama;
                    $dt_insert[] = array(
                        "id_trans" => $id_transaksi,
                        "kode_produk" => !empty($produk_bonus_kode) ? $produk_bonus_kode : '-',
                        "id_product" => $produk_bonus,
                        "jml" => 1,
                        "img" => !empty($dt_produk_bonus->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $dt_produk_bonus->img : '',
                        "id_category" => $dt_produk_bonus->id_category,
                        "product_name" => $produk_bonus_name,
                        "category_name" => $dt_produk_bonus->category_name,
                        "special_promo" => 0,
                        "berat" => (int)$dt_produk_bonus->berat,
                        "harga" => 0,
                        "ttl_harga" => 0,
                        "ttl_berat" => (int)$dt_produk_bonus->berat,
                        "id_pricelist" => 0,
                        "harga_member" => 0,
                        "harga_konsumen" => 0,
                        "start_date_price" => null,
                        "end_date_price" => null,
                        "pv" => 0,
                        "rv" => 0,
                        "hm_non_ppn" => 0,
                        "hk_non_ppn" => 0,
                        "ppn_hm" => 0,
                        "ppn_hk" => 0,
                        "is_bonus" => $is_bonus
                    );
                }
                if ($type_voucher == 4) {
                    $pot_voucher = 0;
                    $produk_bonus = $freeproduk;
                    $where = array();
                    $where = array('product.kode_produk' => $produk_bonus);
                    $dt_produk_bonus = DB::table('product')->where($where)
                        ->leftJoin('category', 'category.id_category', '=', 'product.id_category')->first();
                    $produk_bonus_name = isset($dt_produk_bonus) ? $dt_produk_bonus->product_name : '';
                    $id_product_bonus = isset($dt_produk_bonus) ? (int)$dt_produk_bonus->id_product : 0;
                    $produk_bonus_kode = $produk_bonus;
                    $is_bonus = 1;
                    $dt_insert[] = array(
                        "id_trans" => $id_transaksi,
                        "kode_produk" => (int)$id_product_bonus > 0 && !empty($produk_bonus_kode) ? $produk_bonus_kode : '-',
                        "id_product" => $id_product_bonus,
                        "jml" => 1,
                        "img" => (int)$id_product_bonus > 0 && !empty($dt_produk_bonus->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $dt_produk_bonus->img : '',
                        "id_category" => (int)$id_product_bonus > 0 ? $dt_produk_bonus->id_category : '',
                        "product_name" => (int)$id_product_bonus > 0 ? $produk_bonus_name : '',
                        "category_name" => (int)$id_product_bonus > 0 ? $dt_produk_bonus->category_name : '',
                        "special_promo" => 0,
                        "berat" => (int)$id_product_bonus > 0 ? (int)$dt_produk_bonus->berat : 0,
                        "harga" => 0,
                        "ttl_harga" => 0,
                        "ttl_berat" => (int)$id_product_bonus > 0 ? (int)$dt_produk_bonus->berat : 0,
                        "id_pricelist" => 0,
                        "harga_member" => 0,
                        "harga_konsumen" => 0,
                        "start_date_price" => null,
                        "end_date_price" => null,
                        "pv" => 0,
                        "rv" => 0,
                        "hm_non_ppn" => 0,
                        "hk_non_ppn" => 0,
                        "ppn_hm" => 0,
                        "ppn_hk" => 0,
                        "is_bonus" => $is_bonus
                    );
                }
            }

            if (count($err_hrg) > 0) {
                DB::rollback();
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'Harga invalid',
                    'data' => $err_hrg
                );
                return response($result);
                return false;
            }
            if (count($err_stok) > 0) {
                DB::rollback();
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                $result = array(
                    'err_code' => '04',
                    'err_msg' => 'Stok tidak cukup',
                    'data' => $err_stok
                );
                return response($result);
                return false;
            }

            if ($type_voucher == 4) {
                $custid = empty($cni_id) ? $id_member : '';
                $memberid = $cni_id;
                $submit_voucher = Helper::submit_voucher($custid, $memberid, $kodevoucher, $id_transaksi);
                Log::info(serialize($submit_voucher));
                if ($submit_voucher->result != 'y') {
                    DB::rollback();
                    DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                    return response($submit_voucher);
                    return false;
                }
            }

            if ((int)$id_transaksi > 0) {
                $_key_payment = str_replace([' ', '/', '&', '.', '_', '-'], '', $key_payment);
                $words = '234678' . $sub_ttl . 'NRd509eQng1F' . $_key_payment . 'ABCDEGHIJKLMOPSTUVWXYZ' . 'abcfhijklmopqrstuvwxyz';
                $charactersLength = strlen($words);
                $randomString = '';
                for ($i = 0; $i < 32; $i++) {
                    $randomString .= $words[rand(0, $charactersLength - 1)];
                }
                $sub_ttl_ongkir = $sub_ttl + $ongkir;

                $sub_ttl_ongkir_pot_voucher = $sub_ttl_ongkir - $pot_voucher;
                $nominal_doku = $sub_ttl_ongkir_pot_voucher - $ewallet;
                $action = "ALLOCATE_EWALLET";
                $ket = '';
                if ($sub_ttl_ongkir_pot_voucher <= $ewallet) {
                    $randomString = '';
                    $key_payment = '';
                    $payment = 3;
                    $status = 1;
                    $action = "ALLOCATE_EWALLET";
                    $ewallet = $sub_ttl_ongkir_pot_voucher;
                    $ket = "Pembayaran penuh transaksi #" . $id_transaksi;
                    $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/wallet.png';
                }

                $data_ewallet = '';
                if ($type == 1 && $ewallet > 0) {
                    $cni_id = !empty($data_member) && !empty($data_member->cni_id) ? $data_member->cni_id : '';
                    if (!empty($cni_id) && $payment != 3) {
                        $ket = !empty($ket) ? $ket : "Pembayaran sebagian transaksi #" . $id_transaksi;
                        $data_ewallet = Helper::trans_ewallet($action, $cni_id, $sub_ttl_ongkir_pot_voucher, $ewallet, $id_transaksi, $request->all(), "submit_transaksi", 1, $ket, 1, $id_member);
                    }
                    if (isset($data_ewallet['result']) && $data_ewallet['result'] != "Y") {
                        DB::rollback();
                        DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                        return response($data_ewallet);
                        return false;
                    }
                }

                if ($payment == 1) {
                    $requestId = $id_transaksi;
                    $invoice_number = $requestId;
                    $amount = $nominal_doku;
                    $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/cc.png';
                    $targetPath = "/credit-card/v1/payment-page";
                    $requestBody = array(
                        'order' => array(
                            'amount' => $amount,
                            //"callback_url"		=> site_url('jokul_notif/notify_jokul'),
                            "auto_redirect" => false,
                            'invoice_number' => $id_transaksi,
                        ),


                        'customer' => array(
                            'id' => $id_member,
                            'name' => !empty($nama) ? $nama : 'CNI',
                            'email' => $email,
                        ),
                    );
                    $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));
                    $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
                    $componentSignature = "Client-Id:" . $clientId . "\n" .
                        "Request-Id:" . $requestId . "\n" .
                        "Request-Timestamp:" . $dateTimeFinal . "\n" .
                        "Request-Target:" . $targetPath . "\n" .
                        "Digest:" . $digestValue;
                    $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
                    $headers = array(
                        'Content-Type: application/json',
                        'Client-Id:' . $clientId,
                        'Request-Id:' . $requestId,
                        'Request-Timestamp:' . $dateTimeFinal,
                        'Signature:HMACSHA256=' . $signature
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

                    $result = curl_exec($ch);
                    if ($result === FALSE) {
                        die('Send Error: ' . curl_error($ch));
                    }


                    curl_close($ch);

                    $data_result = json_decode($result);
                    $dt = isset($data_result->credit_card_payment_page) ? $data_result->credit_card_payment_page : '';
                    $key_payment = !empty($dt) ? $dt->url : '';
                }

                if ($payment == 4) {
                    $length_id = strlen($id_transaksi);
                    $length = 15 - (int)$length_id;
                    $randomletter = substr(str_shuffle("cniCNIcniCNIcni"), 0, $length);
                    $base64 = base64_encode($randomletter . "" . $id_transaksi);
                    $data_qris = Helper::generate_qris($base64, $nominal_doku);
                    $key_payment = $data_qris['qrCode'];
                    $key_payment = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl=' . $key_payment;
                }
                if ($payment == 2) {
                    $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));
                    $payment_channel = (int)$request->payment_channel > 0 ? (int)$request->payment_channel : 0;
                    $requestId = $id_transaksi;
                    $invoice_number = $requestId;
                    $targetPath = '';

                    $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/default_payment.png';
                    if ($payment_channel == 29) {
                        $targetPath = "/bca-virtual-account/v2/merchant-payment-code";
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/29.png';
                        $cara_bayar = '<div>
						  Cara membayar di ATM<br>
						  1. Masukkan kartu ATM BCA dan PIN<br>
						  2. Pilih "Transaksi Lainnya"<br>
						  3. Pilih "Transfer"<br>
						  4. Pilih "ke Rekening BCA Virtual Account"<br>
						  5. Masukkan nomor BCA Virtual Account<br>
						  6. Masukkan jumlah yang ingin dibayarkan<br>
						  7. Setelah Proses Validasi selesai dengan menekan tombol "Ya", simpan bukti transaksi anda.<br>
						</div>
						<div>
						  Cara membayar menggunakan KlikBCA Individual<br>
						  1. Login pada aplikasi KlikBCA Individual<br>
						  2. Masukkan User ID dan PIN<br>
						  3. Pilih "Transfer Dana"<br>
						  4. Pilih "Transfer ke BCA Virtual Account"<br>
						  5. Masukkan nomor BCA Virtual<br>
						  6. Masukkan jumlah yang ingin dibayarkan<br>
						  7. Setelah Proses Validasi selesai dengan menekan tombol "Kirim", simpan bukti transaksi anda.<br>
						</div>';
                    }
                    if ($payment_channel == 32) {
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/32.jpg';
                        $targetPath = "/cimb-virtual-account/v2/payment-code";
                        $cara_bayar = '<div>
                            <b>Melalui ATM CIMB Niaga</b><br/>
                            1. Masukkan kartu ATM CIMB Niaga, lalu masukkan "PIN ATM".<br/>
                            2. Pilih menu "Transfer".<br/>
                            3. Pilih menu "Rekening CIMB Niaga".<br/>
                            4. Masukkan "Jumlah" lalu masukkan "Nomor Virtual Account".<br/>
                            5. Ketika muncul konfirmasi transfer, pilih "Ya" / "Lanjut".<br/>
                            6. Transaksi selesai dan simpan bukti transaksi.<br/>
						</div>
						<div>
                            <b>Melalui Mobile Banking CIMB NIAGA</b><br/>
                            1. Login Mobile Banking CIMB Niaga.<br/>
                            2. Pilih menu "Transfer", lalu pilih "Rekening Ponsel/CIMB Niaga".<br/>
                            3. Pilih "Rekening sumber".<br/>
                            4. Pilih "Rekening Tujuan": CASA.<br/>
                            5. Masukkan "Nomor Virtual Account" dan "Jumlah".<br/>
                            6. Ketika muncul konfirmasi pembayaran, pilih "Ya" / "Lanjut".<br/>
                            7. Transaksi selesai dan simpan bukti transaksi.<br/>
                        </div>
                        <div>
                            <b>Melalui Internet Banking CIMB Niaga</b><br/>
                            1. Login ke Internet Banking CIMB Niaga.<br/>
                            2. Pilih Menu "TRANSFER".<br/>
                            3. Pilih rekening sumber dana pada bagian "Transfer From", masukkan "Jumlah", lalu pada bagian "Transfer To" pilih "Other Account (CIMB Niaga/Rekening Ponsel)", kemudian pilih "NEXT".<br/>
                            4. Pilih "BANK CIMB NIAGA", lalu masukkan Nomor Virtual Account di kolom "Rekening Penerima", kemudian pilih "NEXT".<br/>
                            5. Masukkan "mPIN" lalu pilih "Submit".<br/>
                            6. Transaksi selesai dan simpan bukti transaksi.<br/>
                        </div>
                        <div>
                            <b>Melalui Teller CIMB Niaga</b><br/>
                            1. Datangi Teller CIMB Niaga di kantor CIMB Niaga.<br/>
                            2. Isi Form Setoran Tunai termasuk Nomor Virtual Account #noaccount dan jumlah pembayaran sesuai Tagihan.<br/>
                            3. Serahkan Form Setoran Tunai beserta uang tunai ke Teller CIMB Niaga.<br/>
                            4. Transaksi selesai dan simpan Copy Slip Setoran Tunai sebagai Bukti Bayar.<br/>
                        </div>
                        <div>
                            <b>Melalui ATM Bank Lain</b><br/>
                            1. Masukkan kartu ATM, lalu masukkan PIN ATM.<br/>
                            2. Pilih Menu Transfer Antar Bank.<br/>
                            3. Masukan Kode Bank Tujuan : CIMB Niaga (Kode Bank : 022) + Nomor Virtual Account #noaccount.<br/>
                            4. Masukan jumlah transfer sesuai Tagihan.<br/>
                            5. Ketika Muncul konfirmasi pembayaran, pilih "Ya" / "Lanjut".<br/>
                            6. Transaksi selesai dan ambil bukti transfer anda<br/>
                        </div>';
                    }
                    if ($payment_channel == 33) {
                        $targetPath = "/danamon-virtual-account/v2/payment-code";
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/33.png';
                        $cara_bayar = '<div>
						  <b>Melalui ATM Danamon</b><br/>
							1. Masukkan kartu ATM Danamon, lalu masukkan PIN ATM.<br/>
							2. Pilih menu "Pembayaran", kemudian menu "Lainnya".<br/>
							3. Pilih menu "Virtual Account".<br/>
							4. Masukkan Nomor Virtual Account 8922006300000xxx.<br/>
							5. Ketika muncul Konfirmasi Transfer, pilih "Ya" / "Lanjut".<br/>
							6. Transaksi selesai. Simpan bukti transaksi.<br/>
						</div>
						<div>
						  <b>Melalui ATM Bank Lain</b><br>
							1. Masukkan kartu ATM Danamon, lalu masukkan PIN ATM.<br/>
							2. Pilih menu "Transfer Antar Bank".<br/>
							3. Masukan Kode Bank Tujuan : Danamon (Kode Bank : 011) + Nomor Virtual Account 8922006300000019.<br/>
							4. Masukan jumlah sesuai tagihan.<br/>
							&nbsp;&nbsp;&nbsp;&nbsp; 1. Ketika muncul Konfirmasi Transfer, pilih "Ya" / "Lanjut".<br/>
							5. Transaksi selesai dan ambil bukti transfer anda<br/>
						</div>';
                    }
                    if ($payment_channel == 34) {
                        $targetPath = "/bri-virtual-account/v2/payment-code";
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/34.png';
                        $cara_bayar = '<div>
						  <b>Cara membayar di ATM BRI</b><br/>
							1. Masukkan kartu ATM BRI, lalu masukkan PIN ATM.<br/>
							2. Pilih Menu "Transaksi Lain", kemudian pilih menu "Pembayaran".<br/>
							3. Pilih Menu "Lainnya", lalu pilih menu "Briva".<br/>
							4. Masukkan nomor rekening dengan nomor Virtual Account Anda 2581300000000xxx dan pilih Benar<br/>
							5. Ketika muncul konfirmasi pembayaran, pilih "Ya" / "Lanjut".<br/>
							6. Transaksi selesai dan ambil bukti transaksi.<br/>
						</div>
						<div>
						  <b>Cara membayar melalui Internet Banking BRI</b><br>
							1. Login Internet Banking, kemudian pilih Menu "Pembayaran".<br>
							2. Pilih menu "Briva".<br/>
							3. Masukkan nomor rekening dengan nomor Virtual Account Anda 2581300000000xxx kemudian klik "Kirim".<br/>
							4. Setelah itu, masukkan Password serta mToken internet banking<br/>
							5. Transaksi selesai dan simpan bukti pembayaran anda.<br/>

						</div>
						<div>
						  <b>Melalui Mobile Banking BRI</b><br/>
							1. Login Mobile Banking, lalu pilih menu "Pembayaran".<br/>
							2. Setelah itu pilih menu "Briva".<br/>
							3. Masukkan nomor rekening dengan Nomor Virtual Account 2581300000000xxx, lalu masukkan jumlah.<br/>
							4. Masukkan "PIN Mobile Banking" dan klik "Kirim".<br/>
							5. Transaksi selesai dan bukti pembayaran anda akan dikirimkan melalui notifikasi SMS.<br/>
						</div>
						<div>
						  <b>Melalui Teller BRI</b><br>
							1. Datangi Teller BRI di kantor BRI.<br/>
							2. Isi Form Setoran Tunai termasuk Nomor Virtual Account 2581300000000xxx dan jumlah sesuai Tagihan.<br/>
							3. Serahkan Form Setoran Tunai beserta uang tunai ke Teller BRI.<br/>
							4. Transaksi selesai dan simpan Copy Slip Setoran Tunai sebagai Bukti Bayar.<br/>
						</div>
						<div>
						  <b>Melalui ATM / Mobile Banking Bank Lain</b><br>
							1. Masukkan kartu ATM, lalu masukkan PIN ATM.<br/>
							2. Pilih Menu "Transfer Antar Bank".<br/>
							3. Masukan Kode Bank Tujuan : BRI (Kode Bank : 002) + Nomor Virtual Account 2581300000000xxx.<br/>
							4. Masukan Jumlah.<br/>
							5. Ketika muncul Konfirmasi Transfer, piliha "Ya" / "Lanjut".<br/>
							6. Transaksi selesai dan ambil bukti transfer anda.<br/>
						</div>';
                    }
                    if ($payment_channel == 36) {
                        $targetPath = "/permata-virtual-account/v2/payment-code";
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/36.png';
                        $cara_bayar = '<div>
						  <b>Cara membayar di ATM Permata</b><br/>
							1. Masukkan PIN <br/>
							2. Pilih "Transfer". Apabila menggunakan ATM Bank Lain, pilih "Transaksi lainnya" lalu "Transfer"<br/>
							3. Pilih "Ke Rek Bank Lain"<br/>
							4. Masukkan Kode Bank Permata (013) diikuti 16 digit kode bayar 8856046200000xxx sebagai rekening tujuan, kemudian tekan "Benar"<br/>
							5. Masukkan Jumlah pembayaran sesuai dengan yang ditagihkan (Jumlah yang ditransfer harus sama persis, tidak boleh lebih dan kurang). Jumlah nominal yang tidak sesuai dengan tagihan akan menyebabkan transaksi gagal <br/>
							6. Muncul Layar Konfirmasi Transfer yang berisi nomor rekening tujuan Bank Permata dan Nama beserta jumlah yang dibayar, jika sudah benar, Tekan "Benar" <br/>
							7. Selesai<br/>
						</div>
						<div>
						  <b>Cara membayar di Internet Banking</b><br>
						  <b>Keterangan: Pembayaran dilakukan di Internet Banking BCA (KlikBCA)</b><br>
							1. Login ke dalam akun Internet Banking<br/>
							2. Pilih "Transfer" dan pilih "Transfer Virtual Account".<br/>
							3. Masukkan Kode Virtual Account nya<br/>
							4. Setelah selesai memasukkan Kode Virtual Account tekan Lanjutkan<br/>
							5. Muncul layar konfirmasi jumlah total pembayaran jika sudah benar tekan Ya.<br/>
							6. Selesai<br/>
						</div>';
                    }
                    if ($payment_channel == 37) {
                        $targetPath = "/mandiri-virtual-account/v2/payment-code";
                        $metode_pembayaran = 'https://mcni.cni.co.id/api_cni/uploads/mandiri.jpg';
                        $cara_bayar = '<div>
						  <b>Melalui ATM Mandiri</b><br/>
							1.  Masukkan kartu ATM Mandiri, lalu masukkan PIN ATM.<br/>
							2.  Pilih Menu "Bayar/Beli"<br/>
							3.  Pilih "Lainnya" dan pilih "Lainnya" kembali<br/>
							4.  Pilih "Ecommerce"<br/>
							5.  Masukkan 5 digit awal dari nomor Mandiri VA (Virtual Account) yang di dapat (contoh: 88899)<br/>
							6.  Masukkan keseluruhan nomor VA contoh : 8889916800000xxx<br/>
							7.  Masukkan jumlah pembayaran<br/>
                            8.  Nomor VA, Nama dan Jumlah pembayaran akan ditampilkan di layar<br/>
                            9.  Tekan angka 1 dan pilih "YA"<br/>
                            10. Konfirmasi pembayaran dan pilih "YA"<br/>
                            11. Transaksi selesai. Mohon simpan bukti transaksi.<br/>
						</div>
						<div>
						  <b>Melalui Internet Banking Individu Bank Mandiri</b><br>
							1.	Akses ke https://ib.bankmandiri.co.id/retail/Login.do?action=form&lang=in_ID<br/>
                            2.	Masukkan User ID dan PIN, kemudian login<br/>
                            3.	Pilih menu "Pembayaran"<br/>
                            4.	Pilih Menu "Multi Payment"<br/>
                            5.	Pilih Billing Name "DOKU VA Aggregator"<br/>
                            6.	Masukkan VA  No 8889916800000xxx<br/>
                            7.	Masukkan Nominal Transaksi<br/>
                            8.	Klik tombol "Continue"<br/>
                            9.	Centang pada bagian Total Tagihan<br/>
                            10.	Klik tombol "Continue"<br/>
                            11.	Input PIN Mandiri Appli 1 dari Token<br/>
                            12.	Selesai dan Simpan Bukti Pembayaran<br/>
						</div>
						<div>
						  <b>Melalui Mandiri Online Apps</b><br/>
							1.	Install aplikasi Mandiri Online<br/>
                            2.	Masukkan User ID dan PIN, kemudian login<br/>
                            3.	Pilih Menu Pembayaran<br/>
                            4.	Klik Buat Pembayaran Baru<br/>
                            5.	Pilih Multipayment<br/>
                            6.	Pilih "DOKU VA Aggregator" pada bagian penyedia jasa<br/>
                            7.	Masukkan Nomor VA 8889916800000xxx<br/>
                            8.	Klik Go, kemudian masukkan nominal transaksi<br/>
                            9.	Klik "Konfirmasi"<br/>
                            10.	Klik "Lanjut"<br/>
                            11.	Klik "Konfirmasi"<br/>
                            12.	Masukkan MPIN (PIN SMS Banking)<br/>
                            13.	Selesai dan Simpan Bukti Pembayaran Anda<br/>
						</div>';
                    }
                    $amount = $nominal_doku;
                    $requestBody = array(
                        'order' => array(
                            'amount' => $amount,
                            'invoice_number' => $invoice_number,
                        ),
                        'virtual_account_info' => array(
                            'expired_time' => 180,
                            'reusable_status' => false,

                        ),
                        'customer' => array(
                            'name' => !empty($nama) ? $nama : 'CNI',
                            'email' => $email,
                        ),
                    );
                    if ($payment_channel == 29) {
                        // $prefix = 39355000;
                        // $_phone = !empty($phone_member) ? str_replace('62', '', $phone_member) : mt_rand(100000, 999999);
                        // $no_va = $_phone . '' . $id_member . '' . $id_transaksi;
                        // $no_va = substr($no_va, -8);
                        // $no_va = $prefix . '' . $no_va;
                        $no_va = '3830038300' . $id_transaksi;
                        $requestBody = array(
                            'order' => array(
                                'amount' => $amount,
                                'invoice_number' => $invoice_number,
                            ),
                            'virtual_account_info' => array(
                                'virtual_account_number' => $no_va,
                                'expired_time' => 180,
                                'reusable_status' => false,

                            ),
                            'customer' => array(
                                'name' => !empty($nama) ? $nama : 'CNI',
                                'email' => $email,
                            ),
                        );
                    }
                    $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
                    $componentSignature = "Client-Id:" . $clientId . "\n" .
                        "Request-Id:" . $requestId . "\n" .
                        "Request-Timestamp:" . $dateTimeFinal . "\n" .
                        "Request-Target:" . $targetPath . "\n" .
                        "Digest:" . $digestValue;
                    $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
                    $headers = array(
                        'Content-Type: application/json',
                        'Client-Id:' . $clientId,
                        'Request-Id:' . $requestId,
                        'Request-Timestamp:' . $dateTimeFinal,
                        'Signature:HMACSHA256=' . $signature
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

                    $result = curl_exec($ch);
                    if ($result === FALSE) {
                        die('Send Error: ' . curl_error($ch));
                    }

                    curl_close($ch);
                    // Log::info(serialize($componentSignature));
                    // Log::info(serialize($headers));
                    // Log::info(serialize($requestBody));
                    // Log::info('signature :' . $signature);
                    // Log::info('secretKey :' . $secretKey);
                    // Log::info('clientId : ' . $clientId);
                    // Log::info(serialize($result));
                    $data_result = json_decode($result);
                    $dt = isset($data_result->virtual_account_info) ? $data_result->virtual_account_info : '';
                    $key_payment = !empty($dt) ? $dt->virtual_account_number : '';
                }
                $dt_upd = array();
                $dt_upd = array(
                    "session_id" => $randomString,
                    "ttl_belanjaan" => $sub_ttl,
                    "sub_ttl" => $sub_ttl_ongkir,
                    "pot_voucher" => $pot_voucher,
                    "ttl_price" => $sub_ttl_ongkir_pot_voucher,
                    "nominal_doku" => (int)$nominal_doku > 0 ? $nominal_doku : 0,
                    "key_payment" => $key_payment,
                    "payment" => $payment,
                    "jdp" => $jdp,
                    "status" => $status,
                    "ttl_pv" => $totalPV,
                    "ttl_rv" => $totalRV,
                    "ttl_hr" => $totalHargaRetail,
                    "ttl_hm" => $totalHargaMember,
                    "ttl_disc" => $totalDiskon,
                );

                DB::table('transaksi_detail')->insert($dt_insert);
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
                if ($payment == 4) {
                    $path = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl=' . $key_payment;
                    unset($dt_upd['key_payment']);
                    $dt_upd += array('key_payment' => $path);
                }
                if ($is_regmitra > 0) {
                    DB::table('reg_mitra')->where('id_reg_mitra', $id_member)->update(array('id_transaksi' => $id_transaksi, 'total_belanja' => $sub_ttl));
                    unset($dt_upd['key_payment']);
                    $dt_upd += array('key_payment' => '');
                }
                $dt_trans += $dt_upd;
                $dt_trans += array('id_transaksi' => $id_transaksi);
                $dt_trans += array('list_item' => $dt_insert);
                if ((int)$is_limited > 0) {
                    $dt_voucher = array();
                    $dt_voucher = array(
                        'cnt_used' => $cnt_used,
                        'sisa' => $sisa
                    );

                    DB::table('vouchers')->where('id_voucher', $id_voucher)->update($dt_voucher);
                }
                // DB::connection()->enableQueryLog();
                if ($id_voucher > 0) {
                    $where_voucher_member = array(
                        'id_voucher' => $id_voucher,
                        'id_member' => $id_member
                    );
                    if ($user_tertentu > 0) {
                        $where_voucher_member += array(
                            "deleted_at" => null,
                            "is_used" => null
                        );
                        DB::table('list_member_voucher')->where($where_voucher_member)->update(array('is_used' => $id_transaksi, 'updated_at' => $tgl, 'updated_by' => -1));
                        // Log::info(DB::getQueryLog());
                    } else {
                        $where_voucher_member += array(
                            "created_at" => $tgl,
                            "created_by" => -1,
                            "is_used" => $id_transaksi
                        );
                        DB::table('list_member_voucher')->insert($where_voucher_member);
                    }
                }
                if ((int)$is_regmitra <= 0) {
                    DB::table('cart')->where('id_member', $id_member)->whereIn('id_product', $whereIn)->delete();
                    $setting = DB::table('setting')->get()->toArray();
                    $out = array();
                    if (!empty($setting)) {
                        foreach ($setting as $val) {
                            $out[$val->setting_key] = $val->setting_val;
                        }
                    }
                    if ($status <= 0) {
                        $key_payment = $payment == 2 ? $key_payment : '';
                        $content_email_transaksi = $out['content_email_transaksi'];
                        $content_email_transaksi = str_replace('[#tgl_order#]', $_tgl, $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#ongkir#]', number_format($ongkir), $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#nama#]', $nama, $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#pot_voucher#]', number_format($pot_voucher), $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#ttl_bayar#]', number_format($sub_ttl_ongkir), $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#cara_bayar#]', $cara_bayar, $content_email_transaksi);
                        $content_email_transaksi = str_replace('[#key_payment#]', $key_payment, $content_email_transaksi);
                        $content_email_transaksi = str_replace('https://mcni.cni.co.id/api_cni/uploads/29.png', $metode_pembayaran, $content_email_transaksi);
                        $html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
							<tbody>';
                        for ($t = 0; $t < count($dt_insert); $t++) {
                            $html .= '<tr>';
                            $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
							<img src="' . $dt_insert[$t]['img'] . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
                            $html .= '<td valign="top" style="padding:0 0 16px 16px">
										<div style="margin:0 0 4px;line-height:16px">' . $dt_insert[$t]['product_name'] . '</div>
										<p style="font-weight:bold;margin:4px 0 0">' . number_format($dt_insert[$t]['jml']) . ' x
											<span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($dt_insert[$t]['harga']) . '</span>
										</p>
									</td>';
                            $html .= '</tr>';
                        }

                        $html .= '</tbody></table>';

                        $data_email = array();
                        $content_email_transaksi = str_replace('[#detail_pesanan#]', $html, $content_email_transaksi);
                        //Log::info($content_email_transaksi);
                        $data_email['nama'] = $nama;
                        $data_email['email'] = $email;
                        $data_email['subject'] = 'Menunggu Pembayaran mCNI No Order ' . $id_transaksi;
                        $data_email['content_email'] = $content_email_transaksi;
                        Mail::send([], ['users' => $data_email], function ($message) use ($data_email) {
                            $message->to($data_email['email'], $data_email['nama'])->subject($data_email['subject'])->setBody($data_email['content_email'], 'text/html');
                        });
                    }
                    if ($status == 1) {
                        $content_email_payment_complete = $out['content_email_payment_complete'];
                        $content_email_payment_complete = str_replace('[#nama#]', $nama, $content_email_payment_complete);
                        $content_email_payment_complete = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_payment_complete);
                        $content_email_payment_complete = str_replace('[#ttl_bayar#]', number_format($sub_ttl_ongkir), $content_email_payment_complete);
                        $content_email_payment_complete = str_replace('http://202.158.64.238/api_cni/uploads/29.png', $metode_pembayaran, $content_email_payment_complete);
                        $html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
							<tbody>';
                        for ($t = 0; $t < count($dt_insert); $t++) {
                            $html .= '<tr>';
                            $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
							<img src="' . $dt_insert[$t]['img'] . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
                            $html .= '<td valign="top" style="padding:0 0 16px 16px">
										<div style="margin:0 0 4px;line-height:16px">' . $dt_insert[$t]['product_name'] . '</div>
										<p style="font-weight:bold;margin:4px 0 0">' . number_format($dt_insert[$t]['jml']) . ' x
											<span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($dt_insert[$t]['harga']) . '</span>
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
                        $data_email['content_email'] = $content_email_payment_complete;
                        Mail::send([], ['users' => $data_email], function ($message) use ($data_email) {
                            $message->to($data_email['email'], $data_email['nama'])->subject('Transaksi')->setBody($data_email['content_email'], 'text/html');
                        });
                    }
                }
                if ((int)$is_regmitra > 0) {
                    $setting = DB::table('setting')->get()->toArray();
                    $out = array();
                    if (!empty($setting)) {
                        foreach ($setting as $val) {
                            $out[$val->setting_key] = $val->setting_val;
                        }
                    }
                    $content_email_hold_cust = $out['content_email_otp_mitra'];
                    $content_email_hold_cust = str_replace('[#kode_otp#]', $token_mitra, $content_email_hold_cust);
                    $html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
                        <tbody>';
                    for ($t = 0; $t < count($dt_insert); $t++) {
                        $html .= '<tr>';
                        $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
						<img src="' . $dt_insert[$t]['img'] . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
                        $html .= '<td valign="top" style="padding:0 0 16px 16px">
									<div style="margin:0 0 4px;line-height:16px">' . $dt_insert[$t]['product_name'] . '</div>
									<p style="font-weight:bold;margin:4px 0 0">' . number_format($dt_insert[$t]['jml']) . ' x
										<span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($dt_insert[$t]['harga']) . '</span>
									</p>
								</td>';
                        $html .= '</tr>';
                    }

                    $html .= '</tbody></table>';

                    $data_email = array();
                    $content_email_hold_cust = str_replace('[#detail_pesanan#]', $html, $content_email_hold_cust);
                    $data_email['nama'] = $nama;
                    $data_email['email'] = $email;
                    $data_email['content_email'] = $content_email_hold_cust;
                    Mail::send([], ['users' => $data_email], function ($message) use ($data_email) {
                        $message->to($data_email['email'], $data_email['nama'])->subject('Transaksi Mitra')->setBody($data_email['content_email'], 'text/html');
                    });
                }

                DB::commit();
                if ($status == 1) Helper::send_order_cni($id_transaksi, 'transaksi', $is_upgrade);
                if ($tipe_pengiriman == 3) Helper::upd_stok($upd_product);
            } else {
                DB::rollback();
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
            }
        } catch (Exception $e) {
            Log::info($e);
            DB::rollback();
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
            // something went wrong
        }

        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $dt_trans
        );
        return response($result);
    }

    function store_rne25(Request $request)
    {
        $url_path_doku = env('URL_JOKUL');
        $clientId = env('CLIENT_ID_JOKUL');
        $secretKey = env('SECRET_KEY_JOKUL');
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('d-m-Y H:i');
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $payment = (int)$request->payment > 0 ? (int)$request->payment : 0;
        $payment_channel = !empty($request->payment_channel) ? $request->payment_channel : '';
        $ewallet = !empty($request->ewallet) ? (int)$request->ewallet : 0;
        $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
        $type = !empty($data_member) ? (int)$data_member->type : 0;

        $nama = !empty($data_member) ? $data_member->nama : '';
        $email = !empty($data_member) ? $data_member->email : '';
        $phone_member = !empty($data_member) ? $data_member->phone : '';
        $cni_id = !empty($data_member) ? $data_member->cni_id : '';
        $payment_name = '';
        $key_payment = '';
        $no_va = '';
        //total PV, total RV, total Harga Retail, total Harga Member, Total diskon
        $totalPV = 0;
        $totalRV = 0;
        $totalHargaRetail = 0;
        $totalHargaMember = 0;
        $totalDiskon = 0;
        $tgll = date('Y-m-d');
        $is_rne25 = 0;
        if ($type == 2 && !empty($cni_id)) {
            $end_date = date('Y-m-d', strtotime($data_member->end_member));
            if ($tgll > $end_date) {
                $date1 = date_create($end_date);
                $date2 = date_create($tgll);
                $diff = date_diff($date1, $date2);
                $is_grace_periode = 16 - (int)$diff->format("%R%a");
                $is_rne25 = 2;
            }
        }
        if ($type == 1) {
            $end_date = date('Y-m-d', strtotime($data_member->end_member));
            $last_month_member_date = date('Y-m-d', strtotime("-1 months", strtotime($end_date)));
            if ($tgll >= $last_month_member_date && $tgll <= $end_date) {
                $last_month_member = 1;
                $is_rne25 = 1;
            }
            if ($tgll > $end_date) {
                $date1 = date_create($end_date);
                $date2 = date_create($tgll);
                $diff = date_diff($date1, $date2);
                $is_grace_periode = 16 - (int)$diff->format("%R%a");
                $dataa = array("type" => 2, "updated_at" => date('Y-m-d H:i:s'));
                DB::table('members')->where('id_member', $id_member)->update($dataa);
                $type = 2;
                $last_month_member = 0;
                $is_rne25 = 2;
            }
        }

        if ($is_rne25 == 0) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'belum saatnya beli RNE25',
                'data' => null
            );
            return response($result);
            return false;
        }

        $_whereee = array('transaksi.status' => 0, 'transaksi_detail.id_product' => 1, 'transaksi.id_member' => $id_member);
        $cnt_exist_trans = DB::table('transaksi_detail')->where($_whereee)->leftJoin('transaksi', 'transaksi.id_transaksi', '=', 'transaksi_detail.id_trans')->count();
        if ((int)$cnt_exist_trans > 0) {
            $dt_exist_trans = DB::table('transaksi_detail')->where($_whereee)->leftJoin('transaksi', 'transaksi.id_transaksi', '=', 'transaksi_detail.id_trans')->get();
            foreach ($dt_exist_trans as $te) {
                $whereIn[] = $te->id_transaksi;
                if ((int)$te->ewallet > 0 && (int)$te->type_member == 1) {
                    $dt_refund_ewallet[] = array(
                        'id_transaksi' => $te->id_transaksi,
                        'ewallet' => $te->ewallet,
                        'ttl_price' => $te->ttl_price,
                        'id_member' => $te->id_member,
                        'status' => 0,
                        'created_at' => $tgl
                    );
                }
            }
            DB::table('transaksi')->where(array("status" => 0))
                ->whereIn('id_transaksi', $whereIn)->update(array("status" => 2, "cek_refund_ewallet" => 1));
            if (!empty($dt_refund_ewallet)) DB::table('refund_ewallet')->insert($dt_refund_ewallet);
        }


        if (!empty($request->ewallet)) $payment_name .= "eWallet";
        if ($payment == 1) $payment_name .= !empty($payment_name) ? " & Credit Card" : "Credit Card";
        if ($payment == 4) $payment_name .= !empty($payment_name) ? " & Doku QRIS" : "Doku QRIS";
        if ($payment == 2) {
            $payment_channel_va[29] = array(
                'kode' => 29,
                'payment_name' => 'Bank BCA Virtual Account',
                'prefix' => 39355000
            );
            $payment_channel_va[32] = array(
                'kode' => 32,
                'payment_name' => 'Bank CIMB Virtual Account',
                'prefix' => 51491125
            );
            $payment_channel_va[33] = array(
                'kode' => 33,
                'payment_name' => 'Bank Danamon Virtual Account',
                'prefix' => 89220088
            );
            $payment_channel_va[34] = array(
                'kode' => 34,
                'payment_name' => 'Bank BRI Virtual Account',
                'prefix' => 45664000
            );
            $payment_channel_va[36] = array(
                'kode' => 36,
                'payment_name' => 'Bank PERMATA Virtual Account',
                'prefix' => 88560936
            );
            $payment_channel_va[37] = array(
                'kode' => 37,
                'payment_name' => 'Bank MANDIRI Virtual Account',
                'prefix' => ''
            );

            $_phone = !empty($phone_member) ? str_replace('62', '', $phone_member) : mt_rand(100000, 999999);
            $no_va = $_phone . '' . $id_member;
            $_paymentName = $payment_channel_va[$payment_channel]['payment_name'];
            $key_payment = $payment_channel_va[$payment_channel]['prefix'];
            $payment_name .= !empty($payment_name) ? " & " . $_paymentName : $_paymentName;
        }

        $status = 0;
        $expired_payment = date("Y-m-d H:i", strtotime('+24 hours', strtotime($tgl)));
        $dt_trans = array(
            "id_member" => $id_member,
            "type_member" => $type,
            "cni_id" => $cni_id,
            "is_dropship" => 0,
            "tipe_pengiriman" => 0,
            "ewallet" => $ewallet,
            "payment" => $payment,
            "payment_channel" => $payment_channel,
            "ttl_weight" => 0,
            "ongkir" => 0,
            "status" => $status,
            "payment_name" => $payment_name,
            "expired_payment" => $expired_payment,
            "created_at" => $tgl,
            "id_wh" => 0,
            "iddc" => 0,
            "wh_name" => "-",
            "id_prov_origin" => 0,
            "prov_origin" => "-",
            "kode_origin" => "-",
            "type_logistic" => 0,
            "logistic_name" => "-",
            "is_rne25" => $is_rne25,
            "cek_refund_ewallet" => 0
        );
        $id_transaksi = 0;
        $id_transaksi = DB::table('transaksi')->insertGetId($dt_trans, "id_transaksi");
        $sub_ttl = 0;
        $jdp = 0;
        $where = array("product.id_product" => 1);
        $_data = DB::table('product')->select('product.*', 'category_name', 'pricelist.*')
            ->leftJoin('category', 'category.id_category', '=', 'product.id_category')
            ->leftJoin('pricelist', 'pricelist.id_product', '=', 'product.id_product')->where($where)->first();
        $harga = 0;
        $non_ppn = 0;
        $id_pricelist = $_data->id_pricelist;
        $harga_member = $_data->harga_member * 1;
        $harga_konsumen = $_data->harga_konsumen * 1;
        $start_date_price = $_data->start_date;
        $end_date_price = $_data->end_date;
        $pv = $_data->pv * 1;
        $rv = $_data->rv * 1;
        $hm_non_ppn = $_data->hm_non_ppn * 1;
        $hk_non_ppn = $_data->hk_non_ppn * 1;
        $ppn_hm = $_data->ppn_hm * 1;
        $ppn_hk = $_data->ppn_hk * 1;
        $harga = $type == 1 ? $harga_member : $harga_konsumen;
        $non_ppn = $type == 1 ? $hm_non_ppn : $hk_non_ppn;
        $ttl_harga = $harga * 1;
        $ttl_non_ppn = $non_ppn * 1;
        $sub_ttl += $ttl_harga;
        $jdp += $ttl_non_ppn;
        $dt_insert = array(
            "id_trans" => $id_transaksi,
            "kode_produk" => !empty($_data->kode_produk) ? $_data->kode_produk : '-',
            "id_product" => $_data->id_product,
            "jml" => 1,
            "img" => !empty($_data->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $_data->img : '',
            "id_category" => $_data->id_category,
            "product_name" => $_data->product_name,
            "category_name" => $_data->category_name,
            "special_promo" => (int)$_data->special_promo,
            "berat" => (int)$_data->berat,
            "harga" => $harga,
            "ttl_harga" => $ttl_harga,
            "ttl_berat" => 0,
            "id_pricelist" => $id_pricelist,
            "harga_member" => $harga_member,
            "harga_konsumen" => $harga_konsumen,
            "start_date_price" => $start_date_price,
            "end_date_price" => $end_date_price,
            "pv" => $pv,
            "rv" => $rv,
            "hm_non_ppn" => $hm_non_ppn,
            "hk_non_ppn" => $hk_non_ppn,
            "ppn_hm" => $ppn_hm,
            "ppn_hk" => $ppn_hk,
            "is_bonus" => 0
        );
        $jml_prod = (int)1;
        $totalPV += ($jml_prod * $pv);
        $totalRV += ($jml_prod * $rv);
        $totalHargaRetail += ($jml_prod * $harga_konsumen);
        $totalHargaMember += ($jml_prod * $harga_member);
        if ((int)$id_transaksi > 0) {

            $_key_payment = str_replace([' ', '/', '&', '.', '_', '-'], '', $key_payment);
            $words = '234678' . $sub_ttl . 'NRd509eQng1F' . $_key_payment . 'ABCDEGHIJKLMOPSTUVWXYZ' . 'abcfhijklmopqrstuvwxyz';
            $charactersLength = strlen($words);
            $randomString = '';
            for ($i = 0; $i < 32; $i++) {
                $randomString .= $words[rand(0, $charactersLength - 1)];
            }
            $sub_ttl_ongkir = $sub_ttl;

            $sub_ttl_ongkir_pot_voucher = $sub_ttl_ongkir - 0;
            $nominal_doku = $sub_ttl_ongkir_pot_voucher - $ewallet;
            $action = "ALLOCATE_EWALLET";
            $ket = '';
            if ($sub_ttl_ongkir_pot_voucher <= $ewallet) {
                $randomString = '';
                $key_payment = '';
                $payment = 3;
                $status = 1;
                $action = "PAID_EWALLET";
                $ewallet = $sub_ttl_ongkir_pot_voucher;
                $ket = "Pembayaran penuh transaksi #" . $id_transaksi;
            }
            $data_ewallet = '';
            if ($type == 1 && $ewallet > 0) {
                $cni_id = !empty($data_member) && !empty($data_member->cni_id) ? $data_member->cni_id : '';
                if (!empty($cni_id) && $payment != 3) {
                    $ket = !empty($ket) ? $ket : "Pembayaran sebagian transaksi #" . $id_transaksi;
                    $data_ewallet = Helper::trans_ewallet($action, $cni_id, $sub_ttl_ongkir_pot_voucher, $ewallet, $id_transaksi, $request->all(), "submit_transaksi", 1, $ket, 1, $id_member);
                }
                if (isset($data_ewallet['result']) && $data_ewallet['result'] != "Y") {
                    DB::rollback();
                    DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                    return response($data_ewallet);
                    return false;
                }
            }

            if ($payment == 1) {
                $requestId = $id_transaksi;
                $invoice_number = $requestId;
                $amount = $nominal_doku;
                $targetPath = "/credit-card/v1/payment-page";
                $requestBody = array(
                    'order' => array(
                        'amount' => $amount,
                        //"callback_url"		=> site_url('jokul_notif/notify_jokul'),
                        "auto_redirect" => false,
                        'invoice_number' => $id_transaksi,
                    ),


                    'customer' => array(
                        'id' => $id_member,
                        'name' => !empty($nama) ? $nama : 'CNI',
                        'email' => $email,
                    ),
                );
                $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));
                $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
                $componentSignature = "Client-Id:" . $clientId . "\n" .
                    "Request-Id:" . $requestId . "\n" .
                    "Request-Timestamp:" . $dateTimeFinal . "\n" .
                    "Request-Target:" . $targetPath . "\n" .
                    "Digest:" . $digestValue;
                $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
                $headers = array(
                    'Content-Type: application/json',
                    'Client-Id:' . $clientId,
                    'Request-Id:' . $requestId,
                    'Request-Timestamp:' . $dateTimeFinal,
                    'Signature:HMACSHA256=' . $signature
                );
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

                $result = curl_exec($ch);
                if ($result === FALSE) {
                    die('Send Error: ' . curl_error($ch));
                }


                curl_close($ch);

                $data_result = json_decode($result);
                $dt = isset($data_result->credit_card_payment_page) ? $data_result->credit_card_payment_page : '';
                $key_payment = !empty($dt) ? $dt->url : '';
            }

            if ($payment == 4) {
                $length_id = strlen($id_transaksi);
                $length = 15 - (int)$length_id;
                $randomletter = substr(str_shuffle("cniCNIcniCNIcni"), 0, $length);
                $base64 = base64_encode($randomletter . "" . $id_transaksi);
                $data_qris = Helper::generate_qris($base64, $nominal_doku);
                $key_payment = $data_qris['qrCode'];
                $key_payment = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl=' . $key_payment;
            }

            if ($payment == 2) {
                $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));
                $payment_channel = (int)$request->payment_channel > 0 ? (int)$request->payment_channel : 0;
                $requestId = $id_transaksi;
                $invoice_number = $requestId;
                $targetPath = '';
                if ($payment_channel == 29) {
                    $targetPath = "/bca-virtual-account/v2/merchant-payment-code";
                }
                if ($payment_channel == 32) {
                    $targetPath = "/cimb-virtual-account/v2/payment-code";
                }
                if ($payment_channel == 33) {
                    $targetPath = "/danamon-virtual-account/v2/payment-code";
                }
                if ($payment_channel == 34) {
                    $targetPath = "/bri-virtual-account/v2/payment-code";
                }
                if ($payment_channel == 36) {
                    $targetPath = "/permata-virtual-account/v2/payment-code";
                }
                if ($payment_channel == 37) {
                    $targetPath = "/mandiri-virtual-account/v2/payment-code";
                }
                $amount = $nominal_doku;
                $requestBody = array(
                    'order' => array(
                        'amount' => $amount,
                        'invoice_number' => $invoice_number,
                    ),
                    'virtual_account_info' => array(
                        'expired_time' => 180,
                        'reusable_status' => false,

                    ),
                    'customer' => array(
                        'name' => !empty($nama) ? $nama : 'CNI',
                        'email' => $email,
                    ),
                );
                if ($payment_channel == 29) {
                    // $prefix = 39355000;
                    // $_phone = !empty($phone_member) ? str_replace('62', '', $phone_member) : mt_rand(100000, 999999);
                    // $no_va = $_phone . '' . $id_member . '' . $id_transaksi;
                    // $no_va = substr($no_va, -8);
                    // $no_va = $prefix . '' . $no_va;
                    $no_va = '3830038300' . $id_transaksi;
                    $requestBody = array(
                        'order' => array(
                            'amount' => $amount,
                            'invoice_number' => $invoice_number,
                        ),
                        'virtual_account_info' => array(
                            'virtual_account_number' => $no_va,
                            'expired_time' => 180,
                            'reusable_status' => false,

                        ),
                        'customer' => array(
                            'name' => !empty($nama) ? $nama : 'CNI',
                            'email' => $email,
                        ),
                    );
                }
                $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
                $componentSignature = "Client-Id:" . $clientId . "\n" .
                    "Request-Id:" . $requestId . "\n" .
                    "Request-Timestamp:" . $dateTimeFinal . "\n" .
                    "Request-Target:" . $targetPath . "\n" .
                    "Digest:" . $digestValue;
                $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
                $headers = array(
                    'Content-Type: application/json',
                    'Client-Id:' . $clientId,
                    'Request-Id:' . $requestId,
                    'Request-Timestamp:' . $dateTimeFinal,
                    'Signature:HMACSHA256=' . $signature
                );
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

                $result = curl_exec($ch);
                if ($result === FALSE) {
                    die('Send Error: ' . curl_error($ch));
                }

                curl_close($ch);
                 Log::info(serialize($componentSignature));
                 Log::info(serialize($headers));
                 Log::info(serialize($requestBody));
                 Log::info('signature :' . $signature);
                 Log::info('secretKey :' . $secretKey);
                 Log::info('clientId : ' . $clientId);
                 Log::info(serialize($result));
                $data_result = json_decode($result);
                $dt = isset($data_result->virtual_account_info) ? $data_result->virtual_account_info : '';
                $key_payment = !empty($dt) ? $dt->virtual_account_number : '';
            }
            $dt_upd = array();
            $dt_upd = array(
                "session_id" => $randomString,
                "ttl_belanjaan" => $sub_ttl,
                "sub_ttl" => 0,
                "pot_voucher" => 0,
                "ttl_price" => $sub_ttl_ongkir_pot_voucher,
                "nominal_doku" => (int)$nominal_doku > 0 ? $nominal_doku : 0,
                "key_payment" => $key_payment,
                "payment" => $payment,
                "jdp" => $jdp,
                "status" => $status,
                "ttl_pv" => $totalPV,
                "ttl_rv" => $totalRV,
                "ttl_hr" => $totalHargaRetail,
                "ttl_hm" => $totalHargaMember,
                "ttl_disc" => $totalDiskon,
            );

            DB::table('transaksi_detail')->insert($dt_insert);
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            if ($payment == 4) {
                $path = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl=' . $key_payment;
                unset($dt_upd['key_payment']);
                $dt_upd += array('key_payment' => $path);
            }
            $dt_trans += $dt_upd;
            $dt_trans += array('id_transaksi' => $id_transaksi);
            $dt_trans += array('list_item' => $dt_insert);
            // DB::connection()->enableQueryLog();
            if ($status == 1) Helper::send_order_cni($id_transaksi, 'transaksi');
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $dt_trans
        );
        return response($result);
    }

    function transaksi_detail(Request $request)
    {
        $result = array();
        $_data = array();
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $where = array('transaksi.id_transaksi' => $id_transaksi);
        $_data = DB::table('transaksi')->select(
            'transaksi.*',
            'members.nama as nama_member',
            'members.email',
            'members.phone as phone_member',
            'alamat_wh',
            'phone_wh',
            'email_wh'
        )
            ->where($where)
            ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'transaksi.id_wh')
            ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
        $cnt_details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->count();
        $list_item = null;
        if ($cnt_details > 0) {
            $details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
            foreach ($details as $d) {
                $list_item[] = $d;
            }
        }
        if ((int)$_data->token_mitra > 0) {
            unset($_data->key_payment);
            $_data->key_payment = '';
        }
        $payment_name = str_replace('VA', 'Virtual Account', $_data->payment_name);
        $payment_channel = (int)$_data->payment_channel;
        $cara_bayar = 'no content';
        if ($payment_channel == 29) {
            $cara_bayar = '<div>
						  Cara membayar di ATM<br>
						  1. Masukkan kartu ATM BCA dan PIN<br>
						  2. Pilih "Transaksi Lainnya"<br>
						  3. Pilih "Transfer"<br>
						  4. Pilih "ke Rekening BCA Virtual Account"<br>
						  5. Masukkan nomor BCA Virtual Account<br>
						  6. Masukkan jumlah yang ingin dibayarkan<br>
						  7. Setelah Proses Validasi selesai dengan menekan tombol "Ya", simpan bukti transaksi anda.<br>
						</div>
						<div>
						  Cara membayar menggunakan KlikBCA Individual<br>
						  1. Login pada aplikasi KlikBCA Individual<br>
						  2. Masukkan User ID dan PIN<br>
						  3. Pilih "Transfer Dana"<br>
						  4. Pilih "Transfer ke BCA Virtual Account"<br>
						  5. Masukkan nomor BCA Virtual<br>
						  6. Masukkan jumlah yang ingin dibayarkan<br>
						  7. Setelah Proses Validasi selesai dengan menekan tombol "Kirim", simpan bukti transaksi anda.<br>
						</div>';
        }
        if ($payment_channel == 32) {
            $cara_bayar = '<div>
                            <b>Melalui ATM CIMB Niaga</b><br/>
                            1. Masukkan kartu ATM CIMB Niaga, lalu masukkan "PIN ATM".<br/>
                            2. Pilih menu "Transfer".<br/>
                            3. Pilih menu "Rekening CIMB Niaga".<br/>
                            4. Masukkan "Jumlah" lalu masukkan "Nomor Virtual Account".<br/>
                            5. Ketika muncul konfirmasi transfer, pilih "Ya" / "Lanjut".<br/>
                            6. Transaksi selesai dan simpan bukti transaksi.<br/>
						</div>
						<div>
                            <b>Melalui Mobile Banking CIMB NIAGA</b><br/>
                            1. Login Mobile Banking CIMB Niaga.<br/>
                            2. Pilih menu "Transfer", lalu pilih "Rekening Ponsel/CIMB Niaga".<br/>
                            3. Pilih "Rekening sumber".<br/>
                            4. Pilih "Rekening Tujuan": CASA.<br/>
                            5. Masukkan "Nomor Virtual Account" dan "Jumlah".<br/>
                            6. Ketika muncul konfirmasi pembayaran, pilih "Ya" / "Lanjut".<br/>
                            7. Transaksi selesai dan simpan bukti transaksi.<br/>
                        </div>
                        <div>
                            <b>Melalui Internet Banking CIMB Niaga</b><br/>
                            1. Login ke Internet Banking CIMB Niaga.<br/>
                            2. Pilih Menu "TRANSFER".<br/>
                            3. Pilih rekening sumber dana pada bagian "Transfer From", masukkan "Jumlah", lalu pada bagian "Transfer To" pilih "Other Account (CIMB Niaga/Rekening Ponsel)", kemudian pilih "NEXT".<br/>
                            4. Pilih "BANK CIMB NIAGA", lalu masukkan Nomor Virtual Account di kolom "Rekening Penerima", kemudian pilih "NEXT".<br/>
                            5. Masukkan "mPIN" lalu pilih "Submit".<br/>
                            6. Transaksi selesai dan simpan bukti transaksi.<br/>
                        </div>
                        <div>
                            <b>Melalui Teller CIMB Niaga</b><br/>
                            1. Datangi Teller CIMB Niaga di kantor CIMB Niaga.<br/>
                            2. Isi Form Setoran Tunai termasuk Nomor Virtual Account #noaccount dan jumlah pembayaran sesuai Tagihan.<br/>
                            3. Serahkan Form Setoran Tunai beserta uang tunai ke Teller CIMB Niaga.<br/>
                            4. Transaksi selesai dan simpan Copy Slip Setoran Tunai sebagai Bukti Bayar.<br/>
                        </div>
                        <div>
                            <b>Melalui ATM Bank Lain</b><br/>
                            1. Masukkan kartu ATM, lalu masukkan PIN ATM.<br/>
                            2. Pilih Menu Transfer Antar Bank.<br/>
                            3. Masukan Kode Bank Tujuan : CIMB Niaga (Kode Bank : 022) + Nomor Virtual Account #noaccount.<br/>
                            4. Masukan jumlah transfer sesuai Tagihan.<br/>
                            5. Ketika Muncul konfirmasi pembayaran, pilih "Ya" / "Lanjut".<br/>
                            6. Transaksi selesai dan ambil bukti transfer anda<br/>
                        </div>';
        }
        if ($payment_channel == 33) {
            $cara_bayar = '<div>
						  <b>Melalui ATM Danamon</b><br/>
							1. Masukkan kartu ATM Danamon, lalu masukkan PIN ATM.<br/>
							2. Pilih menu "Pembayaran", kemudian menu "Lainnya".<br/>
							3. Pilih menu "Virtual Account".<br/>
							4. Masukkan Nomor Virtual Account 8922006300000xxx.<br/>
							5. Ketika muncul Konfirmasi Transfer, pilih "Ya" / "Lanjut".<br/>
							6. Transaksi selesai. Simpan bukti transaksi.<br/>
						</div>
						<div>
						  <b>Melalui ATM Bank Lain</b><br>
							1. Masukkan kartu ATM Danamon, lalu masukkan PIN ATM.<br/>
							2. Pilih menu "Transfer Antar Bank".<br/>
							3. Masukan Kode Bank Tujuan : Danamon (Kode Bank : 011) + Nomor Virtual Account 8922006300000019.<br/>
							4. Masukan jumlah sesuai tagihan.<br/>
							&nbsp;&nbsp;&nbsp;&nbsp; 1. Ketika muncul Konfirmasi Transfer, pilih "Ya" / "Lanjut".<br/>
							5. Transaksi selesai dan ambil bukti transfer anda<br/>
						</div>';
        }
        if ($payment_channel == 34) {
            $cara_bayar = '<div>
						  <b>Cara membayar di ATM BRI</b><br/>
							1. Masukkan kartu ATM BRI, lalu masukkan PIN ATM.<br/>
							2. Pilih Menu "Transaksi Lain", kemudian pilih menu "Pembayaran".<br/>
							3. Pilih Menu "Lainnya", lalu pilih menu "Briva".<br/>
							4. Masukkan nomor rekening dengan nomor Virtual Account Anda 2581300000000xxx dan pilih Benar<br/>
							5. Ketika muncul konfirmasi pembayaran, pilih "Ya" / "Lanjut".<br/>
							6. Transaksi selesai dan ambil bukti transaksi.<br/>
						</div>
						<div>
						  <b>Cara membayar melalui Internet Banking BRI</b><br>
							1. Login Internet Banking, kemudian pilih Menu "Pembayaran".<br>
							2. Pilih menu "Briva".<br/>
							3. Masukkan nomor rekening dengan nomor Virtual Account Anda 2581300000000xxx kemudian klik "Kirim".<br/>
							4. Setelah itu, masukkan Password serta mToken internet banking<br/>
							5. Transaksi selesai dan simpan bukti pembayaran anda.<br/>

						</div>
						<div>
						  <b>Melalui Mobile Banking BRI</b><br/>
							1. Login Mobile Banking, lalu pilih menu "Pembayaran".<br/>
							2. Setelah itu pilih menu "Briva".<br/>
							3. Masukkan nomor rekening dengan Nomor Virtual Account 2581300000000xxx, lalu masukkan jumlah.<br/>
							4. Masukkan "PIN Mobile Banking" dan klik "Kirim".<br/>
							5. Transaksi selesai dan bukti pembayaran anda akan dikirimkan melalui notifikasi SMS.<br/>
						</div>
						<div>
						  <b>Melalui Teller BRI</b><br>
							1. Datangi Teller BRI di kantor BRI.<br/>
							2. Isi Form Setoran Tunai termasuk Nomor Virtual Account 2581300000000xxx dan jumlah sesuai Tagihan.<br/>
							3. Serahkan Form Setoran Tunai beserta uang tunai ke Teller BRI.<br/>
							4. Transaksi selesai dan simpan Copy Slip Setoran Tunai sebagai Bukti Bayar.<br/>
						</div>
						<div>
						  <b>Melalui ATM / Mobile Banking Bank Lain</b><br>
							1. Masukkan kartu ATM, lalu masukkan PIN ATM.<br/>
							2. Pilih Menu "Transfer Antar Bank".<br/>
							3. Masukan Kode Bank Tujuan : BRI (Kode Bank : 002) + Nomor Virtual Account 2581300000000xxx.<br/>
							4. Masukan Jumlah.<br/>
							5. Ketika muncul Konfirmasi Transfer, piliha "Ya" / "Lanjut".<br/>
							6. Transaksi selesai dan ambil bukti transfer anda.<br/>
						</div>';
        }
        if ($payment_channel == 36) {
            $cara_bayar = '<div>
						  <b>Cara membayar di ATM</b><br/>
							1. Masukkan PIN <br/>
							2. Pilih "Transfer". Apabila menggunakan ATM Bank Lain, pilih "Transaksi lainnya" lalu "Transfer"<br/>
							3. Pilih "Ke Rek Bank Lain"<br/>
							4. Masukkan Kode Bank Permata (013) diikuti 16 digit kode bayar sebagai rekening tujuan, kemudian tekan "Benar"<br/>
							5. Masukkan Jumlah pembayaran sesuai dengan yang ditagihkan (Jumlah yang ditransfer harus sama persis, tidak boleh lebih dan kurang). Jumlah nominal yang tidak sesuai dengan tagihan akan menyebabkan transaksi gagal <br/>
							6. Muncul Layar Konfirmasi Transfer yang berisi nomor rekening tujuan Bank Permata dan Nama beserta jumlah yang dibayar, jika sudah benar, Tekan "Benar" <br/>
							7. Selesai<br/>
						</div>
						<div>
						  <b>Cara membayar di Internet Banking</b><br>
						  <b>Keterangan: Pembayaran tidak bisa dilakukan di Internet Banking BCA (KlikBCA)</b><br>
							1. Login ke dalam akun Internet Banking<br/>
							2. Pilih "Transfer" dan pilih "Bank Lainnya". Pilih Bank Permata (013) sebagai rekening tujuan.<br/>
							3. Masukkan jumlah pembayaran sesuai dengan yang di tagihkan.<br/>
							4. Isi nomor rekening tujuan dengan 16 digit kode pembayaran.<br/>
							5. Muncul layar konfirmasi Transfer yang berisi nomor rekening tujuan Bank Permata dan Nama beserta jumlah yang dibayar. Jika sudah benar, tekan "Benar".<br/>
							6. Selesai.<br/>
						</div>';
        }
        if ($payment_channel == 37) {
            $cara_bayar = '<div>
						  <b>Melalui ATM Mandiri</b><br/>
							1.  Masukkan kartu ATM Mandiri, lalu masukkan PIN ATM.<br/>
							2.  Pilih Menu "Bayar/Beli"<br/>
							3.  Pilih "Lainnya" dan pilih "Lainnya" kembali<br/>
							4.  Pilih "Ecommerce"<br/>
							5.  Masukkan 5 digit awal dari nomor Mandiri VA (Virtual Account) yang di dapat (contoh: 88899)<br/>
							6.  Masukkan keseluruhan nomor VA contoh : 8889916800000xxx<br/>
							7.  Masukkan jumlah pembayaran<br/>
                            8.  Nomor VA, Nama dan Jumlah pembayaran akan ditampilkan di layar<br/>
                            9.  Tekan angka 1 dan pilih "YA"<br/>
                            10. Konfirmasi pembayaran dan pilih "YA"<br/>
                            11. Transaksi selesai. Mohon simpan bukti transaksi.<br/>
						</div>
						<div>
						  <b>Melalui Internet Banking Individu Bank Mandiri</b><br>
							1.	Akses ke https://ib.bankmandiri.co.id/retail/Login.do?action=form&lang=in_ID<br/>
                            2.	Masukkan User ID dan PIN, kemudian login<br/>
                            3.	Pilih menu "Pembayaran"<br/>
                            4.	Pilih Menu "Multi Payment"<br/>
                            5.	Pilih Billing Name "DOKU VA Aggregator"<br/>
                            6.	Masukkan VA  No 8889916800000xxx<br/>
                            7.	Masukkan Nominal Transaksi<br/>
                            8.	Klik tombol "Continue"<br/>
                            9.	Centang pada bagian Total Tagihan<br/>
                            10.	Klik tombol "Continue"<br/>
                            11.	Input PIN Mandiri Appli 1 dari Token<br/>
                            12.	Selesai dan Simpan Bukti Pembayaran<br/>
						</div>
						<div>
						  <b>Melalui Mandiri Online Apps</b><br/>
							1.	Install aplikasi Mandiri Online<br/>
                            2.	Masukkan User ID dan PIN, kemudian login<br/>
                            3.	Pilih Menu Pembayaran<br/>
                            4.	Klik Buat Pembayaran Baru<br/>
                            5.	Pilih Multipayment<br/>
                            6.	Pilih "DOKU VA Aggregator" pada bagian penyedia jasa<br/>
                            7.	Masukkan Nomor VA 8889916800000xxx<br/>
                            8.	Klik Go, kemudian masukkan nominal transaksi<br/>
                            9.	Klik "Konfirmasi"<br/>
                            10.	Klik "Lanjut"<br/>
                            11.	Klik "Konfirmasi"<br/>
                            12.	Masukkan MPIN (PIN SMS Banking)<br/>
                            13.	Selesai dan Simpan Bukti Pembayaran Anda<br/>
						</div>';
        }

        unset($_data->session_id);
        unset($_data->delivery_by);
        unset($_data->log_payment);
        unset($_data->payment_name);
        $_data->payment_name = $payment_name;
        $_data->cara_bayar = $cara_bayar;
        $_data->list_item = $list_item;
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $_data
        );
        return response($result);
    }

    function set_onprocess(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $remark = $request->has('remark') && !empty($request->remark) ? $request->remark : '';
        if ($id_operator <= 0 || $id_transaksi <= 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'id transaksi dan id_operator required',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $where = array('transaksi.id_transaksi' => $id_transaksi);
        $cnt_details = DB::table('transaksi')->where($where)->count();
        if ($cnt_details > 0) {
            $dt_upd = array();
            $data = DB::table('transaksi')->where($where)->first();
            $dt_upd = array(
                'status' => 3,
                'remark_onprocess' => $remark,
                'onprocess_date' => $tgl,
                'onprocess_by' => $id_operator
            );
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            unset($dt_upd['status']);
            $dt_upd += array('id_transaksi' => $id_transaksi);

            $notif_fcm = array(
                'body' => 'Pesananan anda sedang diproses',
                'title' => 'CNI',
                'badge' => '1',
                'sound' => 'Default'
            );
            $dt_insert_notif = array();
            $dt_insert_notif = array(
                'id' => $id_transaksi,
                'id_member' => $data->id_member,
                'content' => 'Pesananan anda sedang diproses',
                'type' => 1,
                'created_at' => $tgl,
                'created_by' => $id_operator
            );
            $id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
            $data_fcm = array(
                'id_notif' => $id_notif,
                'id' => $id_transaksi,
                'title' => 'CNI',
                'status' => 3,
                'message' => 'Pesananan anda sedang diproses',
                'type' => '1'
            );
            Helper::send_fcm($data->id_member, $data_fcm, $notif_fcm);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $dt_upd
            );
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Data not found',
                'data' => ''
            );
        }
        return response($result);
    }

    function set_cnote(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $cnote = isset($request->no_resi) ? $request->no_resi : '';
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $_where = array('transaksi.id_transaksi' => $id_transaksi);
        $result = array();
        if ($cnote == '' || $id_transaksi <= 0 || $id_operator <= 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'id transaksi, id_operator dan no.resi required',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'status' => 3, 'tipe_pengiriman' => 2);
        $cnt = DB::table('transaksi')->where($where)->count();
        if ((int)$cnt > 0) {
            $dt_upd = array();
            $dt_upd = array(
                'cnote_no' => $cnote,
                'status' => 4,
                'delivery_by' => $id_operator,
                'delivery_date' => $tgl
            );
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            $data = DB::table('transaksi')->select(
                'transaksi.*',
                'members.nama as nama_member',
                'members.email',
                'members.phone as phone_member'

            )->where($_where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
            $data_item = DB::table('transaksi_detail')->select('product_name', 'img', 'harga', 'jml')->where('id_trans', $id_transaksi)->get();
            unset($dt_upd['status']);
            $dt_upd += array('id_transaksi' => $id_transaksi);
            // $setting = DB::table('setting')->get()->toArray();
            // $out = array();
            // if (!empty($setting)) {
            // foreach ($setting as $val) {
            // $out[$val->setting_key] = $val->setting_val;
            // }
            // }
            // $content_email_dikirimkan_cust = $out['content_email_dikirimkan_cust'];
            // $content_email_hold_cust = str_replace('[#tgl_bayar#]', date('d-m-Y H:i', strtotime($data->payment_date)), $content_email_dikirimkan_cust);
            // $content_email_hold_cust = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_hold_cust);
            // $content_email_hold_cust = str_replace('[#payment_channel#]', $data->payment_name, $content_email_hold_cust);
            // $content_email_hold_cust = str_replace('[#ongkir#]', number_format($data->ongkir), $content_email_hold_cust);
            // $content_email_hold_cust = str_replace('[#pot_voucher#]', number_format($data->pot_voucher), $content_email_hold_cust);
            // $content_email_hold_cust = str_replace('[#ttl_bayar#]', number_format($data->ttl_price), $content_email_hold_cust);

            // $html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
            // <tbody>';
            // foreach ($data_item as $di) {
            // $html .= '<tr>';
            // $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
            // <img src="' . $di->img . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
            // $html .= '<td valign="top" style="padding:0 0 16px 16px">
            // <div style="margin:0 0 4px;line-height:16px">' . $di->product_name . '</div>
            // <p style="font-weight:bold;margin:4px 0 0">' . number_format($di->jml) . ' x
            // <span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($di->harga) . '</span>
            // </p>
            // </td>';
            // $html .= '</tr>';
            // }
            // $html .= '</tbody></table>';
            // $content_email_hold_cust = str_replace('[#detail_pesanan#]', $html, $content_email_hold_cust);
            // $data->content_email_hold_cust = $content_email_hold_cust;
            // $notif_fcm = array(
            // 'body'			=> 'Pesananan anda sudah dikirimkan',
            // 'title'			=> 'CNI',
            // 'badge'			=> '1',
            // 'sound'			=> 'Default'
            // );
            // $dt_insert_notif = array();
            // $dt_insert_notif = array(
            // 'id'			=> $id_transaksi,
            // 'id_member'		=> $data->id_member,
            // 'content'		=> 'Pesananan anda sudah dikirimkan',
            // 'type'			=> 1,
            // 'created_at'	=> $tgl,
            // 'created_by'	=> $id_operator
            // );
            // $id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
            // $data_fcm = array(
            // 'id_notif'		=> $id_notif,
            // 'id'			=> $id_transaksi,
            // 'title'			=> 'CNI',
            // 'status'		=> 4,
            // 'message' 		=> 'Pesananan anda sudah dikirimkan',
            // 'type' 			=> '1'
            // );
            // Helper::send_fcm($data->id_member, $data_fcm, $notif_fcm);
            // Mail::send([], ['users' => $data], function ($message) use ($data) {
            // $message->to($data->email, $data->nama_member)->subject('Transaksi Hold')->setBody($data->content_email_hold_cust, 'text/html');
            // });
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $dt_upd
            );
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Data not found',
                'data' => ''
            );
        }
        return response($result);
    }

    function set_stts(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $status = $request->has('status') && (int)$request->status > 0 ? (int)$request->status : 5;

        $result = array();
        if ($id_transaksi <= 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'id transaksi required',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'status' => 4);
        $cnt_details = DB::table('transaksi')->where($where)->count();
        if ($cnt_details > 0) {
            $dt_upd = array();
            $dt_upd = array(
                'status' => $status,
                'completed_at' => $tgl
            );
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            unset($dt_upd['status']);
            $dt_upd += array('id_transaksi' => $id_transaksi);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $dt_upd
            );
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Data not found',
                'data' => ''
            );
        }
        return response($result);
    }

    function set_hold(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('d-m-Y H:i');
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $remark = $request->has('remark') && !empty($request->remark) ? $request->remark : '-';
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $status = 95678;
        $result = array();
        if ($id_transaksi <= 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'id transaksi required',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'status' => 3);
        $_where = array('transaksi.id_transaksi' => $id_transaksi);
        $cnt = DB::table('transaksi')->where($where)->count();
        if ((int)$cnt > 0) {
            $dt_upd = array();
            $dt_upd = array(
                'status' => $status,
                'remark_hold' => $remark,
            );
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            $data = DB::table('transaksi')->select(
                'transaksi.*',
                'members.nama as nama_member',
                'members.email',
                'members.phone as phone_member'

            )->where($_where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
            $data_item = DB::table('transaksi_detail')->select('product_name', 'img', 'harga', 'jml')->where('id_trans', $id_transaksi)->get();
            unset($dt_upd['status']);
            $dt_upd += array('id_transaksi' => $id_transaksi);
            $setting = DB::table('setting')->get()->toArray();
            $out = array();
            if (!empty($setting)) {
                foreach ($setting as $val) {
                    $out[$val->setting_key] = $val->setting_val;
                }
            }
            $alamat_kirim = '-';
            if ((int)$data->tipe_pengiriman > 1) $alamat_kirim = $data->nama_penerima . ',' . $data->alamat . ',' . $data->city_name . ',' . $data->provinsi_name . ',' . $data->kode_pos . ',' . $data->phone_penerima;

            $list_emails = $out['hold_mail_admin'];
            $dt_email_to = !empty($list_emails) ? explode(',', $list_emails) : '';

            $content_email_hold_admin = $out['content_email_hold_admin'];
            $content_email_hold_admin = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#tgl_order#]', date('d-m-Y H:i', strtotime($data->created_at)), $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#nama#]', $data->nama_member, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#nomor_n#]', $data->cni_id, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#email#]', $data->email, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#no_hp#]', $data->phone_member, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#alamat_kirim#]', $alamat_kirim, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#keterangan#]', $data->remark_hold, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#tgl_hold#]', $_tgl, $content_email_hold_admin);
            $content_email_hold_admin = str_replace('[#warehouse#]', $data->wh_name, $content_email_hold_admin);

            $data->content_email_hold_cust = $content_email_hold_admin;
            $notif_fcm = array(
                'body' => 'Pesananan anda dihold',
                'title' => 'CNI',
                'badge' => '1',
                'sound' => 'Default'
            );
            $dt_insert_notif = array();
            $dt_insert_notif = array(
                'id' => $id_transaksi,
                'id_member' => $data->id_member,
                'content' => 'Pesananan anda dihold',
                'type' => 1,
                'created_at' => $tgl,
                'created_by' => $id_operator
            );
            $id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
            $data_fcm = array(
                'id_notif' => $id_notif,
                'id' => $id_transaksi,
                'title' => 'CNI',
                'status' => 95678,
                'message' => 'Pesananan anda dihold',
                'type' => '1'
            );
            Helper::send_fcm($data->id_member, $data_fcm, $notif_fcm);
            if (count($dt_email_to) > 0) {
                Mail::send([], ['users' => $data], function ($message) use ($data) {
                    $message->to($dt_email_to, "Admin CNI")->subject('Transaksi Hold')->setBody($data->content_email_hold_cust, 'text/html');
                });
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $dt_upd
            );
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Data not found',
                'data' => ''
            );
        }
        return response($result);
    }


    function tracking(Request $request)
    {
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $cnote = '';
        $type_logistic = '';
        $result = array();
        if ($id_transaksi <= 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'id_transaksi required',
                'data' => ''
            );
            return response($result);
            return false;
        }
        if ($id_transaksi > 0) {
            $where = array('transaksi.id_transaksi' => $id_transaksi);
            $transaksi = DB::table('transaksi')->where($where)->first();
            $type_logistic = !empty($transaksi) && $transaksi->type_logistic > 0 ? (int)$transaksi->type_logistic : 0;
            $cnote = !empty($transaksi) && $transaksi->cnote_no > 0 ? $transaksi->cnote_no : '';
        }
        //$cnote = "000491576002";
        if (empty($cnote)) {
            unset($transaksi->session_id);
            unset($transaksi->delivery_by);
            unset($transaksi->log_payment);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'CNOTE belum di update',
                'data' => $transaksi
            );
        } else {
            if ($type_logistic == 1) {
                // $postfields = array(
                // "username" => env('JNE_USERNAME'),
                // "api_key" => env('JNE_APIKEY')
                // );
                $postfields = array(
                    "waybill" => $cnote,
                    "courier" => "sicepat",
                );
                $url = env('URL_JNE');
                $curl = curl_init();
                // curl_setopt_array($curl, array(
                // CURLOPT_URL => $url . '/list/v1/cnote/'.$cnote,
                // CURLOPT_RETURNTRANSFER => true,
                // CURLOPT_ENCODING => '',
                // CURLOPT_MAXREDIRS => 10,
                // CURLOPT_TIMEOUT => 0,
                // CURLOPT_FOLLOWLOCATION => true,
                // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                // CURLOPT_CUSTOMREQUEST => 'POST',
                // CURLOPT_POSTFIELDS => http_build_query($postfields),
                // CURLOPT_HTTPHEADER => array(
                // 'Content-Type: application/x-www-form-urlencoded'
                // ),
                // ));
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://pro.rajaongkir.com/api/waybill',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => http_build_query($postfields),
                    CURLOPT_HTTPHEADER => array(
                        'key: e1100f5cdf345dbe73c540c0bf404d71',
                        'Content-Type: application/x-www-form-urlencoded'
                    ),
                ));
                $response = curl_exec($curl);
                curl_close($curl);
            }
            if ($type_logistic == 2) {
                $CLIENT_CODE = env('CLIENT_CODE');
                $url = "https://api-stg-middleware.lionparcel.com/v3/stt/detail?q=$cnote";
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: Basic bGlvbnBhcmNlbDpsaW9ucGFyY2VsQDEyMw=='
                    ),
                ));

                $response = curl_exec($curl);
                curl_close($curl);
            }
            $res = json_decode($response);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $res,
            );
        }
        return response($result);
    }

    function signon_qris(Request $request)
    {
        $result = array();
        $url_qris = env('URL_DOKU_QRIS');
        $clientSecret = env('CLIENTSECRET_DOKU_QRIS');
        $clientId = env('CLIENTID_DOKU_QRIS');
        $Sharedkey = env('SHAREDKEY_DOKU_QRIS');
        $systrace = $request->id_transaksi;
        $amount = $request->amount;
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
            'path' => $path,

        );
        return response($result);
    }

    function submit_ulasan(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_trans = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $rating = (int)$request->rating > 0 ? (int)$request->rating : 0;
        $ulasan = $request->has('ulasan') ? $request->ulasan : '';
        $path_img = $request->file("img");
        $result = array();
        if ($rating <= 0) {
            $result = array(
                'err_code' => '03',
                'err_msg' => 'Rating is required',
                'data' => ''
            );
            return response($result);
            return false;
        }
        if ($rating > 5) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'Nilai rating maksimal 5',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $where = array('id_transaksi' => $id_trans, 'status' => 5);
        $_data = DB::table('transaksi')->where($where)->count();

        if ((int)$_data <= 0) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Transaksi belum bisa direview',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('status_ulasan' => null, 'id_trans' => $id_trans, 'id_product' => $id_product);
        $data = DB::table('transaksi_detail')->where($where)->count();
        if ((int)$data <= 0) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Produk not found',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $dt_upd = array();
        $dt_upd = array(
            'rating' => $rating,
            'ulasan' => $ulasan,
            'tgl_ulasan' => $tgl,
            'status_ulasan' => 1,
        );
        if (!empty($path_img)) {
            $randomletter = substr(str_shuffle("CNIcni"), 0, 3);
            $nama_file = base64_encode($randomletter . "" . $id_trans . "_" . $id_product);
            $fileSize = $path_img->getSize();
            $extension = $path_img->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/ulasan';
            $_extension = array('png', 'jpg', 'jpeg');
            if ($fileSize > 2099200) { // satuan bytes
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file size over 2048',
                    'data' => $fileSize
                );
                return response($result);
                return false;
            }
            if (!in_array($extension, $_extension)) {
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file extension not valid',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $path_img->move($tujuan_upload, $imageName);
            $dt_upd += array("img_ulasan" => env('APP_URL') . '/api_cni/uploads/ulasan/' . $imageName);
        }
        DB::table('transaksi_detail')->where($where)->update($dt_upd);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $dt_upd
        );
        return response($result);
    }

    function approve_rej_ulasan(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_trans = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $status = (int)$request->status > 0 ? (int)$request->status : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $where = array();
        $where = array('status_ulasan' => 1, 'id_trans' => $id_trans, 'id_product' => $id_product);
        $data = DB::table('transaksi_detail')->where($where)->count();
        if ((int)$data <= 0) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Produk not found',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $dt_upd = array();
        $dt_upd = array(
            'tgl_status_ulasan' => $tgl,
            'status_ulasan_by' => $id_operator,
            'status_ulasan' => $status,
        );
        if ($status == 2) {
            $data_trans = DB::table('transaksi_detail')->select('rating')->where($where)->first();
            $_where = array('id_product' => $id_product);
            $data_product = DB::table('product')->select('cnt_ulasan', 'cnt_rating')->where($_where)->first();
            $rating = isset($data_trans) ? (int)$data_trans->rating : 0;
            $cnt_ulasan = isset($data_product) ? (int)$data_product->cnt_ulasan + 1 : 1;
            $cnt_rating = isset($data_product) ? (int)$data_product->cnt_rating + (int)$rating : $rating;
            $avg_rating = round((int)$cnt_rating / (int)$cnt_ulasan, 1);
            $avg_rating = strlen($avg_rating) == 1 ? $avg_rating . '.0' : $avg_rating;
            $upd_product = array(
                'cnt_ulasan' => $cnt_ulasan,
                'cnt_rating' => $cnt_rating,
                'avg_rating' => $avg_rating,
            );
            DB::table('product')->where($_where)->update($upd_product);
        }
        DB::table('transaksi_detail')->where($where)->update($dt_upd);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $dt_upd
        );
        return response($result);
    }

    function test_jokul(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $url_path_doku = env('URL_JOKUL');
        $clientId = env('CLIENT_ID_JOKUL');
        $secretKey = env('SECRET_KEY_JOKUL');
        $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));
        $payment_channel = (int)$request->payment_channel > 0 ? (int)$request->payment_channel : 0;
        $requestId = rand(1, 288888);
        $invoice_number = 'TEST-' . $requestId;
        $targetPath = '';
        if ($payment_channel == 29) {
            $targetPath = "/bca-virtual-account/v2/payment-code";
        }
        if ($payment_channel == 32) {
            $targetPath = "/cimb-virtual-account/v2/payment-code";
        }
        if ($payment_channel == 34) {
            $targetPath = "/bri-virtual-account/v2/payment-code";
        }
        if ($payment_channel == 36) {
            $targetPath = "/permata-virtual-account/v2/payment-code";
        }


        $amount = "180000";
        $requestBody = array(
            'order' => array(
                'amount' => $amount,
                'invoice_number' => $invoice_number,
            ),
            'virtual_account_info' => array(
                'expired_time' => 180,
                'reusable_status' => false,

            ),
            'customer' => array(
                'name' => "test name",
                'email' => "testmail@mail.com",
            ),
        );
        $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
        $componentSignature = "Client-Id:" . $clientId . "\n" .
            "Request-Id:" . $requestId . "\n" .
            "Request-Timestamp:" . $dateTimeFinal . "\n" .
            "Request-Target:" . $targetPath . "\n" .
            "Digest:" . $digestValue;
        $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
        $headers = array(
            'Content-Type: application/json',
            'Client-Id:' . $clientId,
            'Request-Id:' . $requestId,
            'Request-Timestamp:' . $dateTimeFinal,
            'finalsignature:HMACSHA256=' . $signature
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Send Error: ' . curl_error($ch));
        }

        curl_close($ch);

        $data_result = json_decode($result);
        $dt = $data_result->virtual_account_info;
        $res = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'requestId' => $requestId,
            'data' => $dt->virtual_account_number,
            'targetPath' => $targetPath
        );
        return response($res);
    }

    function test_jokul_cc(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $url_path_doku = env('URL_JOKUL');
        $test = (int)$request->test > 0 ? (int)$request->test : 0;
        $clientId = env('CLIENT_ID_JOKUL');
        $secretKey = env('SECRET_KEY_JOKUL');
        if ($test > 0) {
            $clientId = env('CLIENT_ID_JOKUL_TEST');
            $secretKey = env('SECRET_KEY_JOKUL_TEST');
        }
        $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));

        $requestId = rand(1, 288888);
        $invoice_number = 'TEST-CC-' . $requestId;
        $targetPath = "/checkout/v1/payment";

        $amount = "180000";
        $requestBody = array(
            'order' => array(
                'amount' => $amount,
                'invoice_number' => $invoice_number,
            ),
            "payment" => array(
                "payment_due_date" => 180,
            ),
            'customer' => array(
                'name' => "test name cni",
                'email' => "testmail@mail.com",
                "phone" => "6285694566147",
            ),
        );
        $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
        $componentSignature = "Client-Id:" . $clientId . "\n" .
            "Request-Id:" . $requestId . "\n" .
            "Request-Timestamp:" . $dateTimeFinal . "\n" .
            "Request-Target:" . $targetPath . "\n" .
            "Digest:" . $digestValue;
        $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
        $headers = array(
            'Content-Type: application/json',
            'Client-Id:' . $clientId,
            'Request-Id:' . $requestId,
            'Request-Timestamp:' . $dateTimeFinal,
            'Signature:HMACSHA256=' . $signature
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Send Error: ' . curl_error($ch));
        }

        curl_close($ch);

        $data_result = json_decode($result);

        $res = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'requestId' => $requestId,
            'headers' => $headers,
            'data' => $data_result,
            'targetPath' => $targetPath
        );
        return response($res);
    }
}
