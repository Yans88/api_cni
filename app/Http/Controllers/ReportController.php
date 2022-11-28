<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
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
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status >= 0 ? (int)$request->status : -1;
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;

        $from = !empty($request->start_date) ? date('Y-m-d', strtotime($request->start_date)) : '';
        $to = !empty($request->end_date) ? date('Y-m-d', strtotime($request->end_date)) : $from;
        $from = empty($from) && !empty($to) ? $to : $from;
        $to = empty($to) && !empty($from) ? $from : $to;

        $count = 0;
        $_data = array();
        $data = null;
        $sql = '';
        $sql = "select transaksi.id_transaksi,transaksi.created_at,transaksi.type_member,transaksi.payment_name, members.nama as nama_member,
				transaksi.iddc,transaksi.wh_name,ttl_pv,ttl_rv,ttl_disc,members.cni_id, members.cni_id_ref from transaksi
				left join members on members.id_member = transaksi.id_member where 1=1 ";

        if ($status >= 0) {
            $sql .= " and transaksi.status = " . $status;
        }
        if (!empty($from)) {
            $sql .= " and to_char(transaksi.created_at, 'YYYY-MM-DD') >= '" . $from . "' and to_char(transaksi.created_at, 'YYYY-MM-DD') <= '" . $to . "'";
        }

        $_dataa = DB::select(DB::raw($sql));
        $count = count($_dataa);

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $sql .= " order by $sort_column limit $per_page offset $offset";
            $_data = DB::select(DB::raw($sql));
            $nama_ref = array();
            $sql_member = '';
            $sql_member = "select nama as nama_member, cni_id from members where type = 1";
            $data_member = DB::select(DB::raw($sql_member));
            $count_members = count($data_member);
            if ((int)$count_members > 0) {
                foreach ($data_member as $dm) {
                    $nama_ref[$dm->cni_id] = $dm->nama_member;
                }
            }

            foreach ($_data as $d) {

                $d->nama_ref = isset($nama_ref[$d->cni_id_ref]) && (int)$d->type_member === 1 ? $nama_ref[$d->cni_id_ref] : '';
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

    function detail(Request $request)
    {
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;

        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;

        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_trans';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;

        $column_int = array("id_trans");
        if (in_array($sort_column, $column_int)) $sort_column = 'transaksi_detail.' . $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;

        $from = !empty($request->start_date) ? date('Y-m-d', strtotime($request->start_date)) : '';
        $to = !empty($request->end_date) ? date('Y-m-d', strtotime($request->end_date)) : $from;
        $from = empty($from) && !empty($to) ? $to : $from;
        $to = empty($to) && !empty($from) ? $from : $to;


        $data = null;
        $sql = '';

        $sql = "select transaksi_detail.id_trans as id_transaksi,transaksi_detail.kode_produk,transaksi_detail.kode_produk,pv,rv, transaksi_detail.product_name,transaksi_detail.jml,hm_non_ppn,hk_non_ppn,transaksi.type_member, transaksi.created_at, transaksi.payment_name,transaksi.iddc,transaksi.wh_name, members.nama as nama_member,members.cni_id, members.cni_id_ref from transaksi_detail left join transaksi on transaksi.id_transaksi = transaksi_detail.id_trans left join members on members.id_member = transaksi.id_member where 1=1 ";

        if (!empty($from)) {
            $sql .= " and to_char(transaksi.created_at, 'YYYY-MM-DD') >= '" . $from . "' and to_char(transaksi.created_at, 'YYYY-MM-DD') <= '" . $to . "'";
        }

        $_dataa = DB::select(DB::raw($sql));

        $count = count($_dataa);

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $sql .= " order by $sort_column limit $per_page offset $offset";
            $_data = DB::select(DB::raw($sql));
            $nama_ref = array();
            $sql_member = '';
            $sql_member = "select nama as nama_member, cni_id from members where type = 1";
            $data_member = DB::select(DB::raw($sql_member));
            $count_members = count($data_member);
            if ((int)$count_members > 0) {
                foreach ($data_member as $dm) {
                    $nama_ref[$dm->cni_id] = $dm->nama_member;
                }
            }
            $i = 1;
            foreach ($_data as $d) {
                $jml = (int)$d->jml;
                $d->no = $i++;
                $d->ttl_pv = $jml * $d->pv;
                $d->ttl_rv = $jml * $d->rv;
                $hrg_nonppn = (int)$d->type_member == 1 ? $d->hm_non_ppn : $d->hk_non_ppn;
                $d->ttl_hrg_non_ppn = $jml * $hrg_nonppn;
                unset($d->pv);
                unset($d->rv);
                $d->nama_ref = isset($nama_ref[$d->cni_id_ref]) && (int)$d->type_member === 1 ? $nama_ref[$d->cni_id_ref] : '';
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

    public function export_header(Request $request)
    {
        $tgl = Carbon::now();
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $status = (int)$request->status >= 0 ? (int)$request->status : -1;
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;

        $from = !empty($request->start_date) ? date('Y-m-d', strtotime($request->start_date)) : '';
        $to = !empty($request->end_date) ? date('Y-m-d', strtotime($request->end_date)) : $from;
        $from = empty($from) && !empty($to) ? $to : $from;
        $to = empty($to) && !empty($from) ? $from : $to;

        $count = 0;
        $_data = array();
        $data = null;
        $sql = '';
        $sql = "select transaksi.id_transaksi,transaksi.created_at,transaksi.type_member,transaksi.payment_name,
       members.nama as nama_member,transaksi.pot_voucher,transaksi.iddc,transaksi.wh_name,ttl_pv,ttl_rv,
       ttl_disc,jdp,members.cni_id, members.cni_id_ref, transaksi.status, transaksi.logistic_name, transaksi.jdp,
       transaksi.ongkir, transaksi.key_payment, transaksi.ewallet, transaksi.nominal_doku, transaksi.payment_date,
       transaksi.ttl_belanjaan as ttl_cb, transaksi.payment_channel from transaksi
				left join members on members.id_member = transaksi.id_member where 1=1 ";

        if ($status >= 0) {
            $sql .= " and transaksi.status = " . $status;
        }
        if (!empty($from)) {
            $sql .= " and to_char(transaksi.created_at, 'YYYY-MM-DD') >= '" . $from . "' and to_char(transaksi.created_at, 'YYYY-MM-DD') <= '" . $to . "'";
        }

        $_dataa = DB::select(DB::raw($sql));
        $count = count($_dataa);

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $sql .= " order by $sort_column";
            $_data = DB::select(DB::raw($sql));
            $nama_ref = array();
            $sql_member = '';
            $sql_member = "select nama as nama_member, cni_id from members where type = 1";
            $data_member = DB::select(DB::raw($sql_member));
            $count_members = count($data_member);
            if ((int)$count_members > 0) {
                foreach ($data_member as $dm) {
                    $nama_ref[$dm->cni_id] = $dm->nama_member;
                }
            }

            $pc = array(29, 32, 33, 34, 36);
            foreach ($_data as $d) {
                $created_at = !empty($d->created_at) ? date('d/m/Y', strtotime($d->created_at)) : '';
                $payment_date = !empty($d->payment_date) ? date('d/m/Y', strtotime($d->payment_date)) : '';
                $jdp = 1 * $d->jdp;
                $ttl_cb = 1 * $d->ttl_cb;
                $nominal_doku = 1 * $d->nominal_doku;
                $ewallet = 1 * $d->ewallet;
                $ongkir = 1 * $d->ongkir;
                $ttl_pv = 1 * $d->ttl_pv;
                $ttl_rv = 1 * $d->ttl_rv;
                $ttl_disc = 1 * $d->ttl_disc;
                $pot_voucher = 1 * (int)$d->pot_voucher;
                $jpayment = $d->ongkir + $d->ttl_cb - $pot_voucher;
                $cni_id_ref = !empty($d->cni_id_ref) ? $d->cni_id_ref : '-';
                $key_payment = in_array((int)$d->payment_channel, $pc) ? (int)$d->key_payment : '';
                unset($d->jdp);
                unset($d->ttl_cb);
                unset($d->nominal_doku);
                unset($d->created_at);
                unset($d->payment_date);
                unset($d->ewallet);
                unset($d->ongkir);
                unset($d->ttl_pv);
                unset($d->ttl_rv);
                unset($d->ttl_disc);
                unset($d->key_payment);
                unset($d->cni_id_ref);
                $status_name = '-';
                if ((int)$d->status == 0) $status_name = "Waiting Payment";
                if ((int)$d->status == 1) $status_name = "Payment Completed";
                if ((int)$d->status == 2) $status_name = "Expired";
                if ((int)$d->status == 3) $status_name = "On Process";
                if ((int)$d->status == 95678) $status_name = "Hold";
                if ((int)$d->status == 4) $status_name = "Dikirim";
                if ((int)$d->status == 5) $status_name = "Completed";
                $jpp = $ttl_cb - $jdp;
                $d->ttl_cb = $ttl_cb;
                $d->jpp = $jpp;
                $d->jdp = $jdp;
                $d->nominal_doku = $nominal_doku;
                $d->ewallet = (int)$ewallet > 0 ? $ewallet : "";
                $d->ongkir = (int)$ongkir > 0 ? $ongkir : "";
                $d->ttl_pv = $ttl_pv;
                $d->ttl_rv = $ttl_rv;

                //$d->ttl_disc = number_format($ttl_disc,2,",",".");
                $d->jpayment = $jpayment;
                $d->created_at = $created_at;
                $d->payment_date = $payment_date;
                $d->key_payment = $key_payment;
                $d->cni_id_ref = $cni_id_ref;
                $d->nama_ref = isset($nama_ref[$cni_id_ref]) && (int)$d->type_member === 1 ? $nama_ref[$cni_id_ref] : '-';
                $d->status_name = $status_name;
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

    function export_detail(Request $request)
    {
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;

        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;

        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_trans';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;

        $column_int = array("id_trans");
        if (in_array($sort_column, $column_int)) $sort_column = 'transaksi_detail.' . $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;

        $from = !empty($request->start_date) ? date('Y-m-d', strtotime($request->start_date)) : '';
        $to = !empty($request->end_date) ? date('Y-m-d', strtotime($request->end_date)) : $from;
        $from = empty($from) && !empty($to) ? $to : $from;
        $to = empty($to) && !empty($from) ? $from : $to;

        $count = 0;
        $_count = 0;
        $_data = array();
        $whereIn = array();
        $data = null;
        $sql = '';

        $sql = "select
			transaksi_detail.id_trans as id_transaksi,
			transaksi_detail.ttl_harga,
			transaksi_detail.kode_produk,pv,rv,ppn_hk,ppn_hm,
			transaksi_detail.jml,hm_non_ppn,hk_non_ppn,
			transaksi.type_member,
			transaksi.created_at,
			transaksi.payment_name,
			transaksi.key_payment,
			transaksi.payment_channel,
			transaksi.iddc,
			transaksi.wh_name,
			transaksi.status,
			transaksi.logistic_name,
			members.nama as nama_member,
			members.cni_id, members.cni_id_ref from transaksi_detail left join transaksi on transaksi.id_transaksi = transaksi_detail.id_trans left join members on members.id_member = transaksi.id_member where 1=1 ";

        if (!empty($from)) {
            $sql .= " and to_char(transaksi.created_at, 'YYYY-MM-DD') >= '" . $from . "' and to_char(transaksi.created_at, 'YYYY-MM-DD') <= '" . $to . "'";
        }

        $_dataa = DB::select(DB::raw($sql));
        $count = count($_dataa);

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $sql .= " order by $sort_column";
            $_data = DB::select(DB::raw($sql));
            $nama_ref = array();
            $sql_member = '';
            $sql_member = "select nama as nama_member, cni_id from members where type = 1";
            $data_member = DB::select(DB::raw($sql_member));
            $count_members = count($data_member);
            if ((int)$count_members > 0) {
                foreach ($data_member as $dm) {
                    $nama_ref[$dm->cni_id] = $dm->nama_member;
                }
            }
            $i = 1;
            $pc = array(29, 32, 33, 34, 36);
            foreach ($_data as $d) {
                $jml = (int)$d->jml;
                $d->no = $i++;
                $d->ttl_pv = $jml * $d->pv;
                $d->ttl_rv = $jml * $d->rv;
                $hrg_nonppn = (int)$d->type_member == 1 ? $d->hm_non_ppn : $d->hk_non_ppn;
                $d->ttl_hrg_non_ppn = $jml * $hrg_nonppn;
                $key_payment = in_array((int)$d->payment_channel, $pc) ? (int)$d->key_payment : '';
                $ttl_harga = 1 * $d->ttl_harga;
                $cni_id_ref = !empty($d->cni_id_ref) ? $d->cni_id_ref : '-';
                $pajak = (int)$d->type_member == 1 ? $d->ppn_hm : $d->ppn_hk;
                $ttl_pajak = $jml * $pajak;
                $status_name = '-';
                if ((int)$d->status == 0) $status_name = "Waiting Payment";
                if ((int)$d->status == 1) $status_name = "Payment Completed";
                if ((int)$d->status == 2) $status_name = "Expired";
                if ((int)$d->status == 3) $status_name = "On Process";
                if ((int)$d->status == 95678) $status_name = "Hold";
                if ((int)$d->status == 4) $status_name = "Dikirim";
                if ((int)$d->status == 5) $status_name = "Completed";
                $created_at = !empty($d->created_at) ? date('d/m/Y', strtotime($d->created_at)) : '';
                unset($d->pv);
                unset($d->rv);
                unset($d->hm_non_ppn);
                unset($d->hk_non_ppn);
                unset($d->created_at);
                unset($d->ttl_harga);
                $d->status_name = $status_name;
                $d->key_payment = $key_payment;
                $d->cni_id_ref = $cni_id_ref;
                $d->created_at = $created_at;
                $d->ttl_harga = $ttl_harga;
                $d->ttl_pajak = round($ttl_pajak, 2);
                $d->nama_ref = isset($nama_ref[$cni_id_ref]) && (int)$d->type_member === 1 ? $nama_ref[$cni_id_ref] : '-';
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
}
