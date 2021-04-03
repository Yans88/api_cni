<?php

use Illuminate\Support\Facades\DB;
$setting = DB::table('setting')->get()->toArray();
$out = array();
if(!empty($setting)){
    foreach($setting as $val){
        $out[$val->setting_key] = $val->setting_val;
    }
}
return [
    'driver' => env('MAIL_DRIVER'),
    'host' => env('MAIL_HOST'),
    'port' => env('MAIL_PORT'),
    'from' => [
        'address' => $out['send_mail'],
        'name' => env('MAIL_FROM_NAME'),
    ],
    'encryption' => env('MAIL_ENCRYPTION'),
    'username' => $out['send_mail'],
    'password' => $out['mail_pass'],
    'markdown' => [
        'theme' => 'default',
        'paths' => [
             resource_path('views/vendor/mail'),
        ],
    ],
];
?>