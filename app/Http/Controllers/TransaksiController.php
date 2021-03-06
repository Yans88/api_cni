<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
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
        $sql = "select id_transaksi,ewallet,ttl_price, id_member,type_member from transaksi where status=0 and expired_payment::timestamp <= '" . $tgl . "'";
        $trans_expired = DB::select(DB::raw($sql));
		$whereIn = array();
		$dt_refund_ewallet = array();
		if(count($trans_expired) > 0){
            //update status ke expired payment
            foreach($trans_expired as $te){
                $whereIn[] = $te->id_transaksi;
				if((int)$te->ewallet > 0 && (int)$te->type_member == 1){
					$dt_refund_ewallet[] = array(
						'id_transaksi'	=> $te->id_transaksi,
						'ewallet'		=> $te->ewallet,
						'ttl_price'		=> $te->ttl_price,
						'id_member'		=> $te->id_member,
						'status'		=> 0,
						'created_at'	=> $tgl
					);
				}
            }
			DB::table('transaksi')->where(array("status" => 0))
                ->whereIn('id_transaksi', $whereIn)->update(array("status"=>2,"cek_refund_ewallet"	=> 1));
			if(!empty($dt_refund_ewallet)) DB::table('refund_ewallet')->insert($dt_refund_ewallet);
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
		if($id_level > 1 && $id_wh > 0){
			$where += array('transaksi.id_wh' => $id_wh);
		}
		if($id_wh > 0 || $id_level == 1){
			if (!empty($keyword)) {
				$_data = DB::table('transaksi')->select(
					'transaksi.*',
					'members.nama as nama_member',
					'members.email',
					'members.phone as phone_member',
					'members.cni_id'
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
					'members.phone as phone_member',
					'members.cni_id'
				)
					->where($where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
					->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
			}
		}
        $result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ($count > 0) {
            // foreach ($_data as $d) {
            // $data[] = $d;
            // }
            $result = array(
                'err_code'      => '00',
                'err_msg'          => 'ok',
                'total_data'    => $count,
                'data'          => $_data
            );
        }
        return response($result);
    }
	
	function list_ulasan(Request $request){
		$per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'tgl_ulasan';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status > 0 ? (int)$request->status : 1;
		$column_int = array("id_transaksi","rating");
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
		}else{
			$count = DB::table('transaksi_detail')->where($where)->count();
			$per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
			$_data = DB::table('transaksi_detail')->where($where)->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
		}
		 $result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ($count > 0) {
			foreach ($_data as $d) {
				$d->unique_key = base64_encode($d->id_trans.''.$d->id_product.''.$d->id_category);
				$data[] = $d;
            }
            $result = array(
                'err_code'      => '00',
                'err_msg'          => 'ok',
                'total_data'    => $count,
                'data'          => $data
            );
        }
        return response($result);
	}

    function store(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id_address = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $tipe_pengiriman = (int)$request->tipe_pengiriman > 0 ? (int)$request->tipe_pengiriman : 0;
        $type_logistic = (int)$request->type_logistic > 0 ? (int)$request->type_logistic : 1;
		$service_code = !empty($request->service_code) ? $request->service_code : '';
        $payment = (int)$request->payment > 0 ? (int)$request->payment : 0;
        
        $payment_channel = !empty($request->payment_channel) ? $request->payment_channel : '';
        $ongkir = $request->ongkir > 0 ? str_replace('.', '', $request->ongkir) : 0;
        $ongkir = str_replace(',', '', $request->ongkir);
        $ttl_weight = $request->ttl_weight > 0 ? str_replace('.', '', $request->ttl_weight) : 0;
        $ewallet = !empty($request->ewallet) ? (int)$request->ewallet : 0;
        $id_voucher = !empty($request->id_voucher) ? (int)$request->id_voucher : 0;
		
        $iddc = !empty($request->iddc) ? (int)$request->iddc : 0;		
        $namadc = !empty($request->namadc) ? $request->namadc : '';		
        $tipe_dc = !empty($request->tipe) ? $request->tipe : '';		
		$jneorigin = !empty($request->jneorigin) ? $request->jneorigin : '';
		
        $kode_voucher = '';
		$type_voucher = 0;
		$pot_voucher = 0;
		$is_bonus = 0;
		$is_limited = 0;
		$user_tertentu = 0;
		if($id_voucher > 0){
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
			if($type_voucher == 3){
				$pot_voucher = 0;
			}
		}
		$saldo_awal = $ewallet;
        $ttl_weight = str_replace(',', '', $ttl_weight);
        if ($tipe_pengiriman == 2) {
            
        }
        // if ($payment == 1) {
            // $result = array(
                // 'err_code'      => '02',
                // 'err_msg'       => 'onprocess, payment belum bisa digunakan',
                // 'data'          => null
            // );
            // return response($result);
            // return false;
        // }
        if ($id_member <= 0) {
            $result = array(
                'err_code'  => '02',
                'err_msg'   => 'id_member required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if ($tipe_pengiriman <= 0) {
            $result = array(
                'err_code'  => '02',
                'err_msg'   => 'tipe_pengiriman required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if ($tipe_pengiriman > 1 && $id_address <= 0) {
            $result = array(
                'err_code'  => '02',
                'err_msg'   => 'id_address required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $kode_origin = '';
		$data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
		$type = !empty($data_member) ? (int)$data_member->type : 0;
        $list_item = json_decode($request->list_item);
        
        $phone_member = !empty($data_member) ? $data_member->phone : '';
        
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
		
		if($tipe_pengiriman != 2){
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
		
		if($tipe_pengiriman == 1){
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
			
			if($type_voucher == 1){
				$id_voucher = "";
				$kode_voucher = "";
				$type_voucher = "";
				$pot_voucher = 0;
				$is_limited = 0;
			}
		}
		
        if (!empty($request->ewallet)) $payment_name .= "eWallet";
        if ($payment == 1)  $payment_name .= !empty($payment_name) ? " & Credit Card" : "Credit Card";
        if ($payment == 4)  $payment_name .= !empty($payment_name) ? " & Doku QRIS" : "Doku QRIS";
        if ($payment == 2) {
            $payment_channel_va[29] = array(
                'kode'          => 29,
                'payment_name'  => 'BCA',
                'prefix'        => 39355000
            );
            $payment_channel_va[32] = array(
                'kode'          => 32,
                'payment_name'  => 'CIMB Niaga',
                'prefix'        => 51491125
            );
            $payment_channel_va[33] = array(
                'kode'          => 33,
                'payment_name'  => 'Danamon',
                'prefix'        => 89220088
            );
            $payment_channel_va[34] = array(
                'kode'          => 34,
                'payment_name'  => 'BRIVA',
                'prefix'        => 45664000
            );
            $payment_channel_va[36] = array(
                'kode'          => 36,
                'payment_name'  => 'PERMATA',
                'prefix'        => 88560936
            );

            $_phone = !empty($phone_member) ? str_replace('62', '', $phone_member) : mt_rand(100000, 999999);
            $no_va = $_phone . '' . $id_member;
            $_paymentName = $payment_channel_va[$payment_channel]['payment_name'];
            $key_payment = $payment_channel_va[$payment_channel]['prefix'];
            $payment_name .= !empty($payment_name) ? " & " . $_paymentName : $_paymentName;
        }
        $expired_payment = date("Y-m-d H:i", strtotime('+24 hours', strtotime($tgl)));
		$status = 0;
        $dt_trans = array(
            "id_member"         => $id_member,
            "id_alamat"         => $id_address,
            "type_member"       => $type,
            "is_dropship"       => (int)$request->is_dropship > 0 ? 1 : 0,
            "tipe_pengiriman"   => $tipe_pengiriman,            
            "ewallet"           => $ewallet,
            "payment"           => $payment,
            "payment_channel"   => $payment_channel,
            "ttl_weight"        => $ttl_weight,
            "ongkir"            => $ongkir,
            "status"            => $status,
            "payment_name"      => $payment_name,            
            "expired_payment"   => $expired_payment,
            "created_at"        => $tgl,
            "id_wh"             => (int)$id_wh,
            "iddc"             => (int)$iddc,
            "wh_name"           => $wh_name,
            "id_prov_origin"    => (int)$id_prov_origin,
            "prov_origin"        => $prov_origin,
            "kode_origin"        => $kode_origin,
            "type_logistic"        => $type_logistic,
            "logistic_name"        => $logistic_name,
			"cek_refund_ewallet"	=> 0,
			"pot_voucher"			=> $pot_voucher,
			"kode_voucher"			=> $kode_voucher,			
			"tipe_dc"			=> $tipe_dc,
        );
		if($tipe_pengiriman != 1){
			$dt_trans += array(
				"service_code"      => $service_code,
				"label_alamat"      => $data_address->label_alamat,
				"nama_penerima"     => $data_address->nama_penerima,
				"phone_penerima"    => $data_address->phone_penerima,
				"id_provinsi"       => $data_address->id_provinsi,
				"id_city"           => $data_address->id_city,
				"id_kec"            => $data_address->id_kec,
				"alamat"            => $data_address->alamat,
				"kode_pos"          => $data_address->kode_pos,
				"provinsi_name"     => $data_address->provinsi_name,
				"kode_jne_prov"     => $data_address->kode_jne_prov,
				"kode_lp_prov"      => $data_address->kode_lp_prov,
				"city_name"         => $data_address->city_name,
				"kode_jne_city"     => $data_address->kode_jne_city,
				"kode_lp_city"      => $data_address->kode_lp_city,
				"kec_name"          => $data_address->kec_name,
				"kode_jne_kec"      => $data_address->kode_jne_kec,
				"kode_lp_kec"       => $data_address->kode_lp_kec,
			);
		}
		
		if($id_voucher > 0){
			$dt_trans += array(
				'id_voucher' 	=> $id_voucher,
				'kode_voucher'	=> $kode_voucher,
				'type_voucher'	=> $type_voucher
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
                    'id_product'    => $list_item[$i]->id_product,
                    'jml'           => $jml
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

            if (count($_data) > 0) {
                foreach ($_data as $dt) {
                    $harga = 0;
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
                    $harga =  $type == 1 ? $dt->harga_member : $dt->harga_konsumen;
                    $ttl_harga = $harga * (int)$dt_product[$dt->id_product]['jml'];
                    $sub_ttl += $ttl_harga;
                    $dt_insert[] = array(
                        "id_trans"      => $id_transaksi,
                        "kode_produk"    => !empty($dt->kode_produk) ? $dt->kode_produk : '-',
                        "id_product"    => $dt->id_product,
                        "jml"           => (int)$dt_product[$dt->id_product]['jml'],
                        "img"           => !empty($dt->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $dt->img : '',
                        "id_category"   => $dt->id_category,
                        "product_name"  => $dt->product_name,
                        "category_name" => $dt->category_name,
                        "special_promo" => (int)$dt->special_promo,
                        "berat"         => (int)$dt->berat,
                        "harga"         => $harga,
                        "ttl_harga"     => $ttl_harga,
                        "ttl_berat"     => (int)$dt->berat * (int)$dt_product[$dt->id_product]['jml'],
                        "id_pricelist"  => $dt->id_pricelist,
                        "harga_member"  => $dt->harga_member,
                        "harga_konsumen"    => $dt->harga_konsumen,
                        "start_date_price"  => $dt->start_date_price,
                        "end_date_price"  => $dt->end_date_price,
                        "pv"  => $dt->pv,
                        "rv"    => $dt->rv,
                        "hm_non_ppn"  => $dt->hm_non_ppn,
                        "hk_non_ppn"  => $dt->hk_non_ppn,
                        "ppn_hm"    => $dt->ppn_hm,
                        "ppn_hk"  => $dt->ppn_hk,
                        "is_bonus"  => 0
                    );
                    $upd_product[] = array(
                        "id_product"    => $dt->id_product,
                        "qty"           => (int)$dt->qty - (int)$dt_product[$dt->id_product]['jml']
                    );
                    if ((int)$dt->qty < (int)$dt_product[$dt->id_product]['jml'] && $tipe_pengiriman == 3) {
                        $err_stok[] = $dt;
                    }
                    if (!in_array($dt->id_product, $myPriceId)) {
                        $err_hrg[] = $dt;
                    }
                }
				if($type_voucher == 3){
					$pot_voucher = 0;
					$produk_utama = (int)$dt_voucher->produk_utama;
					$produk_bonus = (int)$dt_voucher->produk_bonus;
					$where = array();
					$where = array('product.deleted_at' => null, 'product.id_product' => $produk_bonus);
					$dt_produk_bonus = DB::table('product')->where($where)
					->leftJoin('category', 'category.id_category', '=', 'product.id_category')->first();
					$produk_bonus_name = isset($dt_produk_bonus) ? $dt_produk_bonus-> product_name: '';
					$produk_bonus_kode = isset($dt_produk_bonus) ? $dt_produk_bonus-> kode_produk: '';
					$is_bonus = $produk_utama;
					$dt_insert[] = array(
                        "id_trans"      => $id_transaksi,
                        "kode_produk"    => !empty($produk_bonus_kode) ? $produk_bonus_kode : '-',
                        "id_product"    => $produk_bonus,
                        "jml"           => 1,
                        "img"           => !empty($dt_produk_bonus->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $dt_produk_bonus->img : '',
                        "id_category"   => $dt_produk_bonus->id_category,
                        "product_name"  => $produk_bonus_name,
                        "category_name" => $dt_produk_bonus->category_name,
                        "special_promo" => 0,
                        "berat"         => (int)$dt_produk_bonus->berat,
                        "harga"         => 0,
                        "ttl_harga"     => 0,
                        "ttl_berat"     => (int)$dt_produk_bonus->berat,
                        "id_pricelist"  => 0,
                        "harga_member"  => 0,
                        "harga_konsumen"    => 0,
                        "start_date_price"  => null,
                        "end_date_price"  => null,
                        "pv"  => 0,
                        "rv"    => 0,
                        "hm_non_ppn"  => 0,
                        "hk_non_ppn"  => 0,
                        "ppn_hm"    => 0,
                        "ppn_hk"  => 0,
                        "is_bonus"  => $is_bonus
                    );
				}
            }
            if (count($err_hrg) > 0) {
                DB::rollback();
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                $result = array(
                    'err_code'  => '05',
                    'err_msg'   => 'Harga invalid',
                    'data'      => $err_hrg
                );
                return response($result);
                return false;
            }
            if (count($err_stok) > 0) {
                DB::rollback();
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                $result = array(
                    'err_code'  => '04',
                    'err_msg'   => 'Stok tidak cukup',
                    'data'      => $err_stok
                );
                return response($result);
                return false;
            }
			

            if ((int)$id_transaksi > 0) {
                if ($payment == 2) {
                    $no_va .= $id_transaksi;
                    $no_va = substr($no_va, -8);
                    $key_payment = $key_payment . '' . $no_va;
                }
				if ($payment == 1) {
					$randomletter = substr(str_shuffle("cniCNI"), 0, 3);
					$base64 = base64_encode ($randomletter."".$id_transaksi);
					$key_payment = env('APP_URL') . '/api_cni/index.php/payment_suite/'.$base64;
				}
				$_key_payment = str_replace([' ','/','&','.','_','-'],'',$key_payment);
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
				if($sub_ttl_ongkir_pot_voucher <= $ewallet){
					$randomString = '';
					$key_payment = '';
					$payment = 3;
					$status = 1;
					$action = "PAID_EWALLET";		
					$ewallet = $sub_ttl_ongkir_pot_voucher;
					$ket = "Pembayaran penuh transaksi #".$id_transaksi;
				}
				$data_ewallet = '';
				if($type == 1 && $ewallet > 0){
					$cni_id = !empty($data_member) && !empty($data_member->cni_id) ? $data_member->cni_id : '';
					if(!empty($cni_id)){
						$ket = !empty($ket) ? $ket : "Pembayaran sebagian transaksi #".$id_transaksi;
						$data_ewallet = Helper::trans_ewallet($action,$cni_id, $sub_ttl_ongkir_pot_voucher,$ewallet,$id_transaksi,$request->all(),"submit_transaksi",1,$ket,1,$id_member);
					}
					if(isset($data_ewallet['result']) && $data_ewallet['result'] != "Y"){
						DB::rollback();
						DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
						return response($data_ewallet);
						return false;
					}
				}
				
				if($payment == 4){	
					$length_id = strlen($id_transaksi);
					$length = 15 - (int)$length_id;
					$randomletter = substr(str_shuffle("cniCNIcniCNIcni"), 0, $length);
					$base64 = base64_encode ($randomletter."".$id_transaksi);
					$data_qris = Helper::generate_qris($base64, $nominal_doku);
					$key_payment = $data_qris['qrCode'];
					$key_payment = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl='.$key_payment;
				}
                $dt_upd = array();
                $dt_upd = array(
                    "session_id"    => $randomString,
                    "ttl_belanjaan" => $sub_ttl,
                    "sub_ttl"       => $sub_ttl_ongkir,
                    "pot_voucher"   => $pot_voucher,
                    "ttl_price"     => $sub_ttl_ongkir_pot_voucher,
                    "nominal_doku"  => (int)$nominal_doku > 0 ? $nominal_doku : 0,
                    "key_payment"   => $key_payment,
                    "payment" 		=> $payment,
					"status"		=> $status
                );
				
                DB::table('transaksi_detail')->insert($dt_insert);				
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
				if($payment == 4){	
					$path = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl='.$key_payment;
					unset($dt_upd['key_payment']);
					$dt_upd += array('key_payment'=>$path);
				}
                $dt_trans += $dt_upd;
                $dt_trans += array('id_transaksi' => $id_transaksi);
                $dt_trans += array('list_item' => $dt_insert);
				if((int)$is_limited > 0){
					$dt_voucher = array();
					$dt_voucher = array(
						'cnt_used' 	=> $cnt_used,
						'sisa' 		=> $sisa
					);
					
					DB::table('vouchers')->where('id_voucher', $id_voucher)->update($dt_voucher);
					
				}
				// DB::connection()->enableQueryLog();
				if($id_voucher > 0){
					$where_voucher_member = array(
						'id_voucher'	=> $id_voucher,
						'id_member'		=> $id_member
					);
					if($user_tertentu > 0){
						$where_voucher_member += array(
							"deleted_at" => null,
							"is_used"	 => null
						);
						DB::table('list_member_voucher')->where($where_voucher_member)->update(array('is_used'=>$id_transaksi,'updated_at'=>$tgl,'updated_by'=>-1));
						// Log::info(DB::getQueryLog());
					}else{
						$where_voucher_member += array(
							"created_at" 	=> $tgl, 
							"created_by" 	=> -1,
							"is_used"		=> $id_transaksi
						);
						DB::table('list_member_voucher')->insert($where_voucher_member);						
					}
					
				}				
                DB::commit();
				if($status == 1) Helper::send_order_cni($id_transaksi,'transaksi');
				if($tipe_pengiriman == 3) Helper::upd_stok($upd_product);
            } else {
                DB::rollback();
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
            }
        } catch (\Exception $e) {
			Log::info($e);
            DB::rollback();
            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
            // something went wrong
        }

        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $dt_trans
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
            'members.cni_id'
        )
            ->where($where)
            ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
        $cnt_details =  DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->count();
        $list_item = null;
        if ($cnt_details > 0) {
            $details =  DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
            foreach ($details as $d) {
                $list_item[] = $d;
            }
        }
        unset($_data->session_id);
        unset($_data->delivery_by);
        unset($_data->log_payment);
        $_data->list_item = $list_item;
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $_data
        );
        return response($result);
    }
	
	function set_onprocess(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;		
		$remark = $request->has('remark') && !empty($request->remark) ? $request->remark : '';
		if($id_operator <= 0 || $id_transaksi <= 0){
			$result = array(
				'err_code'      => '05',
				'err_msg'       => 'id transaksi dan id_operator required',
				'data'          => ''
			);
			return response($result);
			return false;
		}
        $where = array('transaksi.id_transaksi' => $id_transaksi);
		$cnt_details =  DB::table('transaksi')->where($where)->count();
		if($cnt_details > 0){
			$dt_upd  = array();
			$data = DB::table('transaksi')->where($where)->first();
			$dt_upd  = array(				
				'status'			=> 3,
				'remark_onprocess'	=> $remark,
				'onprocess_date'	=> $tgl,
				'onprocess_by'		=> $id_operator
			);
			DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
			unset($dt_upd['status']);
			$dt_upd +=array('id_transaksi'	=> $id_transaksi);
			
			$notif_fcm = array(
				'body'			=> 'Pesananan anda sedang diproses',
				'title'			=> 'CNI',
				'badge'			=> '1',
				'sound'			=> 'Default'
			);	
			$dt_insert_notif = array();
			$dt_insert_notif = array(
				'id'			=> $id_transaksi,
				'id_member'		=> $data->id_member,
				'content'		=> 'Pesananan anda sedang diproses',
				'type'			=> 1,
				'created_at'	=> $tgl,
				'created_by'	=> $id_operator
			);
			$id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
			$data_fcm = array(				
				'id_notif'		=> $id_notif,
				'id'			=> $id_transaksi,
				'title'			=> 'CNI',
				'status'		=> 3,	
				'message' 		=> 'Pesananan anda sedang diproses',
				'type' 			=> '1'
			);
			Helper::send_fcm($data->id_member,$data_fcm,$notif_fcm);
			$result = array(
				'err_code'      => '00',
				'err_msg'       => 'ok',
				'data'          => $dt_upd
			);
		}else{
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Data not found',
				'data'          => ''
			);
		}
		return response($result);
	}
	
	function set_cnote(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$cnote = isset($request->cnote) ? $request->cnote  : '';
		$_where = array('transaksi.id_transaksi' => $id_transaksi);
		$result = array();
		if($cnote == '' || $id_transaksi <= 0){
			$result = array(
				'err_code'      => '05',
				'err_msg'       => 'id transaksi dan cnote required',
				'data'          => ''
			);
			return response($result);
			return false;
		}
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'status'=>3);
		$cnt =  DB::table('transaksi')->where($where)->count();
		if((int)$cnt > 0){
			$dt_upd  = array();
			$dt_upd  = array(
				'cnote_no'		=> $cnote,
				'status'		=> 4,
				'delivery_date'	=> $tgl
			);
			DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
			$data = DB::table('transaksi')->select(
				'transaksi.*',
				'members.nama as nama_member',
				'members.email',
				'members.phone as phone_member',
				'members.cni_id'
			)->where($_where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
			$data_item = DB::table('transaksi_detail')->select('product_name','img','harga','jml')->where('id_trans', $id_transaksi)->get();
			unset($dt_upd['status']);
			$dt_upd +=array('id_transaksi'	=> $id_transaksi);
			$setting = DB::table('setting')->get()->toArray();
            $out = array();
            if (!empty($setting)) {
                foreach ($setting as $val) {
                    $out[$val->setting_key] = $val->setting_val;
                }
            }
			$content_email_dikirimkan_cust = $out['content_email_dikirimkan_cust'];
			$content_email_hold_cust = str_replace('[#tgl_bayar#]', date('d-m-Y H:i', strtotime($data->payment_date)), $content_email_dikirimkan_cust);
			$content_email_hold_cust = str_replace('[#no_transaksi#]', $id_transaksi, $content_email_hold_cust);
			$content_email_hold_cust = str_replace('[#payment_channel#]', $data->payment_name, $content_email_hold_cust);
			$content_email_hold_cust = str_replace('[#ongkir#]', number_format($data->ongkir), $content_email_hold_cust);
			$content_email_hold_cust = str_replace('[#pot_voucher#]', number_format($data->pot_voucher), $content_email_hold_cust);
			$content_email_hold_cust = str_replace('[#ttl_bayar#]', number_format($data->ttl_price), $content_email_hold_cust); 
			$html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
                        <tbody>';
			foreach($data_item as $di){
				$html .='<tr>';
				$html .='<td valign="top" width="64" style="padding:0 0 16px 0">
				<img src="'.$di->img.'" width="64" style="border-radius:8px" class="CToWUd"></td>';
				$html .='<td valign="top" style="padding:0 0 16px 16px">
                            <div style="margin:0 0 4px;line-height:16px">'.$di->product_name.'</div>
                            <p style="font-weight:bold;margin:4px 0 0">'.number_format($di->jml).' x 
                                <span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. '.number_format($di->harga).'</span>
                            </p>
                        </td>';
				$html .='</tr>';
			}
			$html .='</tbody></table>';
			$content_email_hold_cust = str_replace('[#detail_pesanan#]', $html, $content_email_hold_cust);
			$data->content_email_hold_cust = $content_email_hold_cust;
			$notif_fcm = array(
				'body'			=> 'Pesananan anda sudah dikirimkan',
				'title'			=> 'CNI',
				'badge'			=> '1',
				'sound'			=> 'Default'
			);	
			$dt_insert_notif = array();
			$dt_insert_notif = array(
				'id'			=> $id_transaksi,
				'id_member'		=> $data->id_member,
				'content'		=> 'Pesananan anda sudah dikirimkan',
				'type'			=> 1,
				'created_at'	=> $tgl,
				'created_by'	=> $id_operator
			);
			$id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
			$data_fcm = array(				
				//'id_notif'		=> $id_notif,
				'id'			=> $id_transaksi,
				'title'			=> 'CNI',
				'status'		=> 4,	
				'message' 		=> 'Pesananan anda sudah dikirimkan',
				'type' 			=> '1'
			);
			Helper::send_fcm($data->id_member,$data_fcm,$notif_fcm);
			Mail::send([], ['users' => $data], function ($message) use ($data) {
                $message->to($data->email, $data->nama_member)->subject('Transaksi Hold')->setBody($data->content_email_hold_cust, 'text/html');
            });
			$result = array(
				'err_code'      => '00',
				'err_msg'       => 'ok',
				'data'          => $dt_upd
			);
		}else{
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Data not found',
				'data'          => ''
			);
		}
		return response($result);
	}
	
	function set_stts(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$status = $request->has('status') && (int)$request->status > 0 ? (int)$request->status : 5;
		
		$result = array();
		if($id_transaksi <= 0){
			$result = array(
				'err_code'      => '05',
				'err_msg'       => 'id transaksi required',
				'data'          => ''
			);
			return response($result);
			return false;
		}
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'status'=>4);
		$cnt_details =  DB::table('transaksi')->where($where)->count();
		if($cnt_details > 0){
			$dt_upd  = array();
			$dt_upd  = array(				
				'status'		=> $status,
				'completed_at'	=> $tgl
			);
			DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
			unset($dt_upd['status']);
			$dt_upd +=array('id_transaksi'	=> $id_transaksi);
			$result = array(
				'err_code'      => '00',
				'err_msg'       => 'ok',
				'data'          => $dt_upd
			);
		}else{
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Data not found',
				'data'          => ''
			);
		}
		return response($result);
	}
	
	function set_hold(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$_tgl = date('d-m-Y H:i');
		$id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$remark = $request->has('remark') && !empty($request->remark) ? $request->remark : '-';
		$status = 95678;
		$result = array();
		if($id_transaksi <= 0){
			$result = array(
				'err_code'      => '05',
				'err_msg'       => 'id transaksi required',
				'data'          => ''
			);
			return response($result);
			return false;
		}
        $where = array('transaksi.id_transaksi' => $id_transaksi,'status'=>3);
        $_where = array('transaksi.id_transaksi' => $id_transaksi);
		$cnt =  DB::table('transaksi')->where($where)->count();
		if((int)$cnt > 0){
			$dt_upd  = array();
			$dt_upd  = array(				
				'status'		=> $status,
				'remark_hold'	=> $remark,
			);
			DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
			$data = DB::table('transaksi')->select(
				'transaksi.*',
				'members.nama as nama_member',
				'members.email',
				'members.phone as phone_member',
				'members.cni_id'
			)->where($_where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
			$data_item = DB::table('transaksi_detail')->select('product_name','img','harga','jml')->where('id_trans', $id_transaksi)->get();
			unset($dt_upd['status']);
			$dt_upd +=array('id_transaksi'	=> $id_transaksi);
			$setting = DB::table('setting')->get()->toArray();
            $out = array();
            if (!empty($setting)) {
                foreach ($setting as $val) {
                    $out[$val->setting_key] = $val->setting_val;
                }
            }
			$alamat_kirim = '-';
			if((int)$data->tipe_pengiriman > 1) $alamat_kirim = $data->nama_penerima.','.$data->alamat.','.$data->city_name.','.$data->provinsi_name.','.$data->kode_pos.','.$data->phone_penerima;
			
			$list_emails =  $out['hold_mail_admin'];
			$dt_email_to = !empty($list_emails) ? explode(',',$list_emails) : '';
			
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
				'body'			=> 'Pesananan anda dihold',
				'title'			=> 'CNI',
				'badge'			=> '1',
				'sound'			=> 'Default'
			);	
			$dt_insert_notif = array();
			$dt_insert_notif = array(
				'id'			=> $id_transaksi,
				'id_member'		=> $data->id_member,
				'content'		=> 'Pesananan anda dihold',
				'type'			=> 1,
				'created_at'	=> $tgl,
				'created_by'	=> $id_operator
			);
			$id_notif = DB::table('history_notif')->insertGetId($dt_insert_notif, "id_notif");
			$data_fcm = array(				
				'id_notif'		=> $id_notif,
				'id'			=> $id_transaksi,
				'title'			=> 'CNI',
				'status'		=> 95678,	
				'message' 		=> 'Pesananan anda dihold',
				'type' 			=> '1'
			);
			Helper::send_fcm($data->id_member,$data_fcm,$notif_fcm);
			if(count($dt_email_to) > 0){
				Mail::send([], ['users' => $data], function ($message) use ($data) {
					$message->to($dt_email_to, "Admin CNI")->subject('Transaksi Hold')->setBody($data->content_email_hold_cust, 'text/html');
				});
			}
			$result = array(
				'err_code'      => '00',
				'err_msg'       => 'ok',
				'data'          => $dt_upd
			);
		}else{
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Data not found',
				'data'          => ''
			);
		}
		return response($result);
	}
	
		
	function tracking(Request $request){
		$id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$cnote = '';
		$type_logistic = '';
		$result = array();
		if($id_transaksi <= 0){
			$result = array(
				'err_code'      => '05',
				'err_msg'       => 'id_transaksi required',
				'data'          => ''
			);
			return response($result);
			return false;
		}
		if($id_transaksi > 0){
			$where = array('transaksi.id_transaksi' => $id_transaksi);
			$transaksi =  DB::table('transaksi')->where($where)->first();
			$type_logistic = !empty($transaksi) && $transaksi->type_logistic > 0 ? (int)$transaksi->type_logistic : 0;
			$cnote = !empty($transaksi) && $transaksi->cnote_no > 0 ? $transaksi->cnote_no : '';
		}
		//$cnote = "000491576002";
		if(empty($cnote)){
			unset($transaksi->session_id);
			unset($transaksi->delivery_by);
			unset($transaksi->log_payment);
			$result = array(
				'err_code'      => '00',
				'err_msg'       => 'CNOTE belum di update',
				'data'          => $transaksi
			);
		}else{
			//if($type_logistic == 1){
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
				$res =  json_decode($response);
				$result = array(
					'err_code'      => '00',
					'err_msg'       => 'ok',
					'data'          => $res,
								
				);				
			//}
		}
		return response($result);
	}
	
	function signon_qris(Request $request){
		$result = array();
		$url_qris = env('URL_DOKU_QRIS');
		$clientSecret = env('CLIENTSECRET_DOKU_QRIS');
		$clientId = env('CLIENTID_DOKU_QRIS');
		$Sharedkey = env('SHAREDKEY_DOKU_QRIS');
		$systrace = $request->id_transaksi;
		$amount = $request->amount;
		$version = env('VERSION_SIGNON_DOKU_QRIS');
		$words = '';
		$words_ori_signon = $clientId . '' . $Sharedkey .''. $systrace;
		$words = hash_hmac('sha1', $words_ori_signon,$clientSecret);
		$postfields = array();
		$postfields = array(
            "clientSecret" 	=> $clientSecret,
            "clientId" 		=> $clientId,
            "systrace"      => $systrace,
            "words"      	=> $words,
            "version"    	=> $version
        );
		$curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url_qris.'/signon',
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
        $res =  json_decode($response);
		if((int)$res->responseCode > 0){
			$result = array(
				'err_code'      => $res->responseCode,
				'err_msg'       => $res->responseMessage->id,
				'data'          => $res,
									
			);
			return response($result);
			return false;
		}
		$words = '';
		$accessToken = isset($res->accessToken) ? $res->accessToken : '';
		$words_generate = $clientId .''. $systrace.''.$clientId.''.$Sharedkey;
		$words = hash_hmac('sha1', $words_generate,$clientSecret);
		$id_transaksi = $systrace;
		$length_id = strlen($id_transaksi);
		if($length_id < 15){
			$id_transaksi = '00000000000000'.$systrace;
			$id_transaksi = substr($id_transaksi, -15);
		}
		$postfields = array();
		$postfields = array(           
            "clientId" 		=> $clientId,
            "accessToken" 	=> $accessToken,
            "dpMallId"      => $clientId,
            "words"      	=> $words,
            "version"    	=> env('VERSION_GENERATE_DOKU_QRIS'),
            "terminalId"    => env('TERMINALID_DOKU_QRIS'),
            "amount"    	=> $amount,
            "postalCode"    => env('POSTALCODE_DOKU_QRIS'),
			"transactionId" => $id_transaksi,
			"feeType"		=> 1
        );
		$response = '';
		$res = array();
		$result = array();
		$curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url_qris.'/generateQrAspi',
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
        $res =  json_decode($response);
		$qrCode = $res->qrCode;
		$path = 'http://chart.googleapis.com/chart?chs=300x300&cht=qr&chld=Q|3&chl='.$qrCode;
		$result = array(
			'err_code'      => '00',
			'err_msg'       => 'ok',
			'data'          => $res,
			'path'          => $path,
									
		);
		return response($result);
	}
	
	function submit_ulasan(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$id_trans = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
		$rating = (int)$request->rating > 0 ? (int)$request->rating : 0;
		$ulasan = $request->has('ulasan') ? $request->ulasan : '';
		$path_img = $request->file("img");
		$result = array();	
		if($rating <= 0){
			$result = array(
				'err_code'      => '03',
				'err_msg'       => 'Rating is required',
				'data'          => ''
			);
			return response($result);
			return false;
		}
		if($rating > 5){
			$result = array(
				'err_code'      => '05',
				'err_msg'       => 'Nilai rating maksimal 5',
				'data'          => ''
			);
			return response($result);
			return false;
		}
        $where = array('id_transaksi' => $id_trans,'status'=>5);
        $_data = DB::table('transaksi')->where($where)->count();
			
		if((int)$_data <= 0){
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Transaksi belum bisa direview',
				'data'          => ''
			);
			return response($result);
			return false;
		}
		$where = array();
		$where = array('status_ulasan' => null,'id_trans'=>$id_trans,'id_product'=>$id_product);
		$data = DB::table('transaksi_detail')->where($where)->count();
		if((int)$data <= 0){
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Produk not found',
				'data'          => ''
			);
			return response($result);
			return false;
		}
		$dt_upd = array();
		$dt_upd = array(
			'rating'		=> $rating,
			'ulasan'		=> $ulasan,
			'tgl_ulasan'	=> $tgl,
			'status_ulasan'	=> 1,
		);
		if (!empty($path_img)) {
			$randomletter = substr(str_shuffle("CNIcni"), 0, 3);
			$nama_file = base64_encode($randomletter."".$id_trans."_".$id_product);            
            $fileSize = $path_img->getSize();
            $extension = $path_img->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/ulasan';
            $_extension = array('png', 'jpg', 'jpeg');
            if ($fileSize > 2099200) { // satuan bytes
                $result = array(
                    'err_code'  => '07',
                    'err_msg'   => 'file size over 2048',
                    'data'      => $fileSize
                );
                return response($result);
                return false;
            }
            if (!in_array($extension, $_extension)) {
                $result = array(
                    'err_code'  => '07',
                    'err_msg'   => 'file extension not valid',
                    'data'      => null
                );
                return response($result);
                return false;
            }
            $path_img->move($tujuan_upload, $imageName);
            $dt_upd += array("img_ulasan" => env('APP_URL') . '/api_cni/uploads/ulasan/' .$imageName);
        }
		DB::table('transaksi_detail')->where($where)->update($dt_upd);
		$result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $dt_upd
        );
		return response($result);
	}
	
	function approve_rej_ulasan(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$id_trans = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
		$id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
		$status = (int)$request->status > 0 ? (int)$request->status : 0;
		$id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
		$where = array();
		$where = array('status_ulasan' => 1,'id_trans'=>$id_trans,'id_product'=>$id_product);
		$data = DB::table('transaksi_detail')->where($where)->count();
		if((int)$data <= 0){
			$result = array(
				'err_code'      => '04',
				'err_msg'       => 'Produk not found',
				'data'          => ''
			);
			return response($result);
			return false;
		}
		$dt_upd = array();
		$dt_upd = array(			
			'tgl_status_ulasan'	=> $tgl,
			'status_ulasan_by'	=> $id_operator,
			'status_ulasan'		=> $status,
		);
		if($status == 2){
			$data_trans = DB::table('transaksi_detail')->select('rating')->where($where)->first();
			$_where = array('id_product' => $id_product);
			$data_product = DB::table('product')->select('cnt_ulasan','cnt_rating')->where($_where)->first();
			$rating = isset($data_trans) ? (int)$data_trans->rating : 0;
			$cnt_ulasan = isset($data_product) ? (int)$data_product->cnt_ulasan + 1 : 1;
			$cnt_rating = isset($data_product) ? (int)$data_product->cnt_rating + (int)$rating : $rating;
			$avg_rating = round((int)$cnt_rating / (int)$cnt_ulasan,1);
			$avg_rating = strlen($avg_rating) == 1 ? $avg_rating.'.0' : $avg_rating;
			$upd_product = array(
				'cnt_ulasan'	=> $cnt_ulasan,
				'cnt_rating'	=> $cnt_rating,
				'avg_rating'	=> $avg_rating,
			);
			DB::table('product')->where($_where)->update($upd_product);
		}
		DB::table('transaksi_detail')->where($where)->update($dt_upd);
		$result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $dt_upd
        );
		return response($result);
	}
	
}
