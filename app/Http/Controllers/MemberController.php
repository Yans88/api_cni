<?php

namespace App\Http\Controllers;

use App\Models\Members;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
            $data = DB::table('members')->where($where)->whereRaw("LOWER(nama) like '%" . $keyword . "%'")->get()->toArray();
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
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
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
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'total_data'    => $count,
                'data'      => $_data
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
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => $id_member
        );
        if ((int)$count > 0) {

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
			if($id_token > 0){				
				$fcm_token = DB::table('fcm_token')->where(array('id_token_fcm' => $id_token))->first();
			}
            unset($data->photo);
            unset($data->ewallet);
			$data->id_token_fcm = $id_token;
			$data->token_fcm = isset($fcm_token->token_fcm) ? $fcm_token->token_fcm : '';
            $data->photo = $photo;
            $data->ewallet = 0;
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
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
        $data->email = $request->email;
        $data->phone = !empty($request->phone) ? preg_replace($ptn, $rpltxt, $request->phone) : '';
        $data->cni_id_ref = $request->cni_id_ref;
        $data->nama = $request->nama;
        $data->type = (int)$request->type;
		if($request->has('id_sm') && !empty($request->id_sm)) $data->id_sm = $request->id_sm;
		if($request->has('tipe_sm') && (int)$request->id_sm > 0) $data->tipe_sm = (int)$request->tipe_sm;
        $data->ewallet = 0;
        $verify_code = rand(1000, 9999);
        $data->verify_phone = $verify_code;
        $data->verify_email = $verify_code;
        $data->pass = Crypt::encryptString(strtolower($request->pass));
        $data->created_at = $tgl;
        $data->updated_at = $tgl;
        $result = array();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'not found',
            'data'      => null
        );
        if (empty($data->cni_id) && $data->type == 1) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'cni_id is required',
                'data'      => null
            );
            return response($result);
            return false;
        }

        if (empty($data->email)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'email is required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            $result = array(
                'err_code'    => '06',
                'err_msg'    => 'email invalid format',
                'data'      => null
            );
            return response($result);
            return false;
        }

        if (empty($data->phone)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'phone is required',
                'data'      => null
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
                    'err_code'  => '05',
                    'err_msg'   => 'cni_id already exist',
                    'data'      => null
                );
                return response($result);
                return false;
            }
        }
        $where = ['deleted_at' => null, 'email' => $data->email];
        $count = Members::where($where)->count();
        if (!empty($count)) {
            $result = array(
                'err_code'  => '05',
                'err_msg'   => 'email already exist',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $where = ['deleted_at' => null, 'phone' => $data->phone];
        $count = Members::where($where)->count();
        if (!empty($count)) {
            $result = array(
                'err_code'  => '05',
                'err_msg'   => 'phone already exist',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $save = $data->save();
        Helper::send_sms($data->phone, $verify_code,$request->all(),'register_member');
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
            $content = str_replace('[#kode_otp#]', $verify_code, $content);
            $data->content = $content;
            Mail::send([], ['users' => $data], function ($message) use ($data) {
                $message->to($data->email, $data->nama)->subject('Register')->setBody($data->content, 'text/html');
            });
        }
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
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
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        } else {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'id_member required',
                'data'      => null
            );
        }
        return response($result);
    }

    function login_member(Request $request)
    {
        $count = 0;
        $email = $request->email;
        $cni_id = $request->cni_id;
        $pass = strtolower($request->pass);
        $result = array();
        if (empty($email) && empty($cni_id)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'cni_id or email is required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if (empty($pass)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'password is required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $where = ['deleted_at' => null, 'email' => $email, 'type' => 2];
        if (!empty($cni_id)) {
            $where = ['deleted_at' => null, 'cni_id' => $cni_id, 'type' => 1];
        }

        $count = Members::where($where)->count();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => null
        );
        if ($count > 0) {
            $data = Members::where($where)->first();
            $password = Crypt::decryptString($data->pass);
            if ($pass == $password) {
                unset($data->password);
                unset($data->ewallet);
                $type = (int)$data->type;
                $cni_id = !empty($data->cni_id) ? $data->cni_id : '';
                $res = 0;
                // if ($type == 1 && !empty($cni_id)) {
                    // $data_ewallet = Helper::get_ewallet($cni_id, $request->all(),'login_member');
                    // $res = $data_ewallet['saldo'];
                // }
                Helper::last_login($data->id_member);
                //$data->password = $password;
                $data->ewallet = $res;
                $result = array(
                    'err_code'  => '00',
                    'err_msg'   => 'ok',
                    'data'      => $data
                );
            } else {
                $result = array(
                    'err_code'  => '03',
                    'err_msg'   => 'password not match',
                    'data'      => null
                );
            }
            if ((int)$data->status != 1) {
                $result = array();
                $result = array(
                    'err_code'  => '05',
                    'err_msg'   => 'akun belum diverifikasi',
                    'data'      => $data
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
                'err_code'  => '06',
                'err_msg'   => 'new_pass is required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if (empty($old_pass)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'old_pass is required',
                'data'      => null
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
                    'err_code'  => '03',
                    'err_msg'   => 'old_pass not match',
                    'data'      => null
                );
                return response($result);
                return false;
            }
            $new_pass = strtolower($new_pass);
            if ($password == $new_pass) {
                $result = array(
                    'err_code'  => '02',
                    'err_msg'   => 'new_pass sama dengan password sebelumnya',
                    'data'      => null
                );
                return response($result);
                return false;
            }
            $data->pass = Crypt::encryptString($new_pass);
            $data->updated_at = $tgl;
            $data->updated_by = $id_member;
            $data->save();
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        } else {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'id_member required',
                'data'      => $id_member
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
                'err_code'  => '06',
                'err_msg'   => 'id_member required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if (empty($photo)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'photo required',
                'data'      => null
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
        $photo->move($tujuan_upload, $imageName);
        $data->photo = $imageName;
        $data->updated_at = $tgl;
        $data->updated_by = $id_member;
        $data->save();
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $data,
            'fileSize'      => $fileSize,
            'extension'     => $extension,
            'imageName'     => $imageName,
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
                'err_code'  => '06',
                'err_msg'   => 'id_member required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if ($kode <= 0) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'kode required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $data = Members::where('id_member', $id_member)->first();
        if ($kode != (int)$data->verify_phone) {
            $result = array(
                'err_code'  => '02',
                'err_msg'   => 'kode not match',
                'data'      => $data->verify_phone
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
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $data
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
                'err_code'  => '03',
                'err_msg'   => 'phone sudah terverifikasi sebelumnya',
                'data'      => null
            );
            return response($result);
            return false;
        }
        if (empty($data->verify_phone)) {
            $verify_code = rand(1000, 9999);
            $data->verify_phone = $verify_code;
            $data->updated_at = $tgl;
            $data->updated_by = $id_member;
            $data->save();
            //Helper::send_sms($data->phone, $verify_code);
        }
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
        $content = str_replace('[#kode_otp#]', $data->verify_phone, $content);
        $data->content = $content;
        Mail::send([], ['users' => $data], function ($message) use ($data) {
            $message->to($data->email, $data->nama)->subject('Register')->setBody($data->content, 'text/html');
        });
        $result = array(
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $data
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
                'err_code'  => '03',
                'err_msg'   => 'email sudah terverifikasi sebelumnya',
                'data'      => null
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
            'err_code'      => '00',
            'err_msg'       => 'ok',
            'data'          => $data
        );
        return response($result);
    }

    function forgot_pass(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $email = $request->email;
        if (!empty($email)) {
            $cnt = Members::whereRaw("LOWER(email) = '" . strtolower($email) . "'")->count();
			if((int)$cnt > 0){
				$data = Members::whereRaw("LOWER(email) = '" . strtolower($email) . "'")->first();
				if ((int)$data->verify_email <= 0) {
					$result = array(
						'err_code'  => '07',
						'err_msg'   => 'email belum terverifikasi',
						'data'      => null
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
					'err_code'  => '00',
					'err_msg'   => 'ok',
					'data'      => $data
				);
				
			}else{
				$result = array(
					'err_code'  => '04',
					'err_msg'   => 'email not found',
					'data'      => null
				);
			}
            
        } else {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'email required',
                'data'      => null
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
                'err_code'  => '06',
                'err_msg'   => 'id_member or id product required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('id_member' => $id_member, 'id_product' => $id_product);
        $res_wishlist = DB::table('wishlist')->where($where)->count();
        if ((int)$res_wishlist > 0) {
            $result = array(
                'err_code'  => '07',
                'err_msg'   => 'id product sudah ditambahkan sebelumnya',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $where += array('created_at' => $tgl);
        DB::table('wishlist')->insert($where);
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $where
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
                'err_code'  => '06',
                'err_msg'   => 'id_member or id product required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('id_member' => $id_member, 'id_product' => $id_product);

        DB::table('wishlist')->where($where)->delete();
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => null
        );
        return response($result);
    }

    function get_mywishlist(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'wishlist.id';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $result = array();
        if ($id_member <= 0) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'id member required',
                'data'      => null
            );
            return response($result);
            return false;
        }
        $_data = array();
        $data = array();
        $count = 0;
        $where = array('product.deleted_at' => null, 'wishlist.id_member' => $id_member);
        if (!empty($keyword)) {
            $_data = DB::table('product')->select('product.product_name','kode_produk','short_description','wishlist.*')
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
            $_data = DB::table('product')->select('product.product_name', 'product.img','kode_produk','short_description','wishlist.*')
                ->leftJoin('wishlist', 'wishlist.id_product', '=', 'product.id_product')
                ->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ((int)$count > 0) {
            foreach ($_data as $d) {
                $path_img = null;
                $path_img  = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                unset($d->img);
                $d->img = $path_img;
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

    function add_address(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $id = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $data = array(
            "label_alamat"  => $request->label_alamat,
            "nama_penerima" => $request->nama_penerima,
            "phone_penerima"    => $request->phone_penerima,
            "id_provinsi"   => (int)$request->id_provinsi,
            "id_city"       => (int)$request->id_city,
            "id_kec"        => (int)$request->id_kec,
            "alamat"        => $request->alamat,
            "kode_pos"      => $request->kode_pos
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
			'provinsi.deleted_at' 		=> null, 
			'city.deleted_at' 			=> null, 
			'kecamatan.deleted_at' 		=> null, 
			'address_member.id_member' 	=> (int)$id_member
		);
        $result = array();
        if ($id_member <= 0) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'id member required',
                'data'      => null
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
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
                unset($d->created_at);
                unset($d->updated_at);
                unset($d->deleted_at);
                $data[] = $d;
            }
            $result = array(
                'err_code'      => '00',
                'err_msg'       => 'ok',
                'total_data'    => $count,
                'data'          => $data
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
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => null
        );
        return response($result);
    }

    function history_transaksi(Request $request)
    {
        $tgl = Carbon::now();
        $data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $status = (int)$request->status >= 0 ? (int)$request->status : -1;
        $sql = "select id_transaksi,ewallet,ttl_price, id_member,type_member from transaksi where id_member=$id_member and status=0 and expired_payment::timestamp <= '" . $tgl . "'";
        $trans_expired = DB::select(DB::raw($sql));
		$whereIn = array();
		$dt_refund_ewallet = array();
        if(count($trans_expired) > 0){
            //update status ke expired payment
            foreach($trans_expired as $te){
                $whereIn[] = $te->id_transaksi;
				$dt_refund_ewallet[] = array(
						'id_transaksi'	=> $te->id_transaksi,
						'ewallet'		=> $te->ewallet,
						'ttl_price'		=> $te->ttl_price,
						'id_member'		=> $te->id_member,
						'status'		=> 0,
						'created_at'	=> $tgl
					);
            }
			DB::table('transaksi')->where(array("status" => 0))
                ->whereIn('id_transaksi', $whereIn)->update(array("status"=>2,"cek_refund_ewallet"=>1));
			if(!empty($dt_refund_ewallet)) DB::table('refund_ewallet')->insert($dt_refund_ewallet);
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
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
        if ($count > 0) {
			$_data = DB::table('transaksi')->select(
				'transaksi.*',
				'members.nama as nama_member',
				'members.email',
				'members.phone as phone_member',
				'members.cni_id'
			)
				->where($where)->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
				->orderByRaw($sort_column)->get();
			$whereIn = array();
			foreach($_data as $_d){
				$whereIn[] = $_d->id_transaksi;
			}
			$data_trans = DB::table('transaksi_detail')->whereIn('id_trans', $whereIn)->get();
			$_dt = array();
			foreach($data_trans as $dt){
				$_dt[$dt->id_trans][] = $dt;
			}
            foreach ($_data as $d) {
                $status = $d->status;
                $key_payment =  (int)$status == 0 ? $d->key_payment : '';
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
                $d->status = $status;
				$d->list_item = !empty($dt) && !empty($_dt[$d->id_transaksi]) ? $_dt[$d->id_transaksi] : null;
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
	
	function akun_fb(Request $request){
		$data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$fb = !empty($request->fb) ? $request->fb : '';
		$data = array('fb' => $fb);
		DB::table('members')->where('id_member', $id_member)->update($data);
		$data += array('id_member' => $id_member);
		$result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
        );
        return response($result);
	}
	
	function akun_ig(Request $request){
		$data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$ig = !empty($request->ig) ? $request->ig : '';
		$data = array('ig' => $ig);
		DB::table('members')->where('id_member', $id_member)->update($data);
		$data += array('id_member' => $id_member);
		$result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
        );
        return response($result);
	}
	
	function add_sm(Request $request){
		$tgl = date('Y-m-d H:i:s');
		$id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$tipe = $request->has('tipe') ? $request->tipe : '';
		$nama = $request->has('nama') ? $request->nama : '';
		$email = $request->has('email') ? $request->email : '';
		$id_sm = $request->has('id_sm') ? $request->id_sm : '';
		$cnt = DB::table('list_social_media')->where(array('id_sm'=>$id_sm))->count();
		$result = array();
		if((int)$cnt > 0){
			$result = array(
                'err_code'  => '07',
                'err_msg'   => 'id_sm already exist',
                'data'      => null
            );
            return response($result);
            return false;
		}
		$data = array(
			'id_member' 	=> $id_member,
			'tipe' 			=> (int)$tipe,
			'nama' 			=> $nama,
			'email' 		=> $email,
			'id_sm' 		=> $id_sm,
			'created_at' 	=> $tgl
		);
		$id = DB::table('list_social_media')->insertGetId($data, "id");
		
        if ($id > 0) {
            $data += array('id' => $id);
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
	
	function get_cniid_sm(Request $request){
		$keyword  = $request->has('keyword') ? $request->keyword : '';
		$tipe = $request->has('tipe') ? (int)$request->tipe : 0;
		$where = array();
		if((int)$tipe > 0)  $where = array('list_social_media.tipe' => $tipe);
		$cnt = DB::table('list_social_media')->where($where)->where(function($query) use ($keyword) {
                $query->whereRaw("list_social_media.nama like '%" . $keyword . "%' or list_social_media.id_sm like '%" . $keyword . "%' ");
            })->count();
		$result = array();
		$result = array(
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => $keyword
        );
		if((int)$cnt > 0){
			$data = DB::table('list_social_media')->select('list_social_media.*','members.nama as nama_member','members.cni_id')
			->where($where)->where(function($query) use ($keyword) {
				$query->whereRaw("list_social_media.nama like '%" . $keyword . "%' or list_social_media.id_sm like '%" . $keyword . "%' ");
                //$query->where('list_social_media.nama', $keyword)
                     
            })->leftJoin('members', 'members.id_member', '=', 'list_social_media.id_member')->get();
			$result = array(
				'err_code'  => '00',
				'err_msg'   => 'ok',
				'data'      => $data
			);
		}
		return response($result);
	}
	
	function get_list_sm(Request $request){
		$id_member = $request->has('id_member') ? (int)$request->id_member : 0;		
		$tipe = $request->has('tipe') ? (int)$request->tipe : 0;	
		$where = array();
		$where = array('list_social_media.id_member'=>$id_member);
		if((int)$tipe > 0) $where += array('list_social_media.tipe' => $tipe);
		$data_sm = DB::table('list_social_media')->select('list_social_media.*','members.nama as nama_member','members.cni_id')
			->where($where)
			->leftJoin('members', 'members.id_member', '=', 'list_social_media.id_member')->get();
		
		$list_dr = array();
		$result = array(
			'err_code'  => '04',
			'err_msg'   => 'data not found',
			'data'      => null
		);
		if((int)count($data_sm) > 0){
			$cni_id = isset($data_sm) ? $data_sm[0]->cni_id : '';
			if(!empty($cni_id)){
				$data_ref_cnt = DB::table('members')->where(array('cni_id_ref'=>$cni_id))->count();
				if((int)$data_ref_cnt > 0){
					$data_ref = DB::table('members')->where(array('cni_id_ref'=>$cni_id))->get();
					foreach($data_ref as $dr){
						$list_dr[$dr->cni_id_ref][$dr->tipe_sm][$dr->id_sm][] = $dr->id_member; 
					}
				}
			}
			foreach($data_sm as $ds){
				$ds->cnt_follower = isset($list_dr[$ds->cni_id][$ds->tipe][$ds->id_sm]) ? count($list_dr[$ds->cni_id][$ds->tipe][$ds->id_sm]) : 0;
				$list_sm[] = $ds;
			}
			$result = array(
				'err_code'  => '00',
				'err_msg'   => 'ok',
				'data'      => $data_sm
			);
		}
		return response($result);
	}
	
	function get_list_follower_sm(Request $request){
		$id_member = $request->has('id_member') ? (int)$request->id_member : 0;		
		$tipe = $request->has('tipe') ? (int)$request->tipe : 0;
		$id_sm = $request->has('id_sm') ? $request->id_sm : '';
		$where = array('list_social_media.id_member'=>$id_member, 'list_social_media.id_sm'=>$id_sm);
		if((int)$tipe > 0) $where += array('list_social_media.tipe' => $tipe);
		$data_cnt = DB::table('list_social_media')->where($where)->count();
		$list_dr = array();
		$result = array(
			'err_code'  => '04',
			'err_msg'   => 'data not found',
			'data'      => null
		);
		$data_ref_cnt = 0;
		if((int)$data_cnt > 0){
			$data_sm = DB::table('list_social_media')->select('list_social_media.*','members.nama as nama_member','members.cni_id')
				->where($where)
				->leftJoin('members', 'members.id_member', '=', 'list_social_media.id_member')->first();
			$cni_id = isset($data_sm) ? $data_sm->cni_id : '';
			$tipe = isset($data_sm) ? (int)$data_sm->tipe : 0;
			if(!empty($cni_id)){
				$wheree = array();
				$wheree = array('cni_id_ref'=>$cni_id,'id_sm'=>$id_sm,'tipe_sm'=>(int)$tipe);
				$data_ref_cnt = DB::table('members')->where($wheree)->count();
				$data_ref = array();
				if((int)$data_ref_cnt > 0){
					$data_ref = DB::table('members')->select('id_member','cni_id','nama','email','phone')->where($wheree)->get();
					
				}
			}
			$data_sm->cnt_follower = $data_ref_cnt;
			$data_sm->list_follower = $data_ref;
			$result = array(
				'err_code'  	=> '00',
				'err_msg'   	=> 'ok',
				'data'      	=> $data_sm
			);
		}
		return response($result);
	}
	
	function akun_bank(Request $request){
		$data = array();
        $result = array();
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$nama_bank = !empty($request->nama_bank) ? $request->nama_bank : '';
		$nama_rek = !empty($request->nama_rek) ? $request->nama_rek : '';
		$no_rek = !empty($request->no_rek) ? $request->no_rek : '';
		$data = array(
			'nama_bank' => $nama_bank,
			'nama_rek' 	=> $nama_rek,
			'no_rek' 	=> $no_rek
		);
		DB::table('members')->where('id_member', $id_member)->update($data);
		$data += array('id_member' => $id_member);
		$result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
        );
        return response($result);
	}
	
	function req_cashout(Request $request){
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
			// return $false;
		// }
		$data = Members::where(array('id_member'=>$id_member))->first();
        $type = (int)$data->type;
        $cni_id = !empty($data->cni_id) ? $data->cni_id : '';		
		
		if($type != 1){
			$dt = array(
				"err_code"      => "07",
				"result"        => "Type member tidak sesuai",
				"err_msg"       => "Type member tidak sesuai",
				"data"			=> $data
			   
			);
			return response($dt);
			return $false;
		}
		if(empty($cni_id)){
			$dt = array(
				"err_code"      => "04",
				"result"        => "CNI ID tidak ditemukan",
				"err_msg"       => "CNI ID tidak ditemukan",
				"data"			=> $data
			   
			);
			return response($dt);
			return $false;
		}
		$url = env('URL_REQUEST_CASHOUT');
        $token = env('TOKEN_REQUEST_CASHOUT');
		$postfields = array(
            "nomorn" => $cni_id,
            "token" => $token,
			"nominalcashout"=>$nominal
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
        $_res =  json_encode($response);
        $res =  json_decode($_res);
		$dt_log = array();
		$dt_history = array();
		$dt_log = array(
			"api_name"		=> "req_cashout",
			"param_from_fe"	=> serialize($request->all()),
			"param_to_cni"	=> serialize($postfields),
			"endpoint"		=> $url,
			"responses"		=> serialize($res),
			"id_transaksi"	=> $id_member,
			"created_at"	=> $tgl
		);
		DB::table('log_api')->insert($dt_log);
		return response($res)->header('Content-Type', "application/json");
	}
	
	function history_cashout(Request $request){
		$result = array();
		$tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$data = Members::where(array('id_member'=>$id_member))->first();
        $type = isset($data) && (int)$data->type > 0 ? (int)$data->type : 2;
        $cni_id = isset($data) && !empty($data->cni_id) ? $data->cni_id : '';
		if($type != 1){
			$result = array(
				"err_code"      => "07",
				"result"        => "Type member tidak sesuai",
				"err_msg"       => "Type member tidak sesuai",
				"data"			=> $data
			   
			);
			return response($result);
			return $false;
		}
		if(empty($cni_id)){
			$result = array(
				"err_code"      => "04",
				"result"        => "CNI ID tidak ditemukan",
				"err_msg"       => "CNI ID tidak ditemukan",
				"data"			=> $data
			   
			);
			return response($result);
			return $false;
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
        $_res =  json_encode($response);
        $res =  json_decode($_res);
       
		$dt_log = array();
		$dt_history = array();
		$dt_log = array(
			"api_name"		=> "history_cashout",
			"param_from_fe"	=> serialize($request->all()),
			"param_to_cni"	=> serialize($postfields),
			"endpoint"		=> $url,
			"responses"		=> serialize($res),
			"id_transaksi"	=> $id_member,
			"created_at"	=> $tgl
		);
		DB::table('log_api')->insert($dt_log);		
		return response($res)->header('Content-Type', "application/json");	
		
	}

	function history_wallet(Request $request){
		$result = array();
		$tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$data = Members::where(array('id_member'=>$id_member))->first();
        $type = isset($data) && (int)$data->type > 0 ? (int)$data->type : 2;
        $cni_id = isset($data) && !empty($data->cni_id) ? $data->cni_id : '';
		if($type != 1){
			$result = array(
				"err_code"      => "07",
				"result"        => "Type member tidak sesuai",
				"err_msg"       => "Type member tidak sesuai",
				"data"			=> $data
			   
			);
			return response($result);
			return $false;
		}
		if(empty($cni_id)){
			$result = array(
				"err_code"      => "04",
				"result"        => "CNI ID tidak ditemukan",
				"err_msg"       => "CNI ID tidak ditemukan",
				"data"			=> $data
			   
			);
			return response($result);
			return $false;
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
        $_res =  json_encode($response);
        $res =  json_decode($_res);
       
		$dt_log = array();
		$dt_history = array();
		$dt_log = array(
			"api_name"		=> "history_wallet",
			"param_from_fe"	=> serialize($request->all()),
			"param_to_cni"	=> serialize($postfields),
			"endpoint"		=> $url,
			"responses"		=> serialize($res),
			"id_transaksi"	=> $id_member,
			"created_at"	=> $tgl
		);
		DB::table('log_api')->insert($dt_log);		
		return response($res)->header('Content-Type', "application/json");	
		
	}
	
	function set_token_fcm(Request $request){
		$tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id = (int)$request->id_token_fcm  > 0 ? (int)$request->id_token_fcm  : 0;
		$token_fcm = $request->token_fcm;
		if ($id_member <= 0) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'id_member required',
                'data'      => null
            );
            return response($result);
            return false;
        }
		$data = array(
			'id_member'	=> $id_member,
			'token_fcm'	=> $token_fcm
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
	
	function history_notif(Request $request){		
        $sort_column = "id_notif::integer DESC";
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
		$where = array('history_notif.id_member' => $id_member);
		$count = DB::table('history_notif')->where($where)->count();
		
		$result = array();
		$result = array(
            'err_code'      => '04',
            'err_msg'       => 'data not found',
            'total_data'    => $count,
            'data'          => null
        );
		if($count > 0){
			$data = DB::table('history_notif')->where($where)->orderByRaw($sort_column)->get();
			$result = array(
				'err_code'      => '00',
				'err_msg'       => 'ok',
				'total_data'    => $count,
				'data'          => $data
			);
		}
		 return response($result);
	}


    function test_mail()
    {

        Mail::raw('mail text', function ($message) {
            $message->to('hanssn88@gmail.com', 'CNI')->subject('Test Mail CNI');
        });
    }

    function test_ewallet(Request $request)
    {
        $cni_id = !empty($request->cni_id) ? $request->cni_id : '';
        $data = Helper::get_ewallet($cni_id);
        $res = $data['saldo'];
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok',
            'data'      => $data
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

    //
}
