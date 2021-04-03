<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DokuController extends Controller
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

    public function redirect_va(Request $request)
    {
        // $raw_notification = json_decode(file_get_contents('php://input'), true);
        // $input  = $request->all();
        // Log::info('raw_notification');
        // Log::info($raw_notification);
        // Log::info('inputan');
        // Log::info($input);
        $PAYMENTCODE = $request->PAYMENTCODE;
        $MALLID = $request->MALLID;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = array('transaksi.status' => 0, 'key_payment' => $PAYMENTCODE, 'payment' => 2);
        $data = array();
        $data = DB::table('transaksi')->select(
            'transaksi.*',
            'members.nama as nama_member',
            'members.email'
        )
            ->where($where)
            ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
            ->orderByRaw($sort_column)->first();
        $id_transaksi = $data->id_transaksi;
        $ttl_price = $data->nominal_doku;
        $words = $ttl_price . '' . $MALLID . 'NRd509eQng1F' . '' . $id_transaksi;
        $charactersLength = strlen($words);
        $randomString = '';
        for ($i = 0; $i < 20; $i++) {
            $randomString .= $words[rand(0, $charactersLength - 1)];
        }

        $basket = 'Paket ' . $data->nama_member . ' No. Order #' . $id_transaksi . ',' . number_format($ttl_price, 2, ".", "") . ',1,' . number_format($ttl_price, 2, ".", "");
        // $details =  DB::table('transaksi_detail')->where(array('id_trans' => $id_transaksi))->get();
        // $i = 1;
        // $jml_data = count($details);
        // if (count($details) > 0) {
        //     foreach ($details as $row) {
        //         if ($jml_data == $i) {
        //             $basket .= $row->product_name . ',' . number_format($row->harga, 2, ".", "") . ',' . $row->jml . ',' . number_format($row->ttl_harga, 2, ".", "");
        //         } else {
        //             $basket .= $row->product_name . ',' . number_format($row->harga, 2, ".", "") . ',' . $row->jml . ',' . number_format($row->ttl_harga, 2, ".", "") . ';';
        //         }
        //         $i++;
        //     }
        // }

        $tgl = date('YmdHis');
        $xml = new \SimpleXMLElement('<INQUIRY_RESPONSE/>');
        $xml_item = $xml->addChild('INQUIRY_RESPONSE');
        $xml_item->addChild('PAYMENTCODE', $PAYMENTCODE);
        $xml_item->addChild('AMOUNT', $ttl_price . '.00');
        $xml_item->addChild('PURCHASEAMOUNT', $ttl_price . '.00');
        $xml_item->addChild('TRANSIDMERCHANT', $id_transaksi);
        $xml_item->addChild('WORDS', sha1($words));
        $xml_item->addChild('REQUESTDATETIME', $tgl);
        $xml_item->addChild('CURRENCY', 360);
        $xml_item->addChild('PURCHASECURRENCY', 360);
        $xml_item->addChild('SESSIONID', $data->session_id);
        $xml_item->addChild('NAME', $data->nama_member);
        $xml_item->addChild('EMAIL', $data->email);
        $xml_item->addChild('BASKET', $basket);
        $res = $xml_item->asXML();
        // $result = array(
        //     'err_code'      => '00',
        //     'err_msg'          => 'ok',
        //     'data_xml'      => $xml_item->asXML(),

        // );
        return response($res);
    }

    function notify(Request $request)
    {
        // $raw_notification = json_decode(file_get_contents('php://input'), true);
        // Log::info('raw_notification');
        // Log::info($raw_notification);
        $input  = $request->all();
        Log::info('inputan notify doku');
        Log::info($input);
		$id_transaksi = $request->TRANSIDMERCHANT;
		$status = $request->RESULTMSG;
		$status_code = $request->RESPONSECODE;
		$bank_issuer = $request->BANK;
		$brand_cc = $request->BRAND;		
		$payment_date = !empty($request->PAYMENTDATEANDTIME) ? date('Y-m-d H:i:s', strtotime($request->PAYMENTDATEANDTIME)) : '';
		$where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
		$count = DB::table('transaksi')->where($where)->count();
		if((int)$count > 0){
			$data_s = serialize($input);
			if((int)$status_code == 0){
				$data = array();           
				$data = array(            
					'payment_date'  => $payment_date,
					'status'   		=> 1,
					'bank_issuer'  	=> $bank_issuer,
					'brand_cc'   	=> $brand_cc,
					'log_payment'	=> $data_s
				);
				DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($data);
				echo 'Transaction #'.$id_transaksi. ': '.$status;
				echo '<script>console.log(\'RECEIVEOK\');</script>';
			}else{
				echo 'Transaction #'.$id_transaksi.' Failed : '.$status;
				echo '<script>console.log(\'RECEIVEFALSE\');</script>';
			}			
		}else{
			echo 'Transaction #'.$id_transaksi.' Not Found : '.$status;
			echo '<script>console.log(\'RECEIVEFALSE\');</script>';
		}
		echo "CONTINUE";
        //return response($raw_notification);
    }
}
