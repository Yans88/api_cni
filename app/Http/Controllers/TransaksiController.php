<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status > 0 ? (int)$request->status : -1;
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = array();
        $where = $status >= 0 ? array('transaksi.status'=>$status) : array();
        $count = 0;
        $_data = array();
        $data = null;
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
        $result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
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
        $payment = (int)$request->payment > 0 ? (int)$request->payment : 0;
        $payment_channel = !empty($request->payment_channel) ? $request->payment_channel : '';
        $ongkir = $request->ongkir > 0 ? str_replace('.', '', $request->ongkir) : 0;
        $ongkir = str_replace(',', '', $request->ongkir);
        $ttl_weight = $request->ttl_weight > 0 ? str_replace('.', '', $request->ttl_weight) : 0;
        $ewallet = !empty($request->ewallet) ? $request->ewallet : 0;
        $ttl_weight = str_replace(',', '', $ttl_weight);
		if($tipe_pengiriman == 2){
			$result = array(
                'err_code'      => '02',
                'err_msg'       => 'onprocess, tipe_pengiriman belum bisa digunakan',
                'data'          => null
            );
            return response($result);
            return false;	
		}
		if($payment == 1){
			$result = array(
                'err_code'      => '02',
                'err_msg'       => 'onprocess, payment belum bisa digunakan',
                'data'          => null
            );
            return response($result);
            return false;	
		}
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
       
        $list_item = json_decode($request->list_item);
        $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
        $phone_member = $data_member->phone;
        $type = !empty($data_member) ? (int)$data_member->type : 0;
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
		$id_wh = (int)$data_address->id_wh > 0 ? (int)$data_address->id_wh : 0;
		$wh_name = (int)$data_address->id_wh ? $data_address->wh_name : 0;
		$id_prov_origin = (int)$data_address->id_prov_origin ? (int)$data_address->id_prov_origin : 0;

		$data_origin = DB::table('provinsi')->where(array('id_provinsi'=>$id_prov_origin))->first();
		$prov_origin = $data_origin->provinsi_name;
		if($type_logistic == 1){
			$logistic_name = "JNE";
			$kode_origin = $data_origin->kode_jne;
		}
		if($type_logistic == 2){
			$logistic_name = "Lion Parcel";
			$kode_origin = $data_origin->kode_lp;
		}
			
        if (!empty($request->ewallet)) $payment_name .= "eWallet";
        if ($payment == 1)  $payment_name .= !empty($payment_name) ? " & Credit Card" : "Credit Card";
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
        $expired_payment = date("Y-m-d H:i", strtotime('+6 hours', strtotime($tgl)));
        $dt_trans = array(
            "id_member"         => $id_member,
            "id_alamat"         => $id_address,
            "type_member"       => $type,
            "is_dropship"       => (int)$request->is_dropship > 0 ? 1 : 0,
            "tipe_pengiriman"   => $tipe_pengiriman,
            "service_code"      => !empty($request->service_code) ? $request->service_code : '',
            "ewallet"           => $ewallet,
            "payment"           => $payment,
            "payment_channel"   => $payment_channel,
            "ttl_weight"        => $ttl_weight,
            "ongkir"            => $ongkir,
            "status"            => 0,
            "payment_name"      => $payment_name,
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
            "expired_payment"   => $expired_payment,
            "created_at"        => $tgl,
			"id_wh"				=> (int)$id_wh,
			"wh_name"			=> $wh_name,
			"id_prov_origin"	=> (int)$id_prov_origin,
			"prov_origin"		=> $prov_origin,
			"kode_origin"		=> $kode_origin,
			"type_logistic"		=> $type_logistic,			
			"logistic_name"		=> $logistic_name,			
        );
		$id_transaksi = 0;
        $id_transaksi = DB::table('transaksi')->insertGetId($dt_trans, "id_transaksi");
		$whereIn = array();
        DB::beginTransaction();
        try {
            for ($i = 0; $i < count($list_item); $i++) {
                $whereIn[] = $list_item[$i]->id_product;
                $jml = $list_item[$i]->jml;
                $dt_product[$list_item[$i]->id_product] = array(
                    'id_product'    => $list_item[$i]->id_product,
                    'jml'           => $jml
                );
            }
            $err_stok = array();
            $upd_product = array();
            $where = array('product.deleted_at' => null);
            $_data = DB::table('product')->select('product.*', 'category_name')
                ->whereIn('id_product', $whereIn)
                ->leftJoin('category', 'category.id_category', '=', 'product.id_category')->where($where)->get();
            $sub_ttl = 0;
            
            if (count($_data) > 0) {
                foreach ($_data as $dt) {
                    $harga = 0;
                    $harga =  $type == 1 ? $dt->harga_member : $dt->harga_konsumen;
                    $ttl_harga = $harga * (int)$dt_product[$dt->id_product]['jml'];
                    $sub_ttl += $ttl_harga;
                    $dt_insert[] = array(
                        "id_trans"      => $id_transaksi,
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
                        "ttl_berat"     => (int)$dt->berat * (int)$dt_product[$dt->id_product]['jml']
                    );
                    $upd_product[] = array(
                        "id_product"    => $dt->id_product,
                        "qty"           => (int)$dt->qty - (int)$dt_product[$dt->id_product]['jml']
                    );
                    if ((int)$dt->qty < (int)$dt_product[$dt->id_product]['jml']) {
                        $err_stok[] = $dt;
                    }
                }
            }
            if (count($err_stok) > 0) {				
                DB::rollback();
				DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
                $result = array(
                    'err_code'  => '00',
                    'err_msg'   => 'stok tidak cukup',
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
                $words = '234678' . $sub_ttl . 'NRd509eQng1F' . $key_payment . 'ABCDEGHIJKLMOPSTUVWXYZ' . 'abcfhijklmopqrstuvwxyz';
                $charactersLength = strlen($words);
                $randomString = '';
                for ($i = 0; $i < 32; $i++) {
                    $randomString .= $words[rand(0, $charactersLength - 1)];
                }

                $dt_upd = array();
                $dt_upd = array(
                    "session_id"    => $randomString,
                    "ttl_belanjaan" => $sub_ttl,
                    "sub_ttl"       => $sub_ttl + $ongkir,
                    "pot_voucher"   => 0,
                    "ttl_price"     => $sub_ttl + $ongkir,
                    "nominal_doku"  => ($sub_ttl + $ongkir) - $ewallet,
                    "key_payment"   => $key_payment,
                );
                DB::table('transaksi_detail')->insert($dt_insert);
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
                $dt_trans += $dt_upd;
                $dt_trans += array('id_transaksi' => $id_transaksi);
                $dt_trans += array('list_item' => $dt_insert);
                DB::commit();
                Helper::upd_stok($upd_product);
            } else {
                DB::rollback();
				DB::table('transaksi')->where('id_transaksi', $id_transaksi)->delete();
            }
        } catch (\Exception $e) {
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
        $_data->list_item = $list_item;
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $_data
        );
        return response($result);
    }
}
