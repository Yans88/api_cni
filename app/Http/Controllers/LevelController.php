<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class LevelController extends Controller
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
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_level';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
		if($sort_column == 'id_level') $sort_column = $sort_column."::integer";
		$sort_column .=' '.$sort_order;
        $where = ['deleted_at' => null];
        $count = 0;
        $_data = array();
        $data = array();
        if (!empty($keyword)) {
            $_data = DB::table('level')->select('level.*')
                
                ->where($where)->whereRaw("LOWER(level_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('level')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('level')->select('level.*')                
                ->where($where)->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
        }
        $result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
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
        $result = array();
		$input  = $request->all();
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('YmdHi');
        $data = array();
        $id = (int)$request->id_level > 0 ? (int)$request->id_level : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
		$level_name = $request->has('level_name') ? $request->level_name : '';
        $data['level_name'] = $level_name;	
		
		foreach($input as $key=>$val){
			$val = $request->has($key) && (int)$val > 0 ? (int)$val : 0;		
			if($key != 'level_name') $data[$key] = (int)$val;			
		}		
        unset($data['id_operator']);
		unset($data['id_level']);   
		unset($data['created_at']);   
		unset($data['created_by']);   
		unset($data['updated_at']);   
		unset($data['updated_by']);   
		unset($data['deleted_at']);   
		unset($data['deleted_by']);   
		unset($data['showFormSuccess']);   
        
        if ($id > 0) {
			$data['updated_at'] = $tgl;
			$data['updated_by'] = $id_operator;           
            DB::table('level')->where('id_level', $id)->update($data);
        } else {
			$data['created_at'] = $tgl;
			$data['created_by'] = $id_operator;           
            $id = DB::table('level')->insertGetId($data, "id_level");
        }

        if ($id > 0) {            
			$data+=array("id_level"=>$id);
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        } else {
            $result = array(
                'err_code'  => '05',
                'err_msg'   => 'insert has problem',
                'data'      => null
            );
        }
        return response($result);
    }

    function proses_delete(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_level > 0 ? (int)$request->id_level : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => $request->id_operator);
        DB::table('level')->where('id_level', $id)->update($data);
        $result = array();
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => null
        );
        return response($result);
    }
	
	function detail(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_level > 0 ? (int)$request->id_level : 0;        
        $data = DB::table('level')->where('id_level', $id)->first();
        $result = array();
		
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
        );
        return response($result);
    }

    
}
