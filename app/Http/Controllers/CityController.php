<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CityController extends Controller
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
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'city_name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = array('city.deleted_at' => null);
        if ((int)$request->id_provinsi > 0) $where += array('city.id_prov' => $request->id_provinsi);
        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {
            $_data = DB::table('city')->where($where)->select('city.*', 'provinsi_name')
                ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'city.id_prov')
                ->whereRaw("LOWER(city_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('city')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('city')->where($where)->select('city.*', 'provinsi_name')
                ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'city.id_prov')
                ->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $provinsi = '';
        if ($count == 0) {
            $data_prov = DB::table('provinsi')->where(array('id_provinsi' => $request->id_provinsi))->first();
            $provinsi = $data_prov->provinsi_name;
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'provinsi' => $provinsi,
            'data' => null
        );
        if ($count > 0) {
            $provinsi = $_data[0]->provinsi_name;
            foreach ($_data as $d) {
                unset($d->created_by);
                unset($d->updated_by);
                unset($d->deleted_by);
                unset($d->created_at);
                unset($d->updated_at);
                unset($d->deleted_at);
                $data[] = $d;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'provinsi' => $provinsi,
                'data' => $data
            );
        }
        return response($result);
    }

    function store(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $data = array();
        $id = (int)$request->id_city > 0 ? (int)$request->id_city : 0;
        $data = array(
            'city_name' => $request->city_name,
            'id_city_cni' => (int)$request->id_city_cni,
            'kode_jne' => strtoupper($request->kode_jne),
            'kode_lp' => strtoupper($request->kode_lp)
        );
        if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => $request->id_operator);
            DB::table('city')->where('id_city', $id)->update($data);
        } else {
            $data += array(
                "id_prov" => (int)$request->id_provinsi,
                "created_at" => $tgl,
                "created_by" => $request->id_operator
            );
            $id = DB::table('city')->insertGetId($data, "id_city");
        }
        $result = array();
        if ($id > 0) {
            $data += array('id_city' => $id);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'insert has problem',
                'data' => null
            );
        }
        return response($result);
    }

    function proses_delete(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_city > 0 ? (int)$request->id_city : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => (int)$request->id_operator);
        DB::table('city')->where('id_city', $id)->update($data);
        $where = array('deleted_at' => null, 'id_city' => $request->id);
        DB::table('kecamatan')->where($where)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }
}
