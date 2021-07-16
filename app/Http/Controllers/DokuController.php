<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\Helper;

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
		$tgl = date('Y-m-d H:i:s');
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
		$id_transaksi = 0;
		$expired_payment = date("Y-m-d H:i", strtotime($data->expired_payment));
		
		$xml = new \SimpleXMLElement('<INQUIRY_RESPONSE/>');
        $xml_item = $xml->addChild('INQUIRY_RESPONSE');
		if($expired_payment <= $tgl){
			$xml_item->addChild('RESPONSECODE', '08');
			$xml_item->addChild('MESSAGE', 'Transaction expired');
			$res = $xml_item->asXML();
			return response($res);
			return false;
		}
		if(!empty($data) && (int) $data->id_transaksi > 0){
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
		}

        $tgl = date('YmdHis');
        
		if((int)$id_transaksi > 0){
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
		}else{
			$xml_item->addChild('MESSAGE', 'Transaction no found');
		}
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
		Log::info($id_transaksi);
		$status = $request->RESULTMSG;
		$status_code = $request->RESPONSECODE;
		$bank_issuer = $request->BANK;
		$brand_cc = $request->BRAND;		
		$payment_date = !empty($request->PAYMENTDATETIME) ? date('Y-m-d H:i:s', strtotime($request->PAYMENTDATETIME)) : '';
		$where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
		$count = DB::table('transaksi')->where($where)->count();
		if((int)$count > 0){
			$data_s = serialize($input);
			if((int)$status_code == 0){
				$dt_trans = '';
				$dt_members = '';
				$dt_trans = DB::table('transaksi')->where($where)->first();
				$ewallet = isset($dt_trans->ewallet) && (int)$dt_trans->ewallet > 0 ? (int)$dt_trans->ewallet : 0;		
				$ttl_price = isset($dt_trans->ttl_price) && (int)$dt_trans->ttl_price > 0 ? (int)$dt_trans->ttl_price : 0;	
				
				$id_member = $dt_trans->id_member;
				$dt_members = DB::table('members')->where(array("id_member"=>$id_member))->first();
				$tipe_member = (int)$dt_members->type;
				if($ewallet > 0){					
					$sub_ttl = $dt_trans->ttl_price;					
					$cni_id = isset($dt_members->cni_id) && (int)$dt_members->cni_id > 0 ? (int)$dt_members->cni_id : 0;
					Helper::trans_ewallet("PAID_EWALLET",$cni_id, $sub_ttl,$ewallet,$id_transaksi,$input,"doku/notify",1,'',0,$id_member);
				}
				$gen_cni_id = Helper::send_order_cni($id_transaksi,'doku');
				if($ttl_price >= 1000000 && $tipe_member == 2){					
					$start_member = date('Y-m-d');
					$_end_member = date('Y-m-d', strtotime('+1 years'));
					$end_member = date("Y-m-t", strtotime($_end_member));
					$upd_member = array();
					$upd_member = array(
						'cni_id'		=> $gen_cni_id,
						'start_member'	=> $start_member,
						'end_member'	=> $end_member,
						'tipe_member'	=> 1,
					);
					DB::table('members')->where('id_member', $id_member)->update($upd_member);
				}
				$data = array();           
				$data = array(            
					'payment_date'  => $payment_date,
					'status'   		=> 1,
					'bank_issuer'  	=> $bank_issuer,
					'brand_cc'   	=> $brand_cc,
					'expired_payment' => null,
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
	
	function notify_qris(Request $request)
    {
        // $raw_notification = json_decode(file_get_contents('php://input'), true);
        // Log::info('raw_notification');
        // Log::info($raw_notification);
        $input  = $request->all();
        Log::info('inputan notify QRIS');
        Log::info($input);
		$_id_transaksi = $request->TRANSACTIONID;
		$status = $request->TXNSTATUS;			
		$payment_date = !empty($request->TXNDATE) ? date('Y-m-d H:i:s', strtotime($request->TXNDATE)) : '';
		
		$base64 = base64_decode($_id_transaksi);
		$vowels = array("n", "c", "i", "C", "I", "N");
		$id_transaksi = str_replace($vowels,"",$base64);
		Log::info($id_transaksi);
		$where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
		Log::info($where);
		$count = DB::table('transaksi')->where($where)->count();
		if((int)$count > 0){
			$data_s = serialize($input);
			if((int)$status == "S"){
				$dt_trans = '';
				$dt_members = '';
				$dt_trans = DB::table('transaksi')->where($where)->first();
				$ewallet = isset($dt_trans->ewallet) && (int)$dt_trans->ewallet > 0 ? (int)$dt_trans->ewallet : 0;
				$ttl_price = isset($dt_trans->ttl_price) && (int)$dt_trans->ttl_price > 0 ? (int)$dt_trans->ttl_price : 0;	
				
				$id_member = $dt_trans->id_member;
				$dt_members = DB::table('members')->where(array("id_member"=>$id_member))->first();
				$tipe_member = (int)$dt_members->type;
				if($ewallet > 0){					
					$sub_ttl = $dt_trans->ttl_price;					
					$cni_id = isset($dt_members->cni_id) && (int)$dt_members->cni_id > 0 ? (int)$dt_members->cni_id : 0;
					Helper::trans_ewallet("PAID_EWALLET",$cni_id, $sub_ttl,$ewallet,$id_transaksi,$input,"doku/notify",1,'',0,$id_member);
				}
				$gen_cni_id = Helper::send_order_cni($id_transaksi,'doku');
				if($ttl_price >= 1000000 && $tipe_member == 2){
					//$gen_cni_id = 'CMS'.rand(10000, 99999);
					$start_member = date('Y-m-d');
					$_end_member = date('Y-m-d', strtotime('+1 years'));
					$end_member = date("Y-m-t", strtotime($_end_member));
					$upd_member = array();
					$upd_member = array(
						'cni_id'		=> $gen_cni_id,
						'start_member'	=> $start_member,
						'end_member'	=> $end_member,
						'tipe_member'	=> 1,
					);
					DB::table('members')->where('id_member', $id_member)->update($upd_member);
				}
				$data = array();           
				$data = array(            
					'payment_date'  => $payment_date,
					'status'   		=> 1,					
					'expired_payment' => null,
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
	
	function generate_words(Request $request){
		$clientId = $request->clientId;
		$clientSecret = $request->clientSecret;
		$Sharedkey = $request->Sharedkey;
		$systrace = $request->systrace;
		$format_result = $request->format_result;
		$generate = (int)$request->generate;
		$words_ori = $clientId . '' . $Sharedkey .''. $systrace;
		if($generate > 0) $words_ori = $clientId .''. $systrace.''.$clientId.''.$Sharedkey;
		$words = '';
		$format = '';
		if($format_result == 2) {
			$format = "HSMAC SHA1 => hash_hmac('sha1', $words_ori,'b7cfdcfc247f11fcffde5b2b784f5381')";
			$words = hash_hmac('sha1', $words_ori,'b7cfdcfc247f11fcffde5b2b784f5381');
		}
		if($format_result == 1) {
			$format = "SHA1 => sha1($words_ori)";
			$words = sha1($words_ori);
		}
		$result = array();
		$result = array(
			'clientId'	=> $clientId,
			'Sharedkey'	=> $Sharedkey,
			'systrace'	=> $systrace,
			'words_ori'	=> $words_ori,
			'format'	=> $format,
			'words'		=> $words
		);
		return response($result);
	}
	
	// function payment_cc($id_transaksi=0){
		// $id_transaksi = isset($request->id_transaksi) ? (int)$request->id_transaksi : 0;
		// $where = array('transaksi.status' => 0, 'id_transaksi' => $id_transaksi);
		// $data = DB::table('transaksi')->select(
            // 'transaksi.*',
            // 'members.nama as nama_member',
            // 'members.email'
        // )
            // ->where($where)
            // ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')->first();
		
		// $id_transaksi = $data->id_transaksi;
        // $ttl_price = $data->nominal_doku;
		// $MALLID = env('MALLID_CC');
		// $shared_key = env('SHAREDKEY');
        // $words = $ttl_price . '' . $MALLID . ''.$shared_key. '' . $id_transaksi;
		// $basket = 'Paket ' . $data->nama_member . ' No. Order #' . $id_transaksi . ',' . number_format($ttl_price, 2, ".", "") . ',1,' . number_format($ttl_price, 2, ".", "");
		// $res = array(
			// 'mall_id'		=> $MALLID,
			// 'nama_member'	=> $data->nama_member,
			// 'email'			=> $data->email,
			// 'basket'		=> $data->basket,
			// 'ttl_price'		=> $data->ttl_price,
			// 'words'			=> sha1($words),
			// 'tgl'			=> date('YmdHis'),
			// 'session_id'	=> $data->session_id,
		// );
	// }
	
	// return view('greeting',$res);
}
