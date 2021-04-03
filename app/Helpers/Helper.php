<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class Helper
{

    static function last_login($id_member = 0)
    {
        $tgl = date('Y-m-d H:i:s');
        DB::table('members')->where('id_member', $id_member)->update(['last_login' => $tgl]);
        return $id_member;
    }

    static function get_ewallet($cni_id = "")
    {
        $url = env('URL_GET_EWALLET');
        $token = env('TOKEN_GET_EWALLET');
        $curl = curl_init();
        $postfields = array(
            "nomorn" => $cni_id,
            "token" => $token
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res =  json_decode($response);
        $dt = array(
            "result"        => $res[0]->RESULT,
            "message"       => $res[0]->Message,
            "saldo"         => !empty($res[0]->Saldo) || (int)$res[0]->Saldo > 0 ? $res[0]->Saldo : 0,
            "statuswallet"  => $res[0]->statuswallet
        );
        return $dt;
    }

    static function send_sms($nohp = "", $otp = "")
    {
        $url = env('URL_SEND_SMS');
        $token = env('TOKEN_SEND_SMS');
        $curl = curl_init();
        $postfields = array(
            "nomorn"    => "",
            "token"     => $token,
            "nohp"      => $nohp,
            "otp"       => $otp
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postfields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res =  json_decode($response);
        return $postfields;
    }

    static function upd_stok($data = array())
    {
        for ($i = 0; $i < count($data); $i++) {
            $id_product = 0;
            $id_product = $data[$i]['id_product'];
            DB::table('product')->where('id_product', $id_product)->update(['qty' => $data[$i]['qty']]);
        }
        return true;
    }
}
