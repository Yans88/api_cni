<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $out
        );
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        return response($result);
    }

    function get_ongkir(Request $request)
    {
        $result = array();
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $tipe_pengiriman = (int)$request->tipe_pengiriman > 0 ? (int)$request->tipe_pengiriman : 0;
        $weight = (int)$request->weight > 0 ? (int)$request->weight / 1000 : 1;
        $origin = !empty($request->origin) ? strtoupper($request->origin) : '';
        if ($weight < 1) $weight = 1;
        if ((int)$id <= 0) {
            $result = array(
                'err_code'      => '06',
                'err_msg'       => 'id_address required',
                'data'          => null
            );
            return response($result);
            return false;
        }
		if($tipe_pengiriman == 2){
			$result = array(
                'err_code'      => '02',
                'err_msg'       => 'onprocess, tipe_pengiriman belum bisa digunakan',
                'data'          => null
            );
            return response($result);
            return false;	
		}
		
        if ($tipe_pengiriman == 2 && (empty($origin) || $origin == '')) {
            $result = array(
                'err_code'      => '06',
                'err_msg'       => 'origin required',
                'data'          => null
            );
            return response($result);
            return false;
        }
        $where = array(
			'address_member.deleted_at' => null, 
			'provinsi.deleted_at' 		=> null, 
			'city.deleted_at' 			=> null, 
			'kecamatan.deleted_at' 		=> null, 
			'address_member.id_address' => (int)$id);
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
                'err_code'      => '04',
                'err_msg'       => 'data not found',
                'data'          => null
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
		
		if($id_prov_origin <= 0 && $tipe_pengiriman !=2){
			$result = array(
                'err_code'      => '08',
                'err_msg'       => 'data alamat invalid, belum dimapping',
                'data'          => null
            );
            return response($result);
            return false;			
		}
		
		$data_origin = DB::table('provinsi')->where(array('id_provinsi'=>$id_prov_origin))->first();
		
        $_from_jne = strtoupper($data_origin->kode_jne);
        $_from_lp = strtoupper($data_origin->kode_lp);
		
        if ($tipe_pengiriman == 2) {
            $_from = $origin;
        }
        $postfields = array(
            "username" => env('JNE_USERNAME'),
            "api_key" => env('JNE_APIKEY'),
            "from"      => $_from_jne,
            "thru"      => $_data->kode_jne_kec,
            "weight"    => $weight,
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
        $res =  json_decode($response);
        if (!empty($res) && !empty($res->price)) {
            $_res = $res->price;
            $origin_name = $_res[0]->origin_name;
            $destination_name = $_res[0]->destination_name;
            foreach ($_res as $r) {
                $_r[] = array(
                    "service_display"   => $r->service_display,
                    "service_code"      => $r->service_code,
                    "price"             => $r->price,
                    "etd"               => $r->etd_from . '-' . $r->etd_thru,
                    "times"             => $r->times
                );
            }
            $res_ongkir = array(
                "origin_name"       => $origin_name,
                "destination_name"  => $destination_name,
				"type_logistic"		=> 1,
				"logistic_name"		=> "JNE",
                "layanan"           => $_r
            );
        } else {
            $res_ongkir = $res;
        }
        $post_data_jne = array(
            "from"          => $_from_jne,
            "thru"          => $_data->kode_jne_kec,
            "weight"        => $weight
        );
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $res_ongkir,
            'post_data'     => $post_data_jne,
            "data_alamat"   => $_data,
        );
        return response($result);
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
        echo $response;
    }
}
