<?php

namespace App\Http\Controllers;

use App\Models\Admin as Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        Helper::notify_cart();
    }

    public function index(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = ['admin.deleted_at' => null];
        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {

            $_data = DB::table('admin')->select('admin.*', 'level_name', 'wh_name')
                ->leftJoin('level', 'level.id_level', '=', 'admin.id_level')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'admin.id_wh')
                ->where($where)->whereRaw("LOWER(name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = Admin::where($where)->count();
            // $count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('admin')->select('admin.*', 'level_name', 'wh_name')
                ->leftJoin('level', 'level.id_level', '=', 'admin.id_level')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'admin.id_wh')
                ->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
                $password  = '';
                $password  = Crypt::decryptString($d->password);
                unset($d->created_by);
                unset($d->updated_by);
                unset($d->deleted_by);
                unset($d->created_at);
                unset($d->updated_at);
                unset($d->deleted_at);
                unset($d->password);
                $d->pass = $password;
                $data[] = $d;
            }
            //$password = Crypt::decryptString($data->password);
            //unset($data->password);
            //$data->password = $password;
            $result = array(
                'err_code'      => '00',
                'err_msg'          => 'ok',
                'total_data'    => $count,
                'data'          => $data
            );
        }
        return response($result);
    }

    function detail(Request $request)
    {

        $id_admin = (int)$request->id_admin;
        $where = ['admin.deleted_at' => null, 'id_admin' => $id_admin];

        $count = Admin::where($where)->count();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => null
        );
        if ($count > 0) {
            $data = DB::table('admin')->select('admin.*', 'level.*', 'wh_name')
                ->leftJoin('level', 'level.id_level', '=', 'admin.id_level')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'admin.id_wh')
                ->where($where)->first();
            // $data = Admin::where($where)->first();
            $password = Crypt::decryptString($data->password);
            unset($d->created_by);
            unset($d->updated_by);
            unset($d->deleted_by);
            unset($d->created_at);
            unset($d->updated_at);
            unset($d->deleted_at);
            unset($data->password);
            $data->password = $password;
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        }
        return response($result);
    }

    function store(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $data = new Admin();
        $data->name = $request->name;
        $data->username = $request->username;
        $data->id_level = (int)$request->id_level;
        $data->id_wh = (int)$request->id_wh;
        $data->password = Crypt::encryptString(strtolower($request->pass));
        $data->created_at = $tgl;
        $data->created_by = $request->created_by;
        $data->save();
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
        );
        return response($result);
    }

    function edit(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_admin = (int)$request->id_admin;
        if ($id_admin > 0) {
            $data = Admin::where('id_admin', $id_admin)->first();
            $data->name = $request->name;
            $data->username = $request->username;
            $data->id_level = (int)$request->id_level;
            $data->id_wh = (int)$request->id_wh;
            if (!empty($request->pass)) $data->password = Crypt::encryptString(strtolower($request->pass));
            $data->updated_at = $tgl;
            $data->updated_by = $request->updated_by;
            $data->save();
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        } else {
            $result = array(
                'err_code'  => '02',
                'err_msg'   => 'id_admin required',
                'data'      => null
            );
        }
        return response($result);
    }

    function del(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_admin = $request->id_admin;
        $data = Admin::where('id_admin', $id_admin)->first();
        $data->deleted_at = $tgl;
        $data->deleted_by = $request->deleted_by;
        $data->save();
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok'
        );
        return response($result);
    }

    function login_cms(Request $request)
    {

        $username = $request->username;
        $pass = strtolower($request->pass);
        $where = ['deleted_at' => null, 'username' => $username];

        $count = Admin::where($where)->count();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => null
        );
        if ($count > 0) {
            $data = Admin::where($where)->first();
            $password = Crypt::decryptString($data->password);
            if ($pass == $password) {
                unset($data->password);
                //$data->password = $password;
                $result = array(
                    'err_code'  => '00',
                    'err_msg'   => 'ok',
                    'data'      => $data
                );
            } else {
                $result = array(
                    'err_code'  => '03',
                    'err_msg'   => 'password tidak sesuai'

                );
            }
        }
        return response($result);
    }

    //
}
