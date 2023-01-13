<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Members;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
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
        $type = (int)$request->type > 0 ? (int)$request->type : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'nama';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = array();
        $where = array('deleted_at' => null);
        if ((int)$type > 0) {
            $where += array('type' => $type);
        }
        $count = 0;
        $data = null;
        if (!empty($keyword)) {
            $data = DB::table('members')->where($where)->whereRaw("(LOWER(nama) like '%" . $keyword . "%' or cni_id like '%" . $keyword . "%')")->get()->toArray();
            $count = count($data);
        } else {
            $count = Members::where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $data = Members::where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ((int)$count > 0) {
            foreach ($data as $d) {
                unset($d->ewallet);
                $_type = (int)$d->type;
                $cni_id = !empty($d->cni_id) ? $d->cni_id : '';
                $res = 0;
                // if ($_type == 1 && !empty($cni_id)) {
                // $data_ewallet = Helper::get_ewallet($cni_id);
                // $res = $data_ewallet['saldo'];
                // }
                unset($d->ewallet);
                // $d->ewallet = $res;
                $_data[] = $d;
            }

            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $_data
            );
        }
        return response($result);
    }

    function detail(Request $request)
    {
        $id_member = (int)$request->id_member;
        $id_token = (int)$request->id_token_fcm > 0 ? (int)$request->id_token_fcm : 0;
        $where = ['deleted_at' => null, 'id_member' => $id_member];
        $count = Members::where($where)->count();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => $id_member
        );
        $last_month_member = 0;
        $is_grace_periode = 0;
        if ((int)$count > 0) {

            $wheree = array('history_notif.id_member' => $id_member, 'unread' => 1);
            $cnt_unread = DB::table('history_notif')->where($wheree)->count();

            Helper::last_login($id_member);
            $data = Members::where($where)->first();
            $photo = !empty($data->photo) ? env('APP_URL') . '/api_cni/uploads/members/' . $data->photo : '';
            $type = (int)$data->type;
            $cni_id = !empty($data->cni_id) ? $data->cni_id : '';
            $res = 0;
            // if ($type == 1 && !empty($cni_id)) {
            // $data_ewallet = Helper::get_ewallet($cni_id,$request->all(),'profile_member');
            // $res = $data_ewallet['saldo'];
            // }
            $last_month_member_date = '';
            $tgl = date('Y-m-d');
            if ($type == 2 && !empty($cni_id)) {
                $end_date = date('Y-m-d', strtotime($data->end_member));
                if ($tgl > $end_date) {
                    $date1 = date_create($end_date);
                    $date2 = date_create($tgl);
                    $diff = date_diff($date1, $date2);
                    $is_grace_periode = 16 - (int)$diff->format("%R%a");
                }
            }
            if ($type == 1) {
                $end_date = date('Y-m-d', strtotime($data->end_member));
                $last_month_member_date = date('Y-m-d', strtotime("-1 months", strtotime($end_date)));
                if ($tgl >= $last_month_member_date && $tgl <= $end_date) $last_month_member = 1;
                if ($tgl > $end_date) {
                    $date1 = date_create($end_date);
                    $date2 = date_create($tgl);
                    $diff = date_diff($date1, $date2);
                    $is_grace_periode = 16 - (int)$diff->format("%R%a");
                    $dataa = array("type" => 2, "updated_at" => date('Y-m-d H:i:s'));
                    DB::table('members')->where('id_member', $id_member)->update($dataa);
                    $data = Members::where($where)->first();
                }
            }
            if ($type == 3) {
                $end_date = date('Y-m-d', strtotime($data->end_member));
                if ($tgl > $end_date) {
                    $date1 = date_create($end_date);
                    $date2 = date_create($tgl);
                    $diff = date_diff($date1, $date2);
                    $is_grace_periode = 16 - (int)$diff->format("%R%a");
                    $dataa = array("type" => 2, "updated_at" => date('Y-m-d H:i:s'));
                    DB::table('members')->where('id_member', $id_member)->update($dataa);
                    $data = Members::where($where)->first();
                }
            }
            if ($id_token > 0) {
                $fcm_token = DB::table('fcm_token')->where(array('id_token_fcm' => $id_token))->first();
            }
            unset($data->photo);
            unset($data->ewallet);
            $data->id_token_fcm = $id_token;
            $data->token_fcm = isset($fcm_token->token_fcm) ? $fcm_token->token_fcm : '';
            $data->photo = $photo;
            $data->ewallet = 0;
            $data->last_month_member_date = $last_month_member_date;
            $data->last_month_member = $last_month_member;
            $data->is_grace_periode = $is_grace_periode;
            $data->cnt_unread = $cnt_unread;
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        }
        return response($result);
    }

    function reg(Request $request)
    {
        $ptn = "/^0/";
        $rpltxt = "62";
        $tgl = date('Y-m-d H:i:s');
        $data = new Members();
        $data->cni_id = $request->cni_id;
        $data->email = !empty($request->email) ? strtolower($request->email) : '';
        $data->phone = !empty($request->phone) ? preg_replace($ptn, $rpltxt, $request->phone) : '';
        $data->cni_id_ref = $request->cni_id_ref;
        $data->nama = $request->nama;
        $data->type = (int)$request->type;
        if ($request->has('id_sm') && !empty($request->id_sm)) $data->id_sm = $request->id_sm;
        if ($request->has('tipe_sm') && (int)$request->id_sm > 0) $data->tipe_sm = (int)$request->tipe_sm;
        if ($request->has('end_member') && !empty($request->end_member)) $data->end_member = date('Y-m-d H:i:s', strtotime($request->end_member));
        $data->ewallet = 0;
        $verify_code = rand(1000, 9999);
        $verify_code = Crypt::encryptString($verify_code);
        $data->verify_phone = (int)$data->type == 2 ? $verify_code : 1;
        $data->verify_email = (int)$data->type == 2 ? $verify_code : 1;
        $data->pass = Crypt::encryptString(strtolower($request->pass));
        $data->created_at = $tgl;
        $data->updated_at = $tgl;
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'not found',
            'data' => null
        );
        if (empty($data->cni_id) && $data->type == 1) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'cni_id is required',
                'data' => null
            );
            return response($result);
            return false;
        }

        if (empty($data->email)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'email is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'email invalid format',
                'data' => null
            );
            return response($result);
            return false;
        }

        if (empty($data->phone)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'phone is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $count = 0;
        if ($data->type == 1) {
            $where = ['deleted_at' => null, 'cni_id' => $data->cni_id];
            $count = Members::where($where)->count();
            if ($count > 0) {
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'cni_id already exist',
                    'data' => null
                );
                return response($result);
                return false;
            }
        }
        $where = ['deleted_at' => null, 'email' => $data->email];
        $count = Members::where($where)->count();
        if (!empty($count)) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'email already exist',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = ['deleted_at' => null, 'phone' => $data->phone];
        $count = Members::where($where)->count();
        if (!empty($count)) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'phone already exist',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ((int)$data->type != 2) $data->status = 1;
        $save = $data->save();
        if ((int)$data->type == 2) {
            Helper::send_sms($data->phone, $verify_code, $request->all(), 'register_member');
            if ($save) {
                $setting = DB::table('setting')->get()->toArray();
                $out = array();
                if (!empty($setting)) {
                    foreach ($setting as $val) {
                        $out[$val->setting_key] = $val->setting_val;
                    }
                }
                $content_member = $out['content_reg'];
                $content = str_replace('[#name#]', $data->nama, $content_member);
                $content = str_replace('[#kode_otp#]', Crypt::decryptString($verify_code), $content);
                $data->content = $content;
                Mail::send([], ['users' => $data], function ($message) use ($data) {
                    $message->to($data->email, $data->nama)->subject('Register')->setBody($data->content, 'text/html');
                });
            }
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function edit(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member;
        Helper::last_login($id_member);
        $result = array();
        if ($id_member > 0) {
            $data = Members::where('id_member', $id_member)->first();
            $data->nama = $request->nama;
            $data->updated_at = $tgl;
            $data->updated_by = $id_member;
            $data->save();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
        }
        return response($result);
    }

    function login_member(Request $request)
    {
        $count = 0;
        $email = !empty($request->email) ? strtolower($request->email) : '';
        $cni_id = $request->cni_id;
        $pass = strtolower($request->pass);
        $result = array();
        if (empty($email) && empty($cni_id)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'cni_id or email is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if (empty($pass)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'password is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = ['deleted_at' => null, 'email' => $email];
        $whereIn = array(2);
        if (!empty($cni_id)) {
            $where = ['deleted_at' => null, 'cni_id' => $cni_id];
            $whereIn = array(1, 3);
        }

        $count = Members::where($where)->whereIn('type', $whereIn)->count();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'incorrect email or password',
            'data' => null
        );
        if ($count > 0) {
            $data = Members::where($where)->whereIn('type', $whereIn)->first();
            $password = Crypt::decryptString($data->pass);
            if ($pass == $password) {
                unset($data->password);
                unset($data->ewallet);
                //$type = (int)$data->type;
                //$cni_id = !empty($data->cni_id) ? $data->cni_id : '';
                $res = 0;
                // if ($type == 1 && !empty($cni_id)) {
                // $data_ewallet = Helper::get_ewallet($cni_id, $request->all(),'login_member');
                // $res = $data_ewallet['saldo'];
                // }
                Helper::last_login($data->id_member);
                //$data->password = $password;

                $wheree = array('history_notif.id_member' => $data->id_member, 'unread' => 1);
                $cnt_unread = DB::table('history_notif')->where($wheree)->count();

                $data->ewallet = $res;
                $data->cnt_unread = $cnt_unread;
                $result = array(
                    'err_code' => '00',
                    'err_msg' => 'ok',
                    'data' => $data
                );
            } else {
                $result = array(
                    'err_code' => '03',
                    'err_msg' => 'incorrect email or password',
                    'data' => null
                );
            }
            if ((int)$data->status != 1) {
                $result = array();
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'incorrect email or password', //https://app.asana.com/0/1199955186409410/1202120632018811
                    'data' => $data
                );
            }
        }
        return response($result);
    }

    function change_pass(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member;
        Helper::last_login($id_member);
        $new_pass = $request->new_pass;
        $old_pass = $request->old_pass;
        $result = array();
        if (empty($new_pass)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'new_pass is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if (empty($old_pass)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'old_pass is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($id_member > 0) {
            $data = Members::where('id_member', $id_member)->first();
            $password = Crypt::decryptString($data->pass);
            $old_pass = strtolower($old_pass);
            if ($password != $old_pass) {
                $result = array(
                    'err_code' => '03',
                    'err_msg' => 'old_pass not match',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $new_pass = strtolower($new_pass);
            if ($password == $new_pass) {
                $result = array(
                    'err_code' => '02',
                    'err_msg' => 'new_pass sama dengan password sebelumnya',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $data->pass = Crypt::encryptString($new_pass);
            $data->updated_at = $tgl;
            $data->updated_by = $id_member;
            $data->save();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => $id_member
            );
        }
        return response($result);
    }

    function upl_photo(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member;
        $photo = $request->file("photo");
        $result = array();
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if (empty($photo)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'photo required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $_tgl = date('YmdHi');
        $data = Members::where('id_member', $id_member)->first();
        $nama = str_replace(' ', '', $data->name);
        if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
        $nama = strtolower($nama);
        $nama_file = $_tgl . '' . $nama;
        $nama_file = Crypt::encryptString($nama_file);
        $fileSize = $photo->getSize();
        $extension = $photo->getClientOriginalExtension();
        $imageName = $nama_file . '.' . $extension;
        $tujuan_upload = 'uploads/members';
        $_extension = array('png', 'jpg', 'jpeg');
        if ($fileSize > 6291456) { // satuan bytes
            $result = array(
                'err_code' => '07',
                'err_msg' => 'file size over 6MB',
                'data' => $fileSize
            );
            return response($result);
            return false;
        }
        if (!in_array($extension, $_extension)) {
            $result = array(
                'err_code' => '07',
                'err_msg' => 'file extension not valid',
                'data' => null
            );
            return response($result);
            return false;
        }
        $photo->move($tujuan_upload, $imageName);
        $data->photo = $imageName;
        $data->updated_at = $tgl;
        $data->updated_by = $id_member;
        $data->save();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data,
            'fileSize' => $fileSize,
            'extension' => $extension,
            'imageName' => $imageName,
        );
        Helper::last_login($id_member);
        return response($result);
    }

    function verify_phone(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member;
        $kode = (int)$request->kode;
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($kode <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'kode required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $data = Members::where('id_member', $id_member)->first();
        $verify_phoneDecryptString = Crypt::decryptString($data->verify_phone);
        if ($kode != (int)$verify_phoneDecryptString) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'incorrect verification code',
                'data' => $data->verify_phone
            );
            return response($result);
            return false;
        }
        $data->status = 1;
        $data->verify_phone = 1;
        $data->updated_at = $tgl;
        $data->updated_by = $id_member;
        $data->save();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function resend_code_phone(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member;
        $data = Members::where('id_member', $id_member)->first();
        if ((int)$data->verify_phone == 1) {
            $result = array(
                'err_code' => '03',
                'err_msg' => 'phone sudah terverifikasi sebelumnya',
                'data' => null
            );
            return response($result);
            return false;
        }

        $verify_code = rand(1000, 9999);
        $data->verify_phone = Crypt::encryptString($verify_code);
        $data->updated_at = $tgl;
        $data->updated_by = $id_member;
        $data->save();
        //Helper::send_sms($data->phone, $verify_code);

        $setting = DB::table('setting')->get()->toArray();
        $out = array();
        if (!empty($setting)) {
            foreach ($setting as $val) {
                $out[$val->setting_key] = $val->setting_val;
            }
        }
        $content_member = '';
        $content_member = $out['content_reg'];
        $content = str_replace('[#name#]', $data->nama, $content_member);
        $content = str_replace('[#kode_otp#]', $verify_code, $content);
        $data->content = $content;
        Mail::send([], ['users' => $data], function ($message) use ($data) {
            $message->to($data->email, $data->nama)->subject('Register')->setBody($data->content, 'text/html');
        });
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function verify_email($id)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = Crypt::decryptString($id);
        $id_member = (int)$id_member;
        $data = Members::where('id_member', $id_member)->first();
        if ((int)$data->verify_email == 1) {
            $result = array(
                'err_code' => '03',
                'err_msg' => 'email sudah terverifikasi sebelumnya',
                'data' => null
            );
            return response($result);
            return false;
        }
        $data->verify_email = 1;
        if ((int)$data->verify_phone == 1) {
            $data->status = 1;
        }
        $data->updated_at = $tgl;
        $data->updated_by = $id_member;
        $data->save();
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function forgot_pass(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $email = $request->email;
        if (!empty($email)) {
            $cnt = Members::whereRaw("LOWER(email) = '" . strtolower($email) . "'")->count();
            if ((int)$cnt > 0) {
                $data = Members::whereRaw("LOWER(email) = '" . strtolower($email) . "'")->first();
                if ((int)$data->verify_email <= 0) {
                    $result = array(
                        'err_code' => '07',
                        'err_msg' => 'email belum terverifikasi',
                        'data' => null
                    );
                    return response($result);
                    return false;
                }
                $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
                $pass = array(); //remember to declare $pass as an array
                $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
                for ($i = 0; $i < 8; $i++) {
                    $n = rand(0, $alphaLength);
                    $pass[] = $alphabet[$n];
                }
                $new_pass = implode($pass);
                $data->pass = Crypt::encryptString(strtolower($new_pass));
                $data->updated_at = $tgl;
                $data->save();
                $setting = DB::table('setting')->get()->toArray();
                $out = array();
                if (!empty($setting)) {
                    foreach ($setting as $val) {
                        $out[$val->setting_key] = $val->setting_val;
                    }
                }
                $content_member = $out['content_forgotPass'];
                $content = str_replace('[#name#]', $data->nama, $content_member);
                $content = str_replace('[#email#]', $data->email, $content);
                $content = str_replace('[#new_pass#]', $new_pass, $content);
                $data->content = $content;
                Mail::send([], ['users' => $data], function ($message) use ($data) {
                    $message->to($data->email, $data->nama)->subject('Forgot Password')->setBody($data->content, 'text/html');
                });
                $result = array(
                    'err_code' => '00',
                    'err_msg' => 'INFORMASI : PERMINTAAN RESET PASSWORD ANDA SUDAH DI KIRIM KE EMAIL, SILAHKAN CEK EMAIL ANDA',
                    'data' => $data
                );
            } else {
                $result = array(
                    'err_code' => '04',
                    'err_msg' => 'email not found',
                    'data' => null
                );
            }
        } else {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'email required',
                'data' => null
            );
        }
        return response($result);
    }

    function add_wishlist(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $result = array();
        if ($id_member <= 0 && $id_product <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member or id product required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('id_member' => $id_member, 'id_product' => $id_product);
        $res_wishlist = DB::table('wishlist')->where($where)->count();
        if ((int)$res_wishlist > 0) {
            $result = array(
                'err_code' => '07',
                'err_msg' => 'id product sudah ditambahkan sebelumnya',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where += array('created_at' => $tgl);
        DB::table('wishlist')->insert($where);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $where
        );
        return response($result);
    }

    function del_wishlist(Request $request)
    {
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $result = array();
        if ($id_member <= 0 && $id_product <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member or id product required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('id_member' => $id_member, 'id_product' => $id_product);

        DB::table('wishlist')->where($where)->delete();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function get_mywishlist(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'wishlist.id';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $id_category = (int)$request->id_category > 0 ? (int)$request->id_category : 0;
        $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
        $type = !empty($data_member) ? (int)$data_member->type : 0;
        $result = array();
        $_limit = array();
        $_jml_beli = array();
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $_data = array();
        $data = array();
        $count = 0;
        $where = array('product.deleted_at' => null, 'wishlist.id_member' => $id_member);
        if ($id_category > 0) $where += array('product.id_category' => $id_category);
        if (!empty($keyword)) {
            $_data = DB::table('product')->select('product.product_name', 'product.id_category', 'product.img', 'kode_produk', 'short_description', 'is_sold_out', 'wishlist.*')
                ->leftJoin('wishlist', 'wishlist.id_product', '=', 'product.id_product')
                ->where($where)->whereRaw("LOWER(product.product_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('product')
                ->leftJoin('wishlist', 'wishlist.id_product', '=', 'product.id_product')
                ->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('product')->select('product.product_name', 'product.id_category', 'product.img', 'kode_produk', 'short_description', 'is_sold_out', 'wishlist.*')
                ->leftJoin('wishlist', 'wishlist.id_product', '=', 'product.id_product')
                ->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ((int)$count > 0) {
            $sql_limit = "select id_lp,id_product, limit_pembelian, start_date::timestamp, end_date::timestamp from limit_pembelian
            where deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";
            $limit_product = DB::select(DB::raw($sql_limit));
            $jml_beli = 0;
            if (!empty($limit_product)) {
                foreach ($limit_product as $lp) {
                    $_limit['id_lp'][$lp->id_product] = (int)$lp->id_lp;
                    $_limit['limit_beli'][$lp->id_product] = (int)$lp->limit_pembelian;
                }
            }

            if ($id_member > 0 && (int)count($_limit) > 0) {
                $start_date = $limit_product[0]->start_date;
                $end_date = $limit_product[0]->end_date;
                $sql_beli = "select sum(jml) as jml_beli, id_product from transaksi left join transaksi_detail on transaksi_detail.id_trans = transaksi.id_transaksi where id_member=$id_member and ((created_at::timestamp >= '" . $start_date . "' or created_at::timestamp <= '" . $end_date . "')) group by id_product";
                $beli_product = DB::select(DB::raw($sql_beli));
                if ((int)count($beli_product) > 0) {
                    foreach ($beli_product as $lp) {
                        $_jml_beli[$lp->id_product] = (int)$lp->jml_beli;
                    }
                }
            }

            $_pricelist = array();
            $sql_pricelist = '';
            $sql_pricelist = "select id_pricelist,id_product, harga_konsumen,harga_member,pv,rv from pricelist
            where deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";
            $pricelist_active = DB::select(DB::raw($sql_pricelist));
            if (!empty($pricelist_active)) {
                foreach ($pricelist_active as $pa) {
                    $_pricelist['id_pricelist'][$pa->id_product] = $pa->id_pricelist;
                    $_pricelist['harga_member'][$pa->id_product] = $pa->harga_member;
                    $_pricelist['harga_konsumen'][$pa->id_product] = $pa->harga_konsumen;
                    $_pricelist['pv'][$pa->id_product] = $pa->pv;
                    $_pricelist['rv'][$pa->id_product] = $pa->rv;
                }
            }
            foreach ($_data as $d) {
                $path_img = null;
                $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                unset($d->img);
                $d->img = $path_img;
                $limit_beli = isset($_limit['id_lp'][$d->id_product]) ? (int)$_limit['limit_beli'][$d->id_product] : 9999999999;
                $jml_beli = isset($_jml_beli[$d->id_product]) ? (int)$_jml_beli[$d->id_product] : 0;
                $d->jml_limit_beli = $limit_beli;
                $d->jml_beli = $jml_beli;
                $d->is_limit_beli = (int)$jml_beli >= (int)$limit_beli ? 1 : 0;
                $d->harga_member = isset($_pricelist['harga_member'][$d->id_product]) ? $_pricelist['harga_member'][$d->id_product] : 0;
                $d->harga_konsumen = isset($_pricelist['harga_konsumen'][$d->id_product]) ? $_pricelist['harga_konsumen'][$d->id_product] : 0;
                $d->pv = isset($_pricelist['pv'][$d->id_product]) ? $_pricelist['pv'][$d->id_product] : 0;
                $d->rv = isset($_pricelist['rv'][$d->id_product]) ? $_pricelist['rv'][$d->id_product] : 0;
                $d->harga = $type == 1 || $type == 3 ? $d->harga_member : $d->harga_konsumen;
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

    function add_address(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $data = array(
            "label_alamat" => $request->label_alamat,
            "nama_penerima" => $request->nama_penerima,
            "phone_penerima" => $request->phone_penerima,
            "id_provinsi" => (int)$request->id_provinsi,
            "id_city" => (int)$request->id_city,
            "id_kec" => (int)$request->id_kec,
            "alamat" => $request->alamat,
            "kode_pos" => $request->kode_pos
        );
        if ($id > 0) {
            $data += array("updated_at" => $tgl);
            DB::table('address_member')->where('id_address', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "id_member" => (int)$id_member);
            $id = DB::table('address_member')->insertGetId($data, "id_address");
        }
        $result = array();
        if ($id > 0) {
            $data += array('id_address' => $id);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'insert has problem',
                'data' => null
            );
        }
        return response($result);
    }

    public function get_address(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_address';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $where = array(
            'address_member.deleted_at' => null,
            'provinsi.deleted_at' => null,
            'city.deleted_at' => null,
            'kecamatan.deleted_at' => null,
            'address_member.id_member' => (int)$id_member
        );
        $result = array();
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $count = 0;
        $_data = array();
        $data = null;
        $selects = array(
            'address_member.*',
            'provinsi_name',
            'provinsi.kode_jne as kode_jne_prov',
            'provinsi.kode_lp as kode_lp_prov',
            'city_name',
            'city.kode_jne as kode_jne_city',
            'city.kode_lp as kode_lp_city',
            'kec_name',
            'kecamatan.kode_jne as kode_jne_kec',
            'kecamatan.kode_lp as kode_lp_kec',
            'warehouse.id_wh',
            'warehouse.wh_name',
            'warehouse.id_prov as id_prov_origin'
        );
        if (!empty($keyword)) {
            $_data = DB::table('address_member')->where($where)
                ->select(
                    'address_member.*',
                    'provinsi_name',
                    'provinsi.kode_jne as kode_jne_prov',
                    'provinsi.kode_lp as kode_lp_prov',
                    'city_name',
                    'city.kode_jne as kode_jne_city',
                    'city.kode_lp as kode_lp_city',
                    'kec_name',
                    'kecamatan.kode_jne as kode_jne_kec',
                    'kecamatan.kode_lp as kode_lp_kec',
                    'warehouse.id_wh',
                    'warehouse.wh_name',
                    'warehouse.id_prov as id_prov_origin'
                )
                ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
                ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
                ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')
                ->whereRaw("LOWER(label_alamat) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('address_member')->where($where)
                ->select(
                    'address_member.*',
                    'provinsi_name',
                    'provinsi.kode_jne as kode_jne_prov',
                    'provinsi.kode_lp as kode_lp_prov',
                    'city_name',
                    'city.kode_jne as kode_jne_city',
                    'city.kode_lp as kode_lp_city',
                    'kec_name',
                    'kecamatan.kode_jne as kode_jne_kec',
                    'kecamatan.kode_lp as kode_lp_kec',
                    'warehouse.id_wh',
                    'warehouse.wh_name',
                    'warehouse.id_prov as id_prov_origin'
                )
                ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
                ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
                ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')->count();
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('address_member')->where($where)
                ->select(
                    'address_member.*',
                    'provinsi_name',
                    'provinsi.kode_jne as kode_jne_prov',
                    'provinsi.kode_lp as kode_lp_prov',
                    'city_name',
                    'city.kode_jne as kode_jne_city',
                    'city.kode_lp as kode_lp_city',
                    'kec_name',
                    'kecamatan.kode_jne as kode_jne_kec',
                    'kecamatan.kode_lp as kode_lp_kec',
                    'warehouse.id_wh',
                    'warehouse.wh_name',
                    'warehouse.id_prov as id_prov_origin'
                )
                ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
                ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
                ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')
                ->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
                unset($d->created_at);
                unset($d->updated_at);
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

    public function get_address_detail(Request $request)
    {
        $id_address = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $where = array(
            'address_member.deleted_at' => null,
            'provinsi.deleted_at' => null,
            'city.deleted_at' => null,
            'kecamatan.deleted_at' => null,
            'address_member.id_address' => (int)$id_address
        );
        $result = array();
        if ($id_address <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_address required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $count = 0;
        $_data = array();
        $data = null;
        $count = DB::table('address_member')->where($where)
            ->select(
                'address_member.*',
                'provinsi_name',
                'provinsi.kode_jne as kode_jne_prov',
                'provinsi.kode_lp as kode_lp_prov',
                'city_name',
                'city.kode_jne as kode_jne_city',
                'city.kode_lp as kode_lp_city',
                'kec_name',
                'kecamatan.kode_jne as kode_jne_kec',
                'kecamatan.kode_lp as kode_lp_kec',
                'warehouse.id_wh',
                'warehouse.wh_name',
                'warehouse.id_prov as id_prov_origin'
            )
            ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
            ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
            ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
            ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')->count();

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => null
        );
        if ($count > 0) {
            $_data = DB::table('address_member')->where($where)
                ->select(
                    'address_member.*',
                    'provinsi_name',
                    'provinsi.kode_jne as kode_jne_prov',
                    'provinsi.kode_lp as kode_lp_prov',
                    'city_name',
                    'city.kode_jne as kode_jne_city',
                    'city.kode_lp as kode_lp_city',
                    'kec_name',
                    'kecamatan.kode_jne as kode_jne_kec',
                    'kecamatan.kode_lp as kode_lp_kec',
                    'warehouse.id_wh',
                    'warehouse.wh_name',
                    'warehouse.id_prov as id_prov_origin'
                )
                ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
                ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
                ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
                ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')->first();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $_data
            );
        }
        return response($result);
    }

    function del_address(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $data = array("deleted_at" => $tgl);
        DB::table('address_member')->where('id_address', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function history_transaksi(Request $request)
    {
        $tgl = Carbon::now();
        $data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $status = $request->has('status') && $request->status != '' && (int)$request->status >= 0 ? (int)$request->status : -1;
        $sql = "select id_transaksi,ewallet,ttl_price, id_member,type_member from transaksi where id_member=$id_member and status=0 and expired_payment::timestamp <= '" . $tgl . "'";
        $trans_expired = DB::select(DB::raw($sql));
        $whereIn = array();
        $dt_refund_ewallet = array();
        if (count($trans_expired) > 0) {
            //update status ke expired payment
            foreach ($trans_expired as $te) {
                $whereIn[] = $te->id_transaksi;
                $dt_refund_ewallet[] = array(
                    'id_transaksi' => $te->id_transaksi,
                    'ewallet' => $te->ewallet,
                    'ttl_price' => $te->ttl_price,
                    'id_member' => $te->id_member,
                    'status' => 0,
                    'created_at' => $tgl
                );
            }
            DB::table('transaksi')->where(array("status" => 0))
                ->whereIn('id_transaksi', $whereIn)->update(array("status" => 2, "cek_refund_ewallet" => 1));
            if (!empty($dt_refund_ewallet)) DB::table('refund_ewallet')->insert($dt_refund_ewallet);
        }
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_transaksi';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $column_int = array("id_transaksi");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = array('transaksi.id_member' => $id_member);
        if ($status > -1) $where += array('transaksi.status' => (int)$status);
        $count = 0;
        $count = DB::table('transaksi')->where($where)->count();

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $_data = DB::table('transaksi')->select(
                'transaksi.*',
                'members.nama as nama_member',
                'members.email',
                'members.phone as phone_member'
            )
                ->where($where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
                ->orderByRaw($sort_column)->get();
            $whereIn = array();
            foreach ($_data as $_d) {
                $whereIn[] = $_d->id_transaksi;
            }
            $data_trans = DB::table('transaksi_detail')->whereIn('id_trans', $whereIn)->get();
            $_dt = array();
            foreach ($data_trans as $dt) {
                $_dt[$dt->id_trans][] = $dt;
            }
            foreach ($_data as $d) {
                $status = $d->status;
                $key_payment = (int)$status == 0 ? $d->key_payment : '';
                unset($d->session_id);
                unset($d->delivery_by);
                unset($d->key_payment);
                unset($d->status);
                unset($d->log_payment);
                unset($d->onprocess_by);
                $expiredPayment = Carbon::parse($d->expired_payment);
                $expired = $tgl->gt($expiredPayment);
                $status = !$expired && $status == 0 ? 0 : $status;
                $d->key_payment = $status == 0 ? $key_payment : '';
                if ((int)$d->token_mitra > 0) {
                    unset($_data->key_payment);
                    $d->key_payment = '';
                }
                $d->status = $status;
                $d->list_item = !empty($dt) && !empty($_dt[$d->id_transaksi]) ? $_dt[$d->id_transaksi] : null;
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

    function akun_fb(Request $request)
    {
        $data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $fb = !empty($request->fb) ? $request->fb : '';
        $data = array('fb' => $fb);
        DB::table('members')->where('id_member', $id_member)->update($data);
        $data += array('id_member' => $id_member);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function akun_ig(Request $request)
    {
        $data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $ig = !empty($request->ig) ? $request->ig : '';
        $data = array('ig' => $ig);
        DB::table('members')->where('id_member', $id_member)->update($data);
        $data += array('id_member' => $id_member);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function add_sm(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $tipe = $request->has('tipe') ? $request->tipe : '';
        $nama = $request->has('nama') ? $request->nama : '';
        $email = $request->has('email') ? $request->email : '';
        $id_sm = $request->has('id_sm') ? $request->id_sm : '';
        $cnt = DB::table('list_social_media')->where(array('id_sm' => $id_sm))->count();
        $result = array();
        if ((int)$cnt > 0) {
            $result = array(
                'err_code' => '07',
                'err_msg' => 'id_sm already exist',
                'data' => null
            );
            return response($result);
            return false;
        }
        $data = array(
            'id_member' => $id_member,
            'tipe' => (int)$tipe,
            'nama' => $nama,
            'email' => $email,
            'id_sm' => $id_sm,
            'created_at' => $tgl
        );
        $id = DB::table('list_social_media')->insertGetId($data, "id");

        if ($id > 0) {
            $data += array('id' => $id);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'insert has problem',
                'data' => null
            );
        }
        return response($result);
    }

    function get_cniid_sm(Request $request)
    {
        $keyword = $request->has('keyword') ? strtolower($request->keyword) : '';
        $tipe = $request->has('tipe') ? (int)$request->tipe : 0;
        $where = array();
        if ((int)$tipe > 0) $where = array('list_social_media.tipe' => $tipe);
        $cnt = DB::table('list_social_media')->where($where)->where(function ($query) use ($keyword) {
            $query->whereRaw("LOWER(list_social_media.nama) like '%" . $keyword . "%' or list_social_media.id_sm like '%" . $keyword . "%' ");
        })->count();
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => $keyword
        );
        if ((int)$cnt > 0) {
            $data = DB::table('list_social_media')->select('list_social_media.*', 'members.nama as nama_member', 'members.cni_id')
                ->where($where)->where(function ($query) use ($keyword) {
                    $query->whereRaw("LOWER(list_social_media.nama) like '%" . $keyword . "%' or list_social_media.id_sm like '%" . $keyword . "%' ");
                    //$query->where('list_social_media.nama', $keyword)

                })->leftJoin('members', 'members.id_member', '=', 'list_social_media.id_member')->get();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        }
        return response($result);
    }

    function get_list_sm(Request $request)
    {
        $id_member = $request->has('id_member') ? (int)$request->id_member : 0;
        $tipe = $request->has('tipe') ? (int)$request->tipe : 0;
        $where = array();
        $where = array('list_social_media.id_member' => $id_member);
        if ((int)$tipe > 0) $where += array('list_social_media.tipe' => $tipe);
        $data_sm = DB::table('list_social_media')->select('list_social_media.*', 'members.nama as nama_member', 'members.cni_id')
            ->where($where)
            ->leftJoin('members', 'members.id_member', '=', 'list_social_media.id_member')->get();

        $list_dr = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => null
        );
        if ((int)count($data_sm) > 0) {
            $cni_id = isset($data_sm) ? $data_sm[0]->cni_id : '';
            if (!empty($cni_id)) {
                $data_ref_cnt = DB::table('members')->where(array('cni_id_ref' => $cni_id))->count();
                if ((int)$data_ref_cnt > 0) {
                    $data_ref = DB::table('members')->where(array('cni_id_ref' => $cni_id))->get();
                    foreach ($data_ref as $dr) {
                        $list_dr[$dr->cni_id_ref][$dr->tipe_sm][$dr->id_sm][] = $dr->id_member;
                    }
                }
            }
            foreach ($data_sm as $ds) {
                $ds->cnt_follower = isset($list_dr[$ds->cni_id][$ds->tipe][$ds->id_sm]) ? count($list_dr[$ds->cni_id][$ds->tipe][$ds->id_sm]) : 0;
                $list_sm[] = $ds;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data_sm
            );
        }
        return response($result);
    }

    function get_list_follower_sm(Request $request)
    {
        $id_member = $request->has('id_member') ? (int)$request->id_member : 0;
        $tipe = $request->has('tipe') ? (int)$request->tipe : 0;
        $id_sm = $request->has('id_sm') ? $request->id_sm : '';
        $where = array('list_social_media.id_member' => $id_member, 'list_social_media.id_sm' => $id_sm);
        if ((int)$tipe > 0) $where += array('list_social_media.tipe' => $tipe);
        $data_cnt = DB::table('list_social_media')->where($where)->count();
        $list_dr = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => null
        );
        $data_ref_cnt = 0;
        if ((int)$data_cnt > 0) {
            $data_sm = DB::table('list_social_media')->select('list_social_media.*', 'members.nama as nama_member', 'members.cni_id')
                ->where($where)
                ->leftJoin('members', 'members.id_member', '=', 'list_social_media.id_member')->first();
            $cni_id = isset($data_sm) ? $data_sm->cni_id : '';
            $tipe = isset($data_sm) ? (int)$data_sm->tipe : 0;
            if (!empty($cni_id)) {
                $wheree = array();
                $wheree = array('cni_id_ref' => $cni_id, 'id_sm' => $id_sm, 'tipe_sm' => (int)$tipe);
                $data_ref_cnt = DB::table('members')->where($wheree)->count();
                $data_ref = array();
                if ((int)$data_ref_cnt > 0) {
                    $data_ref = DB::table('members')->select('id_member', 'cni_id', 'nama', 'email', 'phone')->where($wheree)->get();
                }
            }
            $data_sm->cnt_follower = $data_ref_cnt;
            $data_sm->list_follower = $data_ref;
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data_sm
            );
        }
        return response($result);
    }

    function akun_bank(Request $request)
    {
        $data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $nama_bank = !empty($request->nama_bank) ? $request->nama_bank : '';
        $nama_rek = !empty($request->nama_rek) ? $request->nama_rek : '';
        $no_rek = !empty($request->no_rek) ? $request->no_rek : '';
        $data = array(
            'nama_bank' => $nama_bank,
            'nama_rek' => $nama_rek,
            'no_rek' => $no_rek
        );
        DB::table('members')->where('id_member', $id_member)->update($data);
        $data += array('id_member' => $id_member);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function req_cashout(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $dt = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $nominal = !empty($request->nominal) ? $request->nominal : '';
        // if($nominal <= 100000){
        // $dt = array(
        // "err_code"      => "07",
        // "result"        => "Nominal harus diatas 100000",
        // "err_msg"       => "Nominal harus diatas 100000",
        // "data"			=> ''

        // );
        // return response($dt);
        // return false;
        // }
        $data = Members::where(array('id_member' => $id_member))->first();
        $type = (int)$data->type;
        $cni_id = !empty($data->cni_id) ? $data->cni_id : '';

        if ($type != 1) {
            $dt = array(
                "err_code" => "07",
                "result" => "Type member tidak sesuai",
                "err_msg" => "Type member tidak sesuai",
                "data" => $data

            );
            return response($dt);
            return false;
        }
        if (empty($cni_id)) {
            $dt = array(
                "err_code" => "04",
                "result" => "CNI ID tidak ditemukan",
                "err_msg" => "CNI ID tidak ditemukan",
                "data" => $data

            );
            return response($dt);
            return false;
        }
        $url = env('URL_REQUEST_CASHOUT');
        $token = env('TOKEN_REQUEST_CASHOUT');
        $postfields = array(
            "nomorn" => $cni_id,
            "token" => $token,
            "nominalcashout" => $nominal
        );
        $curl = curl_init();
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
        $_res = json_encode($response);
        $res = json_decode($_res);
        $dt_log = array();
        $dt_history = array();
        $dt_log = array(
            "api_name" => "req_cashout",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "id_transaksi" => $id_member,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        return response($res)->header('Content-Type', "application/json");
    }

    function history_cashout(Request $request)
    {
        $result = array();
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $data = Members::where(array('id_member' => $id_member))->first();
        $type = isset($data) && (int)$data->type > 0 ? (int)$data->type : 2;
        $cni_id = isset($data) && !empty($data->cni_id) ? $data->cni_id : '';
        if ($type != 1) {
            $result = array(
                "err_code" => "07",
                "result" => "Type member tidak sesuai",
                "err_msg" => "Type member tidak sesuai",
                "data" => $data

            );
            return response($result);
            return false;
        }
        if (empty($cni_id)) {
            $result = array(
                "err_code" => "04",
                "result" => "CNI ID tidak ditemukan",
                "err_msg" => "CNI ID tidak ditemukan",
                "data" => $data

            );
            return response($result);
            return false;
        }

        $url = env('URL_HISTORY_CASHOUT');
        $token = env('TOKEN_HISTORY_CASHOUT');
        $postfields = array(
            "nomorn" => $cni_id,
            "token" => $token
        );
        $curl = curl_init();
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
        $_res = json_encode($response);
        $res = json_decode($_res);

        $dt_log = array();
        $dt_history = array();
        $dt_log = array(
            "api_name" => "history_cashout",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "id_transaksi" => $id_member,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        return response($res)->header('Content-Type', "application/json");
    }

    function history_wallet(Request $request)
    {
        $result = array();
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $data = Members::where(array('id_member' => $id_member))->first();
        $type = isset($data) && (int)$data->type > 0 ? (int)$data->type : 2;
        $cni_id = isset($data) && !empty($data->cni_id) ? $data->cni_id : '';
        if ($type != 1) {
            $result = array(
                "err_code" => "07",
                "result" => "Type member tidak sesuai",
                "err_msg" => "Type member tidak sesuai",
                "data" => $data

            );
            return response($result);
            return false;
        }
        if (empty($cni_id)) {
            $result = array(
                "err_code" => "04",
                "result" => "CNI ID tidak ditemukan",
                "err_msg" => "CNI ID tidak ditemukan",
                "data" => $data

            );
            return response($result);
            return false;
        }

        $url = env('URL_HISTORY_WALLET');
        $token = env('TOKEN_HISTORY_WALLET');
        $postfields = array(
            "nomorn" => $cni_id,
            "token" => $token
        );
        $curl = curl_init();
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
        $_res = json_encode($response);
        $res = json_decode($_res);

        $dt_log = array();
        $dt_history = array();
        $dt_log = array(
            "api_name" => "history_wallet",
            "param_from_fe" => serialize($request->all()),
            "param_to_cni" => serialize($postfields),
            "endpoint" => $url,
            "responses" => serialize($res),
            "id_transaksi" => $id_member,
            "created_at" => $tgl
        );
        DB::table('log_api')->insert($dt_log);
        return response($res)->header('Content-Type', "application/json");
    }

    function set_token_fcm(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id = (int)$request->id_token_fcm > 0 ? (int)$request->id_token_fcm : 0;
        $token_fcm = $request->token_fcm;
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $data = array(
            'id_member' => $id_member,
            'token_fcm' => $token_fcm
        );
        if ($id > 0) {
            $data += array("updated_at" => $tgl);
            DB::table('fcm_token')->where('id_token_fcm', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl);
            $id = DB::table('fcm_token')->insertGetId($data, "id_token_fcm");
        }
        $result = array();
        if ($id > 0) {
            $data += array('id_token_fcm' => $id);

            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'insert has problem',
                'data' => null
            );
        }
        return response($result);
    }

    function history_notif(Request $request)
    {
        $tgl = date('Y-m-d', strtotime('+1 day'));
        $tgl = date('Y-m-d H:i:s', strtotime($tgl));
        $previousMonthLastDay = date("Y-m-d", strtotime("-3 months"));
        $previousMonthLastDay = date('Y-m-d H:i:s', strtotime($previousMonthLastDay));
        $sort_column = "id_notif::integer DESC";
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $type = (int)$request->type > 0 ? (int)$request->type : 0;
        $where = array('history_notif.id_member' => $id_member);
        if ($type > 0) $where += array('type' => $type);
        $count = DB::table('history_notif')->where($where)->whereBetween('created_at', [$previousMonthLastDay, $tgl])->count();

        $wheree = array('history_notif.id_member' => $id_member, 'unread' => 1);
        if ($type > 0) $wheree += array('type' => $type);
        $cnt_unread = DB::table('history_notif')->where($wheree)->whereBetween('created_at', [$previousMonthLastDay, $tgl])->count();

        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'cnt_unread' => 0,
            'data' => null
        );
        DB::connection()->enableQueryLog();
        if ($count > 0) {
            $data = DB::table('history_notif')->where($where)->whereBetween('created_at', [$previousMonthLastDay, $tgl])->orderByRaw($sort_column)->get();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'cnt_unread' => $cnt_unread,
                'data' => $data
            );
        }

        return response($result);
    }

    function read_notif(Request $request)
    {
        $id = (int)$request->id_notif > 0 ? (int)$request->id_notif : 0;
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        if ((int)$id > 0) {
            $data = array('unread' => 0);
            DB::table('history_notif')->where('id_notif', $id)->update($data);
        }
        $cnt_unread = 0;
        if ($id_member > 0) {
            $wheree = array('history_notif.id_member' => $id_member, 'unread' => 1);
            $cnt_unread = DB::table('history_notif')->where($wheree)->count();
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $id,
            'cnt_unread' => $cnt_unread
        );
        return response($result);
    }

    function add_cart(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $will_be_notified_on_date = date('Y-m-d H:i:s', strtotime($tgl . ' +1 day'));
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $qty = (int)$request->qty > 0 ? (int)$request->qty : 1;

        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($id_product <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_product required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $data = array(
            'id_member' => $id_member,
            'id_product' => $id_product
        );
        $cnt_cart = DB::table('cart')->where($data)->count();
        // Log::info($cnt_cart);
        if ((int)$cnt_cart > 0) {
            $dt_exist = DB::table('cart')->where($data)->first();
            $_qty = isset($dt_exist->qty) ? (int)$dt_exist->qty : 0;
            $_data = array("updated_at" => $tgl, 'qty' => $qty + $_qty);
            DB::table('cart')->where($data)->update($_data);
        } else {
            $data += array("created_at" => $tgl, 'qty' => $qty);
            DB::table('cart')->insert($data);
        }
        $count = DB::table('cart')->where('id_member', $id_member)->count();

        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if ((int)$count > 0) {
            DB::table('cart')->where(array('id_member' => $id_member))->update(array('will_be_notified_on_date' => $will_be_notified_on_date));
            $dt_cart = DB::table('cart')->where('id_member', $id_member)->get();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $dt_cart
            );
        }
        return response($result);
    }

    function edit_qty_cart(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $will_be_notified_on_date = date('Y-m-d H:i:s', strtotime($tgl . ' +1 day'));
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $qty = (int)$request->qty > 0 ? (int)$request->qty : 1;

        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($id_product <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_product required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $data = array(
            'id_member' => $id_member,
            'id_product' => $id_product
        );
        $cnt_cart = DB::table('cart')->where($data)->count();
        // Log::info($cnt_cart);
        if ((int)$cnt_cart > 0) {
            $_data = array("updated_at" => $tgl, 'qty' => $qty);
            DB::table('cart')->where($data)->update($_data);
        } else {
            $data += array("created_at" => $tgl, 'qty' => $qty);
            DB::table('cart')->insert($data);
        }

        $count = DB::table('cart')->where('id_member', $id_member)->count();
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if ((int)$count > 0) {
            DB::table('cart')->where(array('id_member' => $id_member))->update(array('will_be_notified_on_date' => $will_be_notified_on_date));
            $dt_cart = DB::table('cart')->where('id_member', $id_member)->get();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $dt_cart
            );
        }
        return response($result);
    }

    function del_cart(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id_product = array();
        for ($i = 0; $i < count($request->id_product); $i++) {
            $id_product[] = $request->id_product[$i];
        }
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if (count($id_product) <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_product required',
                'data' => null
            );
            return response($result);
            return false;
        }
        DB::table('cart')->where('id_member', $id_member)->whereIn('id_product', $id_product)->delete();
        $count = DB::table('cart')->where('id_member', $id_member)->count();
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'total_data' => 0,
            'data' => null
        );
        if ((int)$count > 0) {
            $dt_cart = DB::table('cart')->where('id_member', $id_member)->get();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $dt_cart
            );
        }
        return response($result);
    }

    function get_cart(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        if ($id_member <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $count = DB::table('cart')->where('id_member', $id_member)->count();
        $result = array();
        $data = array();
        $_limit = array();
        $_jml_beli = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if ((int)$count > 0) {
            $dt_cart = DB::table('cart')->where('id_member', $id_member)->get();
            $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
            $type = !empty($data_member) ? (int)$data_member->type : 0;
            $cni_id = !empty($data_member) ? $data_member->cni_id : '';
            $sql_pricelist = '';
            $myQty = array();
            foreach ($dt_cart as $dc) {
                $id_product[] = $dc->id_product;
                $whereIn = implode(',', $id_product);
                $myQty[$dc->id_product] = $dc->qty;
            }
            $sql_pricelist = "select product.*, category_name, pricelist.id_pricelist,pricelist.id_product, pricelist.harga_konsumen,pricelist.harga_member,pv,rv from pricelist left join product on product.id_product = pricelist.id_product left join category on category.id_category = product.id_category where pricelist.deleted_at is null and ((pricelist.start_date::timestamp <= '" . $tgl . "' and pricelist.end_date::timestamp >= '" . $tgl . "') or (pricelist.start_date::timestamp >= '" . $tgl . "' and pricelist.end_date::timestamp <= '" . $tgl . "')) and pricelist.id_product IN ($whereIn)";
            $pricelist_active = DB::select(DB::raw($sql_pricelist));
            if (!empty($pricelist_active)) {
                $sql_limit = "select id_lp,id_product, limit_pembelian, start_date::timestamp, end_date::timestamp from limit_pembelian
            where deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";
                $limit_product = DB::select(DB::raw($sql_limit));
                $jml_beli = 0;
                if (!empty($limit_product)) {
                    foreach ($limit_product as $lp) {
                        $_limit['id_lp'][$lp->id_product] = (int)$lp->id_lp;
                        $_limit['limit_beli'][$lp->id_product] = (int)$lp->limit_pembelian;
                    }
                }

                if ($id_member > 0 && (int)count($limit_product) > 0) {
                    $start_date = $limit_product[0]->start_date;
                    $end_date = $limit_product[0]->end_date;
                    $sql_beli = "select sum(jml) as jml_beli, id_product from transaksi left join transaksi_detail on transaksi_detail.id_trans = transaksi.id_transaksi where id_member=$id_member and ((created_at::timestamp >= '" . $start_date . "' or created_at::timestamp <= '" . $end_date . "')) group by id_product";
                    $beli_product = DB::select(DB::raw($sql_beli));
                    if ((int)count($beli_product) > 0) {
                        foreach ($beli_product as $lp) {
                            $_jml_beli[$lp->id_product] = (int)$lp->jml_beli;
                        }
                    }
                }


                foreach ($pricelist_active as $d) {
                    if (empty($d->deleted_at) || $d->deleted_at == '') {
                        $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                        unset($d->created_by);
                        unset($d->updated_by);
                        unset($d->deleted_by);
                        unset($d->created_at);
                        unset($d->updated_at);
                        unset($d->deleted_at);
                        unset($d->deskripsi);
                        unset($d->short_description);
                        unset($d->img);
                        $limit_beli = isset($_limit['id_lp'][$d->id_product]) ? (int)$_limit['limit_beli'][$d->id_product] : 9999999999;
                        $jml_beli = isset($_jml_beli[$d->id_product]) ? (int)$_jml_beli[$d->id_product] : 0;
                        $d->qty_cart = isset($myQty[$d->id_product]) ? (int)$myQty[$d->id_product] : 0;
                        $d->harga = $type == 1 || $type == 3 ? $d->harga_member : $d->harga_konsumen;
                        $d->jml_limit_beli = (int)$limit_beli > 0 ? $limit_beli : 9999999999;
                        $d->jml_beli = $jml_beli;
                        $d->is_limit_beli = (int)$jml_beli >= (int)$d->jml_limit_beli ? 1 : 0;
                        $d->img = $path_img;

                        $data[] = $d;
                    }
                }
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => count($data),
                'data' => $data
            );
        }
        return response($result);
    }


    function test_mail()
    {

        // Mail::raw('mail text', function ($message) {
        // $message->to('hanssn88@gmail.com', 'CNI')->subject('Test Mail CNI');
        // });
        $_data = array('email' => 'hanssn88@gmail.com', 'nama_member' => 'test', 'content_email' => 'content_email');
        Mail::send([], ['users' => $_data], function ($message) use ($_data) {
            $message->to($_data['email'], $_data['nama_member'])->subject('Transaksi Mitra')->setBody($_data['content_email'], 'text/html');
        });
    }

    function test_ewallet(Request $request)
    {
        $cni_id = !empty($request->cni_id) ? $request->cni_id : '';
        $data = Helper::get_ewallet($cni_id);
        $res = $data['saldo'];
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function test_sms(Request $request)
    {
        $nohp = !empty($request->nohp) ? $request->nohp : '';
        $otp = !empty($request->otp) ? $request->otp : '';
        $data = Helper::send_sms($nohp, $otp);
        return response($data);
    }

    function generate_pass_all(Request $request)
    {
        DB::connection()->enableQueryLog();
        $where = array('pass' => null);
        $count = DB::table('members')->where($where)->count();
        Log::info(DB::getQueryLog());
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if ($count > 0) {
            $members = DB::table('members')->select('id_member', 'pass_1st')->where($where)->limit(350)->get();
            foreach ($members as $te) {
                $where = array('pass' => null, 'id_member' => $te->id_member);
                $res = array(
                    'pass_1st' => $te->pass_1st,
                    'pass' => !empty($te->pass_1st) ? Crypt::encryptString(strtolower($te->pass_1st)) : '',
                );
                DB::table('members')->where($where)->update($res);
            }
            Log::info(DB::getQueryLog());
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => ''
            );
        }
        return response($result);
    }


    function generate_pass(Request $request)
    {
        $nama = !empty($request->nama) ? $request->nama : '';
        $cni_id = !empty($request->cni_id) ? $request->cni_id : '';
        $pass = !empty($request->pass) ? $request->pass : '';
        $type = !empty($request->type) ? $request->type : '';
        DB::connection()->enableQueryLog();
        $where = array('pass_1st' => null, 'nama' => $nama, 'type' => $type);
        $count = DB::table('members')->where($where)->count();
        Log::info(DB::getQueryLog());
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if ($count > 0) {
            $members = DB::table('members')->select('id_member')->where($where)->first();
            $res = array(
                'pass_1st' => $pass,
                'pass' => !empty($pass) ? Crypt::encryptString(strtolower($pass)) : '',
            );
            DB::table('members')->where($where)->update($res);
            Log::info(DB::getQueryLog());
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => ''
            );
        }

        return response($result);
    }

    function conf_pass(Request $request)
    {

        DB::connection()->enableQueryLog();
        $where = array('conf_pass' => null);
        $count = DB::table('members')->where($where)->count();
        Log::info(DB::getQueryLog());
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if ($count > 0) {
            $members = DB::table('members')->select('id_member', 'pass_1st')->where($where)->first();
            $id_member = $members->id_member;
            $pass = !empty($members->pass_1st) ? Crypt::encryptString(strtolower($members->pass_1st)) : '';
            $res = array(
                'conf_pass' => $members->pass_1st,
                'pass' => $pass
            );
            DB::table('members')->where(array('id_member' => $id_member))->update($res);
            Log::info(DB::getQueryLog());
            $_data = DB::table('members')->select('id_member', 'nama', 'email', 'cni_id', 'type', 'pass_1st', 'pass', 'conf_pass')->where(array('id_member' => $id_member))->first();
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $_data
            );
        }

        return response($result);
    }

    function cek_cniid(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $cni_id = (int)$request->cni_id > 0 ? (int)$request->cni_id : 0;
        $email = !empty($request->email) ? strtolower($request->email) : '';
        if (empty($cni_id) || $cni_id <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'cni_id is required',
                'data' => null
            );
            return response($result);
            return false;
        }

        if (empty($email)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'email is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'email invalid format',
                'data' => null
            );
            return response($result);
            return false;
        }
        $count = 0;
        $where = ['deleted_at' => null, 'cni_id' => $cni_id];
        $count = Members::where($where)->count();
        if ($count > 0) {
            $result = array(
                'err_code' => '05',
                'err_msg' => 'cni_id already exist',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where_member = array(
            "cni_id" => $cni_id,
            "email" => $email,
            "status" => 0
        );
        DB::table('otp_verify_cni_id')->where($where_member)->update(array('status' => -1, 'updated_at' => $tgl));
        $verify_code = rand(100000, 999999);
        $dt_otp = array(
            "kode_otp" => $verify_code,
            "cni_id" => $cni_id,
            "email" => $email,
            "status" => 0,
            "created_at" => $tgl,
            "updated_at" => $tgl,
        );
        $save = DB::table('otp_verify_cni_id')->insertGetId($dt_otp, "id_otp");
        if ($save) {
            $setting = DB::table('setting')->get()->toArray();
            $out = array();
            if (!empty($setting)) {
                foreach ($setting as $val) {
                    $out[$val->setting_key] = $val->setting_val;
                }
            }
            $content_member = $out['content_verify_cni_id'];
            $content = str_replace('[#name#]', $email, $content_member);
            $content = str_replace('[#kode_otp#]', $verify_code, $content);
            $data['content'] = $content;
            $data['email'] = $email;
            Mail::send([], ['users' => $data], function ($message) use ($data) {
                $message->to($data['email'], $data['email'])->subject('Verify Akun')->setBody($data['content'], 'text/html');
            });
            $data['kode_otp'] = $verify_code;
        }
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function verify_cek_cniid(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $kode = (int)$request->kode_otp;

        if ($kode <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'kode required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where_member = array(
            "kode_otp" => $kode,
            "status" => 0
        );
        $data_otp = DB::table('otp_verify_cni_id')->where($where_member)->first();
        $kodeOtp = !empty($data_otp) ? (int)$data_otp->id_otp : 0;
        if ($kodeOtp <= 0) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'incorrect verification code',
                'data' => $kode
            );
            return response($result);
            return false;
        }
        DB::table('otp_verify_cni_id')->where($where_member)->update(array('status' => 2, 'updated_at' => $tgl));
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $kode
        );
        return response($result);
    }

    function cancel_transaksi(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_transaksi = (int)$request->id_transaksi;
        $result = array();

        if ($id_transaksi <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'kode required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array('transaksi.id_transaksi' => $id_transaksi);
        $cnt = DB::table('transaksi')->where($where)->count();
        if ((int)$cnt <= 0) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Transaksi tidak ditemukan',
                'data' => ''
            );
            return response($result);
            return false;
        }
        $transaksi = DB::table('transaksi')->where($where)->first();
        if ((int)$transaksi->status != 0) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Transaksi tidak bisa dibatalkan',
                'data' => $transaksi
            );
            return response($result);
            return false;
        }
        $payment = (int)$transaksi->payment;
        if ((int)$payment == 3) {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'Transaksi tidak bisa dibatalkan',
                'data' => $transaksi
            );
            return response($result);
            return false;
        }

        $no_va = $transaksi->key_payment;
        $amount = $transaksi->nominal_doku;
        $payment_channel = (int)$transaksi->payment_channel;

        $ewallet = (int)$transaksi->ewallet > 0 ? $transaksi->ewallet : 0;
        $type_voucher = (int)$transaksi->type_voucher > 0 ? $transaksi->type_voucher : 0;
        $id_member = (int)$transaksi->id_member > 0 ? $transaksi->id_member : 0;
        $cni_id = (int)$transaksi->cni_id > 0 ? $transaksi->cni_id : 0;
        $sub_ttl_ongkir_pot_voucher = (int)$transaksi->ttl_price > 0 ? $transaksi->ttl_price : 0;
        $kodevoucher = !empty($transaksi->kode_voucher) ? $transaksi->kode_voucher : '';
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'status' => 0);
        $dt_upd = array("status" => 7, 'cancel_ondate' => $tgl, 'key_payment' => null);
        DB::table('transaksi')->where($where)->update($dt_upd);
        $where = ['id_member' => $id_member];
        $member = Members::where($where)->first();
        $nama = $member->nama;
        $email = $member->email;
        if ($payment == 2) {
            $url_path_doku = env('URL_JOKUL');
            $clientId = env('CLIENT_ID_JOKUL');
            $secretKey = env('SECRET_KEY_JOKUL');
            $requestBody = array(
                'order' => array(
                    'amount' => $amount,
                    'invoice_number' => $id_transaksi,
                ),
                'virtual_account_info' => array(
                    'virtual_account_number' => $no_va,
                    'expired_time' => 10,
                    'reusable_status' => false,
                    "status" => "DELETE",
                ),
                'customer' => array(
                    'name' => !empty($nama) ? $nama : 'CNI',
                    'email' => $email,
                ),
            );
            $requestId = $id_transaksi;
            $targetPath = '';
            if ($payment_channel == 29) {
                $targetPath = "/bca-virtual-account/v2/merchant-payment-code";
            }
            if ($payment_channel == 32) {
                $targetPath = "/cimb-virtual-account/v2/payment-code";
            }
            if ($payment_channel == 33) {
                $targetPath = "/danamon-virtual-account/v2/payment-code";
            }
            if ($payment_channel == 34) {
                $targetPath = "/bri-virtual-account/v2/payment-code";
            }
            if ($payment_channel == 36) {
                $targetPath = "/permata-virtual-account/v2/payment-code";
            }
            if ($payment_channel == 37) {
                $targetPath = "/mandiri-virtual-account/v2/payment-code";
            }
            $dateTimeFinal = gmdate("Y-m-d\TH:i:s\Z", strtotime('- 0 minutes'));
            $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));
            $componentSignature = "Client-Id:" . $clientId . "\n" .
                "Request-Id:" . $requestId . "\n" .
                "Request-Timestamp:" . $dateTimeFinal . "\n" .
                "Request-Target:" . $targetPath . "\n" .
                "Digest:" . $digestValue;
            $signature = base64_encode(hash_hmac('sha256', $componentSignature, $secretKey, true));
            $headers = array(
                'Content-Type: application/json',
                'Client-Id:' . $clientId,
                'Request-Id:' . $requestId,
                'Request-Timestamp:' . $dateTimeFinal,
                'Signature:HMACSHA256=' . $signature
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_path_doku . $targetPath);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

            $res = curl_exec($ch);
            if ($res === FALSE) {
                die('Send Error: ' . curl_error($ch));
            }
            Log::info('CURL Cancel Transaksi');
            Log::info('Request :');
            Log::info($requestBody);
            Log::info('Response :');
            Log::info($res);
            curl_close($ch);
        }
        if ($ewallet > 0) {
            $ket = !empty($ket) ? $ket : "Pembatalan transaksi #" . $id_transaksi;
            $data_ewallet = Helper::trans_ewallet('REALLOCATE_EWALLET', $cni_id, $sub_ttl_ongkir_pot_voucher, $ewallet, $id_transaksi, $request->all(), "cancel_transaksi_member", 1, $ket, 1, $id_member);
            Log::info('trans_ewallet Cancel Transaksi');
            Log::info($data_ewallet);
        }
        if ($type_voucher == 4) Helper::unflagVoucher($cni_id, $kodevoucher, $id_member, $id_transaksi);
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $id_transaksi
        );
        return response($result);
    }

    //
}
