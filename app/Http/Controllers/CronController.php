<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CronController extends Controller
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

    public function index()
    {
        $where = array();
        $data_ewallet = null;
        $tgl = date('Y-m-d H:i:s');
        Log::info("cron_refund_ewallet on : " . $tgl);
        $where = array('refund_ewallet.status' => 0);
        $_data = DB::table('refund_ewallet')->select(
            'refund_ewallet.*',
            'members.cni_id'
        )
            ->where($where)
            ->leftJoin('members', 'members.id_member', '=', 'refund_ewallet.id_member')->first();
        $id_transaksi = isset($_data->id_transaksi) && (int)$_data->id_transaksi > 0 ? $_data->id_transaksi : 0;
        if ($id_transaksi > 0) {
            $id_member = $_data->id_member;
            $sub_ttl = $_data->ttl_price;
            $ewallet = $_data->ewallet;
            $cni_id = isset($_data->cni_id) && (int)$_data->cni_id > 0 ? (int)$_data->cni_id : 0;
            $ket = "";
            $ket = "Pengembalian dana ewallet transaksi #" . $id_transaksi;
            $data_ewallet = Helper::trans_ewallet("REALLOCATE_EWALLET", $cni_id, $sub_ttl, $ewallet, $id_transaksi, null, "cron_refund_ewallet", 1, $ket, 1, $id_member);
            $wheree = array();

            if (isset($data_ewallet['result']) && $data_ewallet['result'] == "Y") {
                $wheree = array('id_member' => $id_member, 'id_transaksi' => $id_transaksi);
                DB::table('refund_ewallet')->where($wheree)->update(array("status" => 1, "updated_at" => $tgl));
            }
        }
        $where = array('unflag_voucher.status' => 0);
        $dataVoucher = DB::table('unflag_voucher')->where($where)->first();
        $id_trans = isset($dataVoucher->id_transaksi) && (int)$dataVoucher->id_transaksi > 0 ? $dataVoucher->id_transaksi : 0;
        if ($id_trans > 0) {
            Helper::unflagVoucher($dataVoucher->cni_id, $dataVoucher->kodevoucher, $dataVoucher->custid, $id_trans);
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data_transaksi' => $_data,
            'data_ewallet' => $data_ewallet
        );
        return response($result);
    }

    public function cron_send_email()
    {
        $tgl = date('Y-m-d H:i:s');
        $dataCnt = DB::table('cron_email')->where('status', 1)->limit(10)->count();
        if ((int)$dataCnt > 0) {
            $setting = DB::table('setting')->get()->toArray();
            $out = array();
            if (!empty($setting)) {
                foreach ($setting as $val) {
                    $out[$val->setting_key] = $val->setting_val;
                }
            }
            $data = DB::table('cron_email')->where('status', 1)->limit(10)->get();
            foreach ($data as $d) {
                $data_email = array();
                $data_email['nama'] = $d->nama;
                $data_email['email'] = $d->email;
                $data_email['subject'] = $d->subject;
                $data_email['content_email'] = $d->content_email;
                try {
                    Mail::send([], ['users' => $data_email], function ($message) use ($data_email) {
                        $message->to($data_email['email'], $data_email['nama'])->subject($data_email['subject'])->setBody($data_email['content_email'], 'text/html');
                    });
                    if (count(Mail::failures()) > 0) {

                        $output = "There was one or more failures. They were: \n";
                        foreach (Mail::failures() as $email_address) {
                            $output = $output . $email_address . "\n";
                        }
                        Log::error('failed send email => ' . $output);
                    } else {
                        Log::info('ok send email => ' . $d->id_transaksi);
                        $where = array('id_transaksi' => $d->id_transaksi, 'status' => 1, 'id_member' => $d->id_member);
                        DB::table('cron_email')->where($where)->update(array("status" => 2, "updated_at" => $tgl));
                    }

                } catch (Exception $e) {
                    Log::error('start cron Email :' . $d->id_transaksi);
                    Log::error($e->getMessage());
                    Log::error('end cron Email :' . $d->id_transaksi);
                }
            }
        }

    }
}
