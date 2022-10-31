<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SimpatikController extends Controller
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
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'nama';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status > 0 ? (int)$request->status : 0;

        $where = array('is_agree' => 1);
        if ($status > 0) {
            $where += array('simpatik.status' => $status);
        }

        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {
            $_data = DB::table('simpatik')->select('simpatik.*', 'members.nama', 'members.cni_id', 'members.phone', 'city.city_name as nama_kota_kecelakaan')->where($where)->whereRaw("LOWER(members.nama) like '%" . $keyword . "%'")->leftJoin('members', 'members.id_member', '=', 'simpatik.id_member')->leftJoin('city', 'city.id_city', '=', 'simpatik.kota_kecelakaan')->get();
            $count = count($_data);
        } else {
            $count = DB::table('simpatik')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('simpatik')->select('simpatik.*', 'members.nama', 'members.cni_id', 'members.phone', 'city.city_name as nama_kota_kecelakaan')->where($where)->leftJoin('members', 'members.id_member', '=', 'simpatik.id_member')->leftJoin('city', 'city.id_city', '=', 'simpatik.kota_kecelakaan')->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
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
        $_tgl = date('YmdHi');
        $data = array();
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $kota_kecelakaan = (int)$request->kota_kecelakaan > 0 ? (int)$request->kota_kecelakaan : 0;
        $tgl_kecelakaan = $request->tgl_kecelakaan ? date('Y-m-d', strtotime($request->tgl_kecelakaan)) : null;
        $tgl_pernah_kecelakaan_sebelumnya = $request->tgl_pernah_kecelakaan_sebelumnya ? date('Y-m-d', strtotime($request->tgl_pernah_kecelakaan_sebelumnya)) : null;
        $kat_santunan = $request->kat_santunan ? $request->kat_santunan : '';
        $lama_rawat_inap = (int)$request->lama_rawat_inap > 0 ? (int)$request->lama_rawat_inap : '';
        $luka_dialami = $request->luka_dialami ? $request->luka_dialami : '';
        $penyebab_kecelakaan = $request->penyebab_kecelakaan ? $request->penyebab_kecelakaan : '';
        $pernah_kecelakaan_sebelumnya = $request->pernah_kecelakaan_sebelumnya ? $request->pernah_kecelakaan_sebelumnya : '';
        $berdampak_cacat = $request->berdampak_cacat ? $request->berdampak_cacat : '';
        $rincian_penyebabnya = $request->rincian_penyebabnya ? $request->rincian_penyebabnya : '';
        $meninggal_rincian_penyebabnya = $request->meninggal_rincian_penyebabnya ? $request->meninggal_rincian_penyebabnya : '';
        $is_agree = (int)$request->is_agree > 0 ? (int)$request->is_agree : 0;

        $nama_dokter = $request->file("nama_dokter");
        $no_ref_dokter = $request->file("no_ref_dokter");
        $no_hp_dokter = $request->file("no_hp_dokter");
        if ((int)$id_member <= 0) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'id_member is required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if ((int)$is_agree <= 0) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'is_agree must be checked',
                'data'      => null
            );
            return response($result);
            return false;
        }

        $data = array(
            'id_member'                         => $id_member,
            'kota_kecelakaan'                     => $kota_kecelakaan,
            'tgl_kecelakaan'                     => $tgl_kecelakaan,
            'kat_santunan'                         => $kat_santunan,
            'lama_rawat_inap'                     => $lama_rawat_inap,
            'luka_dialami'                         => $luka_dialami,
            'penyebab_kecelakaan'                 => $penyebab_kecelakaan,
            'pernah_kecelakaan_sebelumnya'         => $pernah_kecelakaan_sebelumnya,
            'tgl_pernah_kecelakaan_sebelumnya'     => $tgl_pernah_kecelakaan_sebelumnya,
            'berdampak_cacat'                     => $berdampak_cacat,
            'rincian_penyebabnya'                 => $rincian_penyebabnya,
            'meninggal_rincian_penyebabnya'     => $meninggal_rincian_penyebabnya,
            'is_agree'                            => $is_agree,
            "created_at"                         => $tgl,
            "status"                             => 1,
        );

        if (!empty($nama_dokter)) {
            $nama = 'nama_dokter';
            if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
            $nama = strtolower($nama);
            $nama_file = $_tgl . '' . $nama;
            $nama_file = Crypt::encryptString($nama_file);
            $fileSize = $nama_dokter->getSize();
            $extension = $nama_dokter->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/simpatik';
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
            // if (!in_array($extension, $_extension)) {
            // $result = array(
            // 'err_code'  => '07',
            // 'err_msg'   => 'file extension not valid',
            // 'data'      => null
            // );
            // return response($result);
            // return false;
            // }
            $nama_dokter->move($tujuan_upload, $imageName);
            $data += array("nama_dokter" => env('APP_URL') . '/api_cni/uploads/simpatik/' . $imageName);
        }
        if (!empty($no_ref_dokter)) {
            $nama = 'no_ref_dokter';
            if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
            $nama = strtolower($nama);
            $nama_file = $_tgl . '' . $nama;
            $nama_file = Crypt::encryptString($nama_file);
            $fileSize = $no_ref_dokter->getSize();
            $extension = $no_ref_dokter->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/simpatik';
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
            $no_ref_dokter->move($tujuan_upload, $imageName);
            $data += array("no_ref_dokter" => env('APP_URL') . '/api_cni/uploads/simpatik/' . $imageName);
        }
        if (!empty($no_hp_dokter)) {
            $nama = 'no_hp_dokter';
            if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
            $nama = strtolower($nama);
            $nama_file = $_tgl . '' . $nama;
            $nama_file = Crypt::encryptString($nama_file);
            $fileSize = $no_hp_dokter->getSize();
            $extension = $no_hp_dokter->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/simpatik';
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
            $no_hp_dokter->move($tujuan_upload, $imageName);
            $data += array("no_hp_dokter" => env('APP_URL') . '/api_cni/uploads/simpatik/' . $imageName);
        }
        $id = DB::table('simpatik')->insertGetId($data, "id");
        $result = array();
        if ($id > 0) {
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

    function history(Request $request)
    {
        $result = array();
        $_data = array();
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $where = array('simpatik.id_member' => $id_member);
        $_data = DB::table('simpatik')->select('simpatik.*', 'members.nama', 'members.cni_id', 'city.city_name as nama_kota_kecelakaan')->where($where)->leftJoin('members', 'members.id_member', '=', 'simpatik.id_member')->leftJoin('city', 'city.id_city', '=', 'simpatik.kota_kecelakaan')->get();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => null
        );
        if (!empty($_data)) {
            $result = array(
                'err_code'      => '00',
                'err_msg'       => 'ok',
                'data'          => $_data
            );
        }
        return response($result);
    }

    function detail(Request $request)
    {
        $result = array();
        $_data = array();
        $id = (int)$request->id > 0 ? (int)$request->id : 0;
        $where = array('simpatik.id' => $id);
        $_data = DB::table('simpatik')->select('simpatik.*', 'members.nama', 'members.cni_id', 'members.phone', 'city.city_name as nama_kota_kecelakaan')->where($where)->leftJoin('members', 'members.id_member', '=', 'simpatik.id_member')->leftJoin('city', 'city.id_city', '=', 'simpatik.kota_kecelakaan')->first();
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $_data
        );
        return response($result);
    }

    function upd_status(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $_data = array();
        $id = (int)$request->id > 0 ? (int)$request->id : 0;
        $status = (int)$request->status > 0 ? (int)$request->status : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $keterangan = !empty($request->keterangan)  ? $request->keterangan : '-';
        $data_upd = array(
            'status'        => $status,
            'keterangan'    => $keterangan,
        );
        if ($status == 2) {
            $data_upd += array(
                'received_date'    => $tgl,
                'received_by'    => $id_operator,
            );
        }
        if ($status == 3 || $status == 5) {
            $data_upd += array(
                'appr_reject_date'    => $tgl,
                'appr_reject_by'    => $id_operator,
            );
        }
        $where = array('simpatik.id' => $id);
        DB::table('simpatik')->where(array("id" => $id))->update($data_upd);
        $_data = DB::table('simpatik')->select('simpatik.*', 'members.nama', 'members.cni_id')->where($where)->leftJoin('members', 'members.id_member', '=', 'simpatik.id_member')->first();
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $_data
        );
        return response($result);
    }

    function upl_bukti_transfer(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('YmdHi');
        $result = array();
        $_data = array();
        $id = (int)$request->id > 0 ? (int)$request->id : 0;
        $status = (int)$request->status > 0 ? (int)$request->status : 4;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $data_upd = array(
            'status'                => $status,
            'bukti_transfer_date'    => $tgl,
            'bukti_transfer_by'        => $id_operator,
        );
        $bukti_transfer = $request->file("bukti_transfer");
        if (!empty($bukti_transfer)) {
            $nama = 'bukti_transfer';
            if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
            $nama = strtolower($nama);
            $nama_file = $id . '' . $_tgl . '' . $nama;
            $nama_file = Crypt::encryptString($nama_file);
            $fileSize = $bukti_transfer->getSize();
            $extension = $bukti_transfer->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/simpatik';
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
            $bukti_transfer->move($tujuan_upload, $imageName);
            $data_upd += array("bukti_transfer" => env('APP_URL') . '/api_cni/uploads/simpatik/' . $imageName);
        }
        $where = array('simpatik.id' => $id);
        DB::table('simpatik')->where(array("id" => $id, "status" => 3))->update($data_upd);
        $_data = DB::table('simpatik')->select('simpatik.*', 'members.nama', 'members.cni_id')->where($where)->leftJoin('members', 'members.id_member', '=', 'simpatik.id_member')->first();
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $_data,
        );
        return response($result);
    }
}
