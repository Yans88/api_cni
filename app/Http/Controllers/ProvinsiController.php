<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProvinsiController extends Controller
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
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'provinsi_name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $is_wh = (int)$request->is_wh > 0 ? (int)$request->is_wh : 0;
        $where = array('deleted_at' => null);
        if ($is_wh > 0) $where += array('id_wh' => 0);
        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {
            $_data = DB::table('provinsi')->where($where)->whereRaw("LOWER(provinsi_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('provinsi')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('provinsi')->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
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
                'data' => $data
            );
        }
        return response($result);
    }

    function store(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $data = array();
        $id = (int)$request->id_provinsi > 0 ? (int)$request->id_provinsi : 0;
        $data = array(
            'provinsi_name' => $request->provinsi_name,
            'id_prov_cni' => (int)$request->id_prov_cni,
            'kode_jne' => strtoupper($request->kode_jne),
            'kode_lp' => strtoupper($request->kode_lp)
        );

        if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => $request->id_operator);
            DB::table('provinsi')->where('id_provinsi', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => $request->id_operator, 'id_wh' => 0);
            $id = DB::table('provinsi')->insertGetId($data, "id_provinsi");
        }
        $result = array();
        if ($id > 0) {
            $data += array('id_provinsi' => $id);
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
        $id = (int)$request->id_provinsi > 0 ? (int)$request->id_provinsi : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => (int)$request->id_operator);
        DB::table('provinsi')->where('id_provinsi', $id)->update($data);
        $where = array('deleted_at' => null, 'id_prov' => $id);
        $_data = DB::table('city')->where($where)->select('id_city')->get();
        $id_city = array();
        if (count($_data) > 0) {
            foreach ($_data as $pa) {
                $id_city[] = $pa->id_city;
                //$whereIn = implode(',', $id_city);
            }
            DB::table('kecamatan')->where(array("deleted_at" => null))
                ->whereIn('id_city', $id_city)->update($data);
        }
        DB::table('city')->where($where)->update($data);
        $data_wh = DB::table('warehouse')->select('id_wh')->where($where)->first();
        if (!empty($data_wh) && $data_wh->id_wh > 0) {
            // $where_prov = array('deleted_at' => null, 'id_wh' => $data_wh->id_wh);
            // DB::table('provinsi')->where($where_prov)->update(array('id_wh' => 0));
            DB::table('warehouse')->where($where)
                ->update(array('id_prov' => 0, 'updated_at' => $tgl, 'updated_at' => (int)$request->id_operator));
        }
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function get_wh(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'warehouse.wh_name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = ['warehouse.deleted_at' => null];
        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {
            $_data = DB::table('warehouse')->select('warehouse.*', 'provinsi_name', 'kode_jne', 'kode_lp')
                ->where($where)->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'warehouse.id_prov')
                ->whereRaw("LOWER(warehouse.wh_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('warehouse')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('warehouse')->select('warehouse.*', 'provinsi_name', 'kode_jne', 'kode_lp')
                ->where($where)->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'warehouse.id_prov')
                ->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ((int)$count > 0) {
            foreach ($_data as $d) {
                unset($d->created_by);
                unset($d->updated_by);
                unset($d->deleted_by);
                unset($d->created_at);
                unset($d->updated_at);
                unset($d->deleted_at);
                unset($d->is_jne);
                unset($d->is_lp);
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

    function add_wh(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $data = array();
        $id = (int)$request->id_wh > 0 ? (int)$request->id_wh : 0;
        $data = array(
            'wh_name' => $request->wh_name,
            'phone_wh' => $request->phone_wh,
            'email_wh' => $request->email_wh,
            'alamat_wh' => $request->alamat_wh,
            'client_code' => $request->client_code,
            'id_prov' => (int)$request->id_prov,
            'is_jne' => (int)$request->is_jne,
            'is_lp' => (int)$request->is_lp
        );

        if ($id > 0) {
            $where = ['warehouse.deleted_at' => null, 'id_wh' => $id];
            $_data = DB::table('warehouse')->where($where)->first();
            $id_prov = !empty($_data) && (int)$_data->id_prov > 0 ? (int)$_data->id_prov : 0;
            if ($id_prov > 0) DB::table('provinsi')->where('id_provinsi', $id_prov)->update(array('id_wh' => 0));
            $data += array("updated_at" => $tgl, "updated_by" => (int)$request->id_operator);
            DB::table('warehouse')->where('id_wh', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => (int)$request->id_operator);
            $id = DB::table('warehouse')->insertGetId($data, "id_wh");
        }
        $result = array();
        if ($id > 0) {
            $data += array('id_wh' => $id);
            DB::table('provinsi')->where('id_provinsi', (int)$request->id_prov)->update(array('id_wh' => $id));
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

    function del_wh(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_wh > 0 ? (int)$request->id_wh : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => (int)$id_operator);
        DB::table('warehouse')->where('id_wh', $id)->update($data);
        $where = array('deleted_at' => null, 'id_wh' => $id);
        DB::table('provinsi')->where($where)
            ->update(array('id_wh' => 0, 'updated_at' => $tgl, 'updated_by' => (int)$id_operator));
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function get_area(Request $request)
    {
        $id = (int)$request->id_wh > 0 ? (int)$request->id_wh : 0;
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'provinsi_name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = array('deleted_at' => null, 'id_wh' => $id);
        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {
            $_data = DB::table('provinsi')->where($where)->whereRaw("LOWER(provinsi_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('provinsi')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('provinsi')->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $data_wh = DB::table('warehouse')->select('warehouse.*', 'provinsi_name', 'kode_jne', 'kode_lp')
            ->where(array('warehouse.id_wh' => $id))->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'warehouse.id_prov')->first();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'id_wh' => $data_wh->id_wh,
            'wh_name' => $data_wh->wh_name,
            'provinsi_origin' => $data_wh->provinsi_name,
            'kode_jne' => $data_wh->kode_jne,
            'kode_lp' => $data_wh->kode_lp,
            'data' => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
                unset($d->created_by);
                unset($d->updated_by);
                unset($d->deleted_by);
                unset($d->created_at);
                unset($d->updated_at);
                unset($d->deleted_at);
                $d->origin_utama = (int)$data_wh->id_prov == $d->id_provinsi ? 1 : 0;
                $data[] = $d;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'id_wh' => $data_wh->id_wh,
                'wh_name' => $data_wh->wh_name,
                'provinsi_origin' => $data_wh->provinsi_name,
                'kode_jne' => $data_wh->kode_jne,
                'kode_lp' => $data_wh->kode_lp,
                'data' => $data
            );
        }
        return response($result);
    }

    function assign_area(Request $request)
    {
        Log::info($request);
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_wh > 0 ? (int)$request->id_wh : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $list_prov = $request->id_prov;

        $whereIn = array();
        $data = array("id_wh" => $id, "updated_at" => $tgl, "updated_by" => (int)$request->id_operator);
        if (count($list_prov) > 0) {
            for ($i = 0; $i < count($list_prov); $i++) {
                $whereIn[] = $list_prov[$i];
            }
            DB::table('provinsi')->where(array("deleted_at" => null))
                ->whereIn('id_provinsi', $whereIn)->update($data);
        }
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function remove_area(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_wh > 0 ? (int)$request->id_wh : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $id_prov = (int)$request->id_prov > 0 ? (int)$request->id_prov : 0;
        $data = array("id_wh" => 0, "updated_at" => $tgl, "updated_by" => (int)$request->id_operator);
        DB::table('provinsi')->where(array("deleted_at" => null, "id_provinsi" => $id_prov))->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }
}
