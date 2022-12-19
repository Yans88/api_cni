<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlastController extends Controller
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
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_blast';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        if ($sort_column == 'id_blast') $sort_column = $sort_column . "::integer";
        $sort_column .= ' ' . $sort_order;
        $where = ['deleted_at' => null];
        $count = 0;
        $_data = array();
        $data = array();
        if (!empty($keyword)) {
            $_data = DB::table('blast_notif')->select('blast_notif.*')
                ->where($where)->whereRaw("LOWER(product_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('blast_notif')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('blast_notif')->select('blast_notif.*')
                ->where($where)->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
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
                //unset($d->created_at);
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
        $result = array();
        $tgl = date('Y-m-d H:i:s');
        $data = array();
        $id_product = $request->has('id_product') && (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $content = $request->has('isi') ? $request->isi : '';
        $tujuan = $request->has('tujuan') ? $request->tujuan : 'Semua pengguna';
        $data_product = DB::table('product')->select('product.product_name', 'product.kode_produk')->where(array('id_product' => $id_product))->first();
        $product_name = (int)$request->id_product > 0 ? $data_product->product_name : "Tidak ada";
        $kode_produk = (int)$request->id_product > 0 ? $data_product->kode_produk : '';
        $list_member = $request->id_member;
        $whereIn = array();
        $cnt_member = 0;

        if ($tujuan == 'Semua pengguna') {
            $list_member = array();
            $cnt_member = DB::table('members')->where(array("deleted_at" => null))->count();
            if ((int)$cnt_member > 0) {
                $dt_member = DB::table('members')->where(array("deleted_at" => null))->get();
                foreach ($dt_member as $dt) {
                    $list_member[] = $dt->id_member;
                }
            }
        }
        $data_notif = array(
            'id_product' => (int)$request->id_product > 0 ? $id_product : -1,
            'product_name' => $product_name,
            'kode_produk' => $kode_produk,
            'content' => $content,
            'tujuan' => $tujuan,
            'created_at' => $tgl,
            'created_by' => (int)$request->id_operator,
        );
        $id = DB::table('blast_notif')->insertGetId($data_notif, "id_blast");
        $notif_fcm = array(
            'body' => $content,
            'title' => 'CNI',
            'badge' => '1',
            'sound' => 'Default'
        );
        $dt_insert_notif = array();
        $target = array();
        $fcm_token = '';
        if (count($list_member) > 0) {
            $fcm_token = DB::table('fcm_token')->select('token_fcm')->whereIn('id_member', $list_member)->groupBy('token_fcm')->get();
            for ($i = 0; $i < count($list_member); $i++) {
                $dt_insert_notif[] = array(
                    'id' => $id_product,
                    'id_member' => $list_member[$i],
                    'content' => $content,
                    'type' => (int)$id_product > 0 ? 3 : 2,
                    'id_blast' => $id,
                    'unread' => 1,
                    'created_at' => $tgl,
                    'created_by' => (int)$request->id_operator
                );
            }
            DB::table('history_notif')->insert($dt_insert_notif);
        }

        $data_fcm = array(
            'id' => $id_product,
            'title' => 'CNI',
            'message' => $content,
            'type' => (int)$id_product > 0 ? 3 : 2
        );
        if (!empty($fcm_token)) {
            foreach ($fcm_token as $dt) {
                if (!empty($dt->token_fcm)) array_push($target, $dt->token_fcm);
            }
            Helper::send_fcm_multiple($target, $data_fcm, $notif_fcm);
        }

        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $dt_insert_notif
        );
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
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function detail(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_blast > 0 ? (int)$request->id_blast : 0;
        $data = DB::table('blast_notif')->where('id_blast', $id)->first();
        $data_members = DB::table('history_notif')->select('members.nama', 'members.email', 'members.phone', 'members.cni_id')->where('id_blast', $id)
            ->leftJoin('members', 'members.id_member', '=', 'history_notif.id_member')->get();
        $result = array();

        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data,
            'list_members' => $data_members,
        );
        return response($result);
    }

    function test_cron_cart()
    {
        $res = Helper::notify_cart();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $res,

        );
        return response($result);
    }


}
