<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class MasterController extends Controller
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
        $cms = (int)$request->cms > 0 ? (int)$request->cms : 0;
        $setting = DB::table('setting')->get()->toArray();
        $out = array();
        if (!empty($setting)) {
            foreach ($setting as $val) {
                $out[$val->setting_key] = $val->setting_val;
            }
        }
        if ($cms == 0) {
            unset($out['mail_pass']);
            unset($out['send_mail']);
            unset($out['content_reg']);
            unset($out['content_forgotPass']);
            unset($out['content_forgotPass']);
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $out
        );
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        return response($result);
    }

    function get_ongkirs(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $_r = array();
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $tipe_pengiriman = (int)$request->tipe_pengiriman > 0 ? (int)$request->tipe_pengiriman : 0;
        $weight = (int)$request->weight > 0 ? (int)$request->weight / 1000 : 1;
        $origin = !empty($request->origin) ? strtoupper($request->origin) : '';
        if ($weight < 1) $weight = 1;
        if ((int)$id <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_address required',
                'data' => null
            );
            return response($result);
            return false;
        }
        // if($tipe_pengiriman == 2){
        // $result = array(
        // 'err_code'      => '02',
        // 'err_msg'       => 'onprocess, tipe_pengiriman belum bisa digunakan',
        // 'data'          => null
        // );
        // return response($result);
        // return false;
        // }

        if ($tipe_pengiriman == 2 && (empty($origin) || $origin == '')) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'origin required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array(
            'address_member.deleted_at' => null,
            'provinsi.deleted_at' => null,
            'city.deleted_at' => null,
            'kecamatan.deleted_at' => null,
            'address_member.id_address' => (int)$id
        );
        $_data = DB::table('address_member')->where($where)
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
        if (empty($_data->kode_jne_kec)) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'data not found',
                'data' => null
            );
            return response($result);
            return false;
        }

        unset($_data->created_at);
        unset($_data->updated_at);
        unset($_data->deleted_at);

        if ($tipe_pengiriman != 2) {
            $id_prov_origin = (int)$_data->id_prov_origin ? (int)$_data->id_prov_origin : 0;

            if ($id_prov_origin <= 0 && $tipe_pengiriman != 2) {
                $result = array(
                    'err_code' => '08',
                    'err_msg' => 'data alamat invalid, belum dimapping',
                    'data' => null
                );
                return response($result);
                return false;
            }

            $data_origin = DB::table('provinsi')->where(array('id_provinsi' => $id_prov_origin))->first();

            $_from_jne = strtoupper($data_origin->kode_jne);
            $_from_lp = strtoupper($data_origin->kode_lp);
        }

        if ($tipe_pengiriman == 2) {
            $_from_jne = $origin;
        }
        $postfields = array(
            "username" => env('JNE_USERNAME'),
            "api_key" => env('JNE_APIKEY'),
            "from" => $_from_jne,
            "thru" => $_data->kode_jne_kec,
            "weight" => "$weight",
        );


        $url = env('URL_JNE');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url . '/pricedev',
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
        $layanan = array();
        if (!empty($res) && !empty($res->price)) {
            $_res = $res->price;
            $origin_name = $_res[0]->origin_name;
            $destination_name = $_res[0]->destination_name;
            foreach ($_res as $r) {
                if ($r->service_code == "CTC19" || $r->service_code == "REG19") {
                    $_r[] = array(
                        "service_display" => $r->service_display,
                        "service_code" => $r->service_code,
                        "price" => $r->price,
                        "etd" => $r->etd_from . '-' . $r->etd_thru,
                        "times" => $r->times
                    );
                }
            }
            //$layanan += $_r;
            $res_ongkir['jne'] = array(
                "origin_name" => $origin_name,
                "destination_name" => $destination_name,
                "type_logistic" => 1,
                "logistic_name" => "JNE",
                "layanan" => $_r
            );
        } else {
            $res_ongkir['jne'] = $res;
        }

        $post_data_jne = array(
            "from" => $_from_jne,
            "thru" => $_data->kode_jne_kec,
            "weight" => $weight
        );
        $dt_log = array(
            "api_name" => "get_ongkirs",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($post_data_jne),
            "endpoint" => $url . '/pricedev',
            "responses" => serialize($res),
            "id_transaksi" => $id,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $_r,
            "data_alamat" => $_data,
            'res_ongkir' => $res_ongkir,
            'post_data_jne' => $post_data_jne,

        );
        return response($result);
    }

    function get_ongkir_lp(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $_r = array();
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $tipe_pengiriman = (int)$request->tipe_pengiriman > 0 ? (int)$request->tipe_pengiriman : 3;
        $weight = (int)$request->weight > 0 ? (int)$request->weight / 1000 : 1;
        $origin = !empty($request->origin) ? strtoupper($request->origin) : '';
        if ($weight < 1) $weight = 1;
        if ((int)$id <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_address required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($tipe_pengiriman < 3) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'tipe_pengiriman tidak sesuai',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($tipe_pengiriman == 2) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'onprocess, tipe_pengiriman belum bisa digunakan',
                'data' => null
            );
            return response($result);
            return false;
        }

        if ($tipe_pengiriman == 2 && (empty($origin) || $origin == '')) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'origin required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array(
            'address_member.deleted_at' => null,
            'provinsi.deleted_at' => null,
            'city.deleted_at' => null,
            'kecamatan.deleted_at' => null,
            'address_member.id_address' => (int)$id
        );
        $_data = DB::table('address_member')->where($where)
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
        if (empty($_data->kode_lp_prov)) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'data not found',
                'data' => null
            );
            return response($result);
            return false;
        }

        unset($_data->created_at);
        unset($_data->updated_at);
        unset($_data->deleted_at);

        $id_wh = (int)$_data->id_wh > 0 ? (int)$_data->id_wh : 0;
        $wh_name = (int)$_data->id_wh ? $_data->wh_name : 0;
        $id_prov_origin = (int)$_data->id_prov_origin ? (int)$_data->id_prov_origin : 0;

        if ($id_prov_origin <= 0 && $tipe_pengiriman != 2) {
            $result = array(
                'err_code' => '08',
                'err_msg' => 'data alamat invalid, belum dimapping',
                'data' => null
            );
            return response($result);
            return false;
        }

        $data_origin = DB::table('provinsi')->where(array('id_provinsi' => $id_prov_origin))->first();
        $_from_lp = strtoupper($data_origin->kode_lp);
        $destination = strtoupper($_data->kode_lp_kec);

        $post_data_lp = array(
            "Origin" => $_from_lp,
            "Destination" => $destination,
            "Weight" => $weight
        );

        $curl = curl_init();
        $url = 'https://api-middleware.lionparcel.com/v3/tariffv3?origin=' . $_from_lp . '&destination=' . $destination . '&weight=' . $weight . '&commodity=GEN-GENERAL OTHERS - GENERAL LAINNYA';

        $url = str_replace(" ", "%20", $url);
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
                'Authorization: Basic bGlvbnBhcmNlbDpsaW9ucGFyY2VsQDEyMw=='
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);

        $result = isset($res->result) ? $res->result : '';
        $r = array();
        $res_ongkir = array();
        if (!empty($result)) {
            foreach ($result as $_res) {
                if ($_res->service_type == 'PACKAGE' && $_res->product == 'REGPACK') {
                    $r[] = $_res;
                }
            }
            $res_ongkir['lp'] = array(
                "type_logistic" => 2,
                "logistic_name" => "Lion Parcel",
                "layanan" => $r
            );
        }
        $dt_log = array(
            "api_name" => "get_ongkir_lp",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($post_data_lp),
            "endpoint" => $url,
            "responses" => serialize($res),
            "id_transaksi" => $id,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        $results = array();

        $results = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $r,
            // "data_alamat"   => $_data,
            "res_ongkir" => $res_ongkir,
            'post_data' => $post_data_lp,
            'result' => $res,
            // 'id_prov_origin'	=> $id_prov_origin,
            // 'data_origin'	=> $data_origin,
            // 'url'			=> $url
        );
        return response($results);
    }

    function upd_setting(Request $request)
    {
        $input = $request->all();
        foreach ($input as $key => $val) {
            $where = array();
            $dt = array();
            $where = array("setting_key" => "$key");
            $dt = ["setting_val" => "$val"];
            DB::table('setting')->where($where)->update($dt);
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $input
        );
        return response($result);
    }

    function get_dc(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $searchdc = $request->searchdc ? $request->searchdc : '';
        $list_item = json_decode($request->list_item);
        //Log::info($list_item);
        $where = array(
            'address_member.deleted_at' => null,
            'provinsi.deleted_at' => null,
            'city.deleted_at' => null,

            'address_member.id_address' => (int)$id
        );
        $_data = DB::table('address_member')->where($where)
            ->select(
                'address_member.*',
                'provinsi_name',
                'provinsi.kode_jne as kode_jne_prov',
                'provinsi.kode_lp as kode_lp_prov',
                'city_name',
                'city.kode_jne as kode_jne_city',
                'city.kode_lp as kode_lp_city',
                'city.id_city_cni as id_city_cni',
            )
            ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
            ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')->first();
        if (empty($_data->id_city_cni)) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'id_city_cni not found',
                'data' => null
            );
            return response($result);
            return false;
        }
        $id_city_cni = $_data->id_city_cni;
        $cnt_details = 0;
        $whereIn = array();
        $list_items[] = array("totalline" => count($list_item));
        try {
            for ($i = 0; $i < count($list_item); $i++) {
                $jml = $list_item[$i]->jml;
                $list_items[] = array(
                    'productid' => $list_item[$i]->kode_produk,
                    'qty' => $jml
                );
            }
        } catch (Exception $e) {
            Log::info($e);
        }

        unset($_data->created_at);
        unset($_data->updated_at);
        unset($_data->deleted_at);
        $postfields = array(
            "cityid" => $id_city_cni, //"150"
            "token" => env('TOKEN_LIST_DC'),
            "detailline" => $list_items,
            "searchdc" => $searchdc
        );
        $url = env('URL_LIST_DC');
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
            "api_name" => "get_dc",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($response),
            "id_transaksi" => $id,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        //$response = array("data_alamat", $_data);
        return response($response)->header('Content-Type', "application/json");
    }

    function cek_stok_dc(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $dcid = (int)$request->dcid > 0 ? (int)$request->dcid : 0;
        $list_item = json_decode($request->list_item);
        $totalline = count($list_item);
        if ((int)$totalline <= 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'list_item required',
                'data' => $dt_notfound
            );
            return response($result);
            return false;
        }
        for ($i = 0; $i < $totalline; $i++) {
            $whereIn[] = $list_item[$i]->id_product;
            $_whereIn = implode(', ', $whereIn);
            $jml = $list_item[$i]->jml;
            $dt_product[$list_item[$i]->id_product] = array(
                'id_product' => $list_item[$i]->id_product,
                'jml' => $jml
            );
        }
        $where = array('product.deleted_at' => null);
        $_data = DB::table('product')->select('product.*')->whereIn('id_product', $whereIn)->where($where)->get();
        $dt_notfound = array();
        $dt_insert = array();
        $dt_insert[] = array(
            'totalline' => $totalline,
        );
        if (count($_data) > 0) {
            foreach ($_data as $dt) {
                $dt_insert[] = array(
                    "productid" => !empty($dt->kode_produk) ? $dt->kode_produk : '-',
                    "qty" => (int)$dt_product[$dt->id_product]['jml']
                );
                if (empty($dt->kode_produk)) {
                    $dt_notfound[] = array(
                        "productid" => !empty($dt->kode_produk) ? $dt->kode_produk : '-',
                        "id_product" => (int)$dt_product[$dt->id_product]['id_product'],
                        "qty" => (int)$dt_product[$dt->id_product]['jml']
                    );
                }
            }
        }
        if (count($dt_notfound) > 0) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Kode produk tidak ditemukan',
                'data' => $dt_notfound
            );
            return response($result);
            return false;
        }
        $postfields = array(
            "dcid" => $dcid, //"150"
            "token" => env('TOKEN_CEKSTOCK_DC'),
            "reqprod" => $dt_insert
        );
        $url = env('URL_CEKSTOCK_DC');
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
            "api_name" => "cek_stok_dc",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($response),
            "id_transaksi" => $dcid,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        return response($response)->header('Content-Type', "application/json");
    }

    function test_ongkir()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://apiv2.jne.co.id:10101/tracing/api/pricedev',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'username=CITRANUSA&api_key=108c038e3534f60472ebb9bc30377831&from=CGK10000&thru=TSM10009&weight=1',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return response($response)->header('Content-Type', "application/json");
    }

    function test_ongkir_lion()
    {

        $curl = curl_init();
        $postfields = array(
            "Origin" => "TANGERANG (TNG)",
            "Destination" => "JATINEGARA, JAKARTA TIMUR (CGK)",
            "Weight" => "2"
        );
        $url = 'https://api-stg-middleware.lionparcel.com/v3/tariffv3?origin=JAKARTA&destination=SEMARANG (SRG)&weight=4.5&commodity=&goods_value=&is_insurance=&is_wood_packing=';
        $url = str_replace(" ", "%20", $url);
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
                'Authorization: Basic bGlvbnBhcmNlbDpsaW9ucGFyY2VsQDEyMw=='
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);

        $result = isset($res->result) ? $res->result : '';
        $r = array();
        $res_ongkir = array();
        if (!empty($result)) {

            foreach ($result as $_res) {
                if ($_res->service_type == 'PACKAGE' && $_res->product == 'REGPACK') {
                    $r[] = $_res;
                }
            }
            $res_ongkir['lp'] = array(
                "type_logistic" => 2,
                "logistic_name" => "Lion Parcel",
                "layanan" => $r
            );
        }
        $results = array();
        $results = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $res_ongkir,
            'res_all' => $res,
        );
        return response($results);
    }

    function test_req(Request $request)
    {
        $result = array();
        $input = $request->all();
        $data = Helper::trans_ewallet("ALLOCATE_EWALLET", '2241', $totalharga = 500000, $potongwallet = 1000000, $id_transaksi = 4, $request->all(), "transaksi");
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function generate_resi(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $prefix = $request->has('prefix') ? $request->prefix : "";
        $where = array('transaksi.id_transaksi' => $id_transaksi);
        $cnt_trans = DB::table('transaksi')->where($where)->count();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'Data not found',
            'data' => '',
            'post_data' => ''
        );
        if ($cnt_trans > 0) {
            $_data = DB::table('transaksi')->select(
                'transaksi.*',
                'members.nama as nama_member',
                'members.email',
                'members.phone as phone_member',
                'members.cni_id'
            )
                ->where($where)
                ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
            $tipe_pengiriman = (int)$_data->tipe_pengiriman;
            if ($tipe_pengiriman == 1) {
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'Silahkan ambil paket anda sesuai DC pada transaksi ini',
                    'data' => $_data,
                    'post_data' => ''
                );
                return response($result);
                return false;
            }
            $type_logistic = (int)$_data->type_logistic;
            if ($type_logistic != 1) {
                $result = array(
                    'err_code' => '03',
                    'err_msg' => 'onprocess ..., type_logistic pada transaksi ini bukan JNE',
                    'data' => $_data,
                    'post_data' => ''
                );
                return response($result);
                return false;
            }
            $cnt_details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->count();
            $list_item = null;
            $ttl_qty = 0;
            if ($cnt_details > 0) {
                $details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
                foreach ($details as $d) {
                    $ttl_qty += (int)$d->jml;
                }
            }
            $ttl_weight = $_data->ttl_weight > 1000 ? $_data->ttl_weight / 1000 : 1;
            $ttl_weight = number_format((float)$ttl_weight, 1, '.', '');
            $order_replace = array("\r\n", "\n", "\r");
            $postfields = array(
                "username" => env('JNE_USERNAME'),
                "api_key" => env('JNE_APIKEY'),
                "OLSHOP_BRANCH" => "CGK000",
                "OLSHOP_CUST" => 80532300,
                "OLSHOP_ORDERID" => 'CNI-' . $prefix . '' . $id_transaksi,
                "OLSHOP_SHIPPER_NAME" => "CNI",
                "OLSHOP_SHIPPER_ADDR1" => "-",
                "OLSHOP_SHIPPER_CITY" => $_data->prov_origin,
                "OLSHOP_SHIPPER_ZIP" => "-",
                "OLSHOP_SHIPPER_PHONE" => "-",
                "OLSHOP_ORIG" => $_data->kode_origin,
                "OLSHOP_RECEIVER_NAME" => $_data->nama_penerima,
                "OLSHOP_RECEIVER_ADDR1" => str_replace($order_replace, '', $_data->alamat),
                "OLSHOP_RECEIVER_CITY" => $_data->city_name,
                "OLSHOP_RECEIVER_ZIP" => $_data->kode_pos,
                "OLSHOP_RECEIVER_PHONE" => $_data->phone_penerima,
                "OLSHOP_QTY" => $ttl_qty,
                "OLSHOP_WEIGHT" => $ttl_weight,
                "OLSHOP_DEST" => $_data->kode_jne_kec,
                "OLSHOP_SERVICE" => $_data->service_code,
                "OLSHOP_GOODSDESC" => "Paket Order #" . $id_transaksi,
                "OLSHOP_GOODSVALUE" => $_data->ttl_belanjaan,
                "OLSHOP_INS_FLAG" => "Y",
                "OLSHOP_GOODSTYPE" => 2
            );
            $url = env('URL_JNE');
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url . '/generatecnote',
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
            $dt_res = isset($res) ? $res->detail[0] : '';
            $status = '08';
            $err_msg = 'Gagal koneksi ke server logistik';
            $cnote_no = '';
            if (!empty($dt_res)) {
                $status = strtolower($dt_res->status) == "sukses" ? "00" : "02";
                $err_msg = strtolower($dt_res->status) == "sukses" ? "ok" : $res->detail[0]->reason;
                $cnote_no = $dt_res->cnote_no;
            }

            $result = array(
                'err_code' => $status,
                'err_msg' => $err_msg,
                'data' => $dt_res,
                'post_data' => $postfields
            );
            if ($status == "00") {
                $dt_upd = array();
                $dt_upd = array(
                    'cnote_no' => $cnote_no,
                    'status' => 4,
                    'delivery_by' => $id_operator,
                    'delivery_date' => $tgl
                );
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            }
            $dt_log = array(
                "api_name" => "generate_resi",
                "param_from_fe" => serialize($request->all()),
                "param_to_cni" => serialize($postfields),
                "endpoint" => $url . '/generatecnote',
                "responses" => serialize($response),
                "id_transaksi" => $id_transaksi,
                "created_at" => $tgl
            );
            DB::table('log_api')->insert($dt_log);
        }

        return response($result);
    }

    function generate_resi_lp(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $tgl2 = date('Y-m-d');
        $result = array();
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;

        $where = array('transaksi.id_transaksi' => $id_transaksi);
        $cnt_trans = DB::table('transaksi')->where($where)->count();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'Data not found',
            'data' => '',
            'post_data' => ''
        );
        if ($cnt_trans > 0) {
            $_data = DB::table('transaksi')->select(
                'transaksi.*',
                'members.nama as nama_member',
                'members.email',
                'members.phone as phone_member',
                'members.cni_id'
            )
                ->where($where)
                ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
            $tipe_pengiriman = (int)$_data->tipe_pengiriman;
            $id_wh = (int)$_data->id_wh;
            if ($tipe_pengiriman == 1) {
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'Silahkan ambil paket anda sesuai DC pada transaksi ini',
                    'data' => $_data,
                    'post_data' => ''
                );
                return response($result);
                return false;
            }
            // $type_logistic = (int)$_data->type_logistic;
            $type_logistic = 2;
            if ($type_logistic != 2) {
                $result = array(
                    'err_code' => '03',
                    'err_msg' => 'type_logistic pada transaksi ini bukan Lion Parcel',
                    'data' => $_data,
                    'post_data' => ''
                );
                return response($result);
                return false;
            }
            $cnt_details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->count();
            $list_item = null;
            $ttl_qty = 0;
            if ($cnt_details > 0) {
                $details = DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
                foreach ($details as $d) {
                    $ttl_qty += (int)$d->jml;
                }
            }
            $ttl_weight = $_data->ttl_weight > 1000 ? $_data->ttl_weight / 1000 : 1;
            $ttl_weight = number_format((float)$ttl_weight, 1, '.', '');
            $ttl_koli = $_data->ttl_weight > 10000 ? ceil($_data->ttl_weight / 10000) : 1;

            $where_wh = ['warehouse.deleted_at' => null, 'warehouse.id_wh' => $id_wh];
            $dt_wh = DB::table('warehouse')->select('warehouse.*', 'provinsi.kode_lp')->where($where_wh)->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'warehouse.id_prov')->first();
            $kode_lp = isset($dt_wh) ? $dt_wh->kode_lp : '';
            $alamat_wh = isset($dt_wh) ? $dt_wh->alamat_wh : '';
            $phone_wh = isset($dt_wh) ? $dt_wh->phone_wh : '';
            $email_wh = isset($dt_wh) ? $dt_wh->email_wh : '';
            $client_code = isset($dt_wh) && !empty($dt_wh->client_code) ? $dt_wh->client_code : env('CLIENT_CODE');

            $url = env('URL_ORDER_LP');
            //$CLIENT_CODE = env('CLIENT_CODE');

            $xml = new SimpleXMLElement('<ORDER_DETAILS/>');

            $xml->addChild('ORDER_NO', 'MCNI-' . $id_transaksi);
            $xml_item = $xml->addChild('PACKAGE_DETAILS');
            $xml_item->addChild('CLIENT_CODE', $client_code);
            $xml_item->addChild('UserType', 'Corporate Customer');
            $xml_item->addChild('EXTERNALNUMBER', 'MCO-' . $id_transaksi);
            $xml_item->addChild('TRACKING_NO', '');
            $xml_item->addChild('PACKAGE_ID', 'PAC-' . $id_transaksi);
            $xml_item->addChild('ORDER_NO_TAG', 'MCNI-' . $id_transaksi);
            $xml_item->addChild('PACKAGE_DATE', $tgl2);
            $xml_item->addChild('PRODUCT_TYPE', 'REGPACK');
            $xml_item->addChild('SERVICE_TYPE', 'PACKAGE');
            $xml_item->addChild('COMMODITY_TYPE', 'GENERAL');
            $xml_item->addChild('NO_OF_PIECES', (int)$ttl_koli);
            $xml_item->addChild('GROSS_WEIGHT', (int)$ttl_weight);
            $xml_item->addChild('VOLUME_WEIGHT', (int)$ttl_weight);
            $xml_item->addChild('CODAMOUNT', 0);
            $xml_item->addChild('SHIPPERNAME', "PT.CITRA NUSA INSAN CEMERLANG");
            $xml_item->addChild('PICK_UP_ADDRESS', $alamat_wh);
            $xml_item->addChild('PICK_UP_LOCATION', $kode_lp);
            $xml_item->addChild('PICK_UP_PHONE', $phone_wh);
            $xml_item->addChild('PICK_UP_EMAIL', $email_wh);
            $xml_item->addChild('RECEIVER_NAME', $_data->nama_penerima);
            $xml_item->addChild('RECEIVER_ADDRESS', $_data->alamat);
            $xml_item->addChild('RECEIVER_LOCATION', $_data->kode_lp_kec);
            $xml_item->addChild('RECEIVER_PHONE', $_data->phone_penerima);
            $xml_item->addChild('RECEIVER_EMAIL', $_data->email);
            $dt_xml = $xml->asXML();

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
                CURLOPT_POSTFIELDS => $dt_xml,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/xml',
                    'Authorization: Basic bGlvbnBhcmNlbDpsaW9ucGFyY2VsQDEyMw=='
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $res = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($res);
            $array = json_decode($json, TRUE);
            $success = isset($array['success']) ? $array['success'] : false;
            $code = isset($array['Code']) ? $array['Code'] : '00';
            $Message = isset($array['Message']) ? $array['Message'] : '';

            $err_msg = isset($Message) ? $Message['EN'] : '';
            $dt_upd = array();
            if ($code == 201) {
                $_dataa = isset($array['Data']) ? $array['Data']['Stt'] : '';
                $cnote_no = isset($_dataa['SttNo']) ? $_dataa['SttNo'] : '';
                $dt_upd = array(
                    'cnote_no' => $cnote_no,
                    'status' => 4,
                    'delivery_by' => $id_operator,
                    'delivery_date' => $tgl
                );
                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($dt_upd);
            }
            $dt_log = array(
                "api_name" => "generate_resi_lp",
                "param_from_fe" => serialize($request->all()),
                "param_to_cni" => $dt_xml,
                "endpoint" => $url,
                "responses" => $response,
                "id_transaksi" => $id_transaksi,
                "created_at" => $tgl
            );
            DB::table('log_api')->insert($dt_log);
            $result = array(
                'err_code' => $code == 201 ? "00" : $code,
                'err_msg' => $code == 201 ? "ok" : $err_msg,
                'data' => $response,
                'post_data' => $dt_xml
            );
            return response($result);
        }
    }

    function store_setting(Request $request)
    {
        $_tgl = date('YmdHis');
        $rancangan_bisnis = $request->file("rancangan_bisnis");
        $panduan_bisnis = $request->file("panduan_bisnis");
        $katalog_produk = $request->file("katalog_produk");
        $info_belanja = $request->file("info_belanja");
        $data = array();
        if (!empty($rancangan_bisnis)) {
            $nama = str_replace(' ', '_', $rancangan_bisnis->getClientOriginalName());

            $fileSize = $rancangan_bisnis->getSize();
            $extension = $rancangan_bisnis->getClientOriginalExtension();
            $imageName = 'rancangan_bisnis_' . $nama;
            $tujuan_upload = 'uploads/info_bisnis';
            $_extension = array('pdf');
            if (!in_array($extension, $_extension)) {
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file extension not valid',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $rancangan_bisnis->move($tujuan_upload, $imageName);
            $where = array();
            $dt = array();
            $where = array("setting_key" => "rancangan_bisnis");
            $dt = ["setting_val" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName];
            DB::table('setting')->where($where)->update($dt);
            $data += array("rancangan_bisnis" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName);
        }

        if (!empty($panduan_bisnis)) {
            $nama = str_replace(' ', '_', $panduan_bisnis->getClientOriginalName());

            $fileSize = $panduan_bisnis->getSize();
            $extension = $panduan_bisnis->getClientOriginalExtension();
            $imageName = 'info_belanja_' . $nama;
            $tujuan_upload = 'uploads/info_bisnis';
            $_extension = array('pdf');
            if (!in_array($extension, $_extension)) {
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file extension not valid',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $panduan_bisnis->move($tujuan_upload, $imageName);
            $where = array();
            $dt = array();
            $where = array("setting_key" => "panduan_bisnis");
            $dt = ["setting_val" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName];
            DB::table('setting')->where($where)->update($dt);
            $data += array("panduan_bisnis" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName);
        }

        if (!empty($katalog_produk)) {
            $nama = str_replace(' ', '_', $katalog_produk->getClientOriginalName());

            $fileSize = $katalog_produk->getSize();
            $extension = $katalog_produk->getClientOriginalExtension();
            $imageName = 'katalog_produk_' . $nama;
            $tujuan_upload = 'uploads/info_bisnis';
            $_extension = array('pdf');
            if (!in_array($extension, $_extension)) {
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file extension not valid',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $katalog_produk->move($tujuan_upload, $imageName);
            $where = array();
            $dt = array();
            $where = array("setting_key" => "katalog_produk");
            $dt = ["setting_val" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName];
            DB::table('setting')->where($where)->update($dt);
            $data += array("katalog_produk" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName);
        }

        if (!empty($info_belanja)) {
            $nama = str_replace(' ', '_', $info_belanja->getClientOriginalName());

            $fileSize = $info_belanja->getSize();
            $extension = $info_belanja->getClientOriginalExtension();
            $imageName = 'info_belanja_' . $nama;
            $tujuan_upload = 'uploads/info_bisnis';
            $_extension = array('pdf');
            if (!in_array($extension, $_extension)) {
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file extension not valid',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $info_belanja->move($tujuan_upload, $imageName);
            $where = array();
            $dt = array();
            $where = array("setting_key" => "info_belanja");
            $dt = ["setting_val" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName];
            DB::table('setting')->where($where)->update($dt);
            $data += array("info_belanja" => env('APP_URL') . '/api_cni/uploads/info_bisnis/' . $imageName);
        }

        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function test_send_order(Request $request)
    {
        $id_transaksi = $request->id_transaksi;
        $gen_cni_id = Helper::send_order_cni($id_transaksi);
        echo $gen_cni_id;
    }
}
