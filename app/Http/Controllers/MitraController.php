<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MitraController extends Controller
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

        $where = array('deleted_at' => null);

        $count = 0;
        $_data = array();
        $data = null;
        if (!empty($keyword)) {
            $_data = DB::table('reg_mitra')->where($where)->whereRaw("LOWER(nama) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('reg_mitra')->where($where)->count();
            //$count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('reg_mitra')->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            foreach ($_data as $d) {
                $path_img = null;
                $path_img = !empty($d->photo_identitas) ? env('APP_URL') . '/api_cni/uploads/members/' . $d->photo_identitas : null;
                unset($d->created_by);
                unset($d->updated_by);
                unset($d->deleted_by);
                unset($d->updated_at);
                unset($d->deleted_at);
                unset($d->photo_identitas);
                $d->photo_identitas = $path_img;
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
        $ptn = "/^0/";
        $rpltxt = "62";
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('YmdHi');
        $data = array();
        $id = (int)$request->id_reg_mitra > 0 ? (int)$request->id_reg_mitra : 0;
        $id_address = (int)$request->id_address > 0 ? (int)$request->id_address : 0;
        $email = !empty($request->email) ? strtolower($request->email) : '';
        $phone = !empty($request->phone) ? preg_replace($ptn, $rpltxt, $request->phone) : '';
        $path_img = $request->file("photo_identitas");
        if ((int)$request->id_member_reg <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_member_reg is required',
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

        if (empty($phone)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'phone is required',
                'data' => null
            );
            return response($result);
            return false;
        }

        $where_validate = array();
        $res_id = 0;
        $where_validate = array('deleted_at' => null, 'email' => strtolower($email));
        $dt_validate = DB::table('members')->select('id_member')->where($where_validate)->first();
        $res_id = !empty($dt_validate) ? $dt_validate->id_member : 0;
        if ((int)$res_id > 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'Email already exist',
                'data' => null
            );
            return response($result);
            return false;
        }

        $where_validate = array();
        $res_id = 0;
        $where_validate = array('deleted_at' => null, 'email' => strtolower($email));
        $dt_validate = DB::table('reg_mitra')->select('id_reg_mitra')->where($where_validate)->first();
        $res_id = !empty($dt_validate) ? $dt_validate->id_reg_mitra : 0;
        if ((int)$res_id > 0 && $res_id != $id) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'Email already exist',
                'data' => null
            );
            return response($result);
            return false;
        }

        $where_validate = array();
        $res_id = 0;
        $where_validate = array('deleted_at' => null, 'phone' => strtolower($phone));
        $dt_validate = DB::table('members')->select('id_member')->where($where_validate)->first();
        $res_id = !empty($dt_validate) ? $dt_validate->id_member : 0;
        if ((int)$res_id > 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'Phone already exist',
                'data' => null
            );
            return response($result);
            return false;
        }

        $where_validate = array();
        $res_id = 0;
        $where_validate = array('deleted_at' => null, 'phone' => strtolower($phone));
        $dt_validate = DB::table('reg_mitra')->select('id_reg_mitra')->where($where_validate)->first();
        $res_id = !empty($dt_validate) ? $dt_validate->id_reg_mitra : 0;
        if ((int)$res_id > 0 && $res_id != $id) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'Phone already exist',
                'data' => null
            );
            return response($result);
            return false;
        }

        $data = array(
            'id_member_reg' => $request->id_member_reg,
            'phone' => $phone,
            'email' => strtolower($email),
            'nama' => $request->nama,
            'tempat_lahir' => $request->tempat_lahir,
            'tgl_lahir' => !empty($request->tgl_lahir) ? date("Y-m-d", strtotime($request->tgl_lahir)) : null,
            'type' => $request->type,
            'no_identitas' => $request->no_identitas,
            'upline_id' => $request->upline_id,
            'nama_upline' => $request->nama_upline,
            'sponsor_id' => $request->sponsor_id,
            'nama_sponsor' => $request->nama_sponsor,
            'status' => 0,
        );
        $data_alamat = array(
            "label_alamat" => "-",
            "nama_penerima" => $request->nama,
            "phone_penerima" => $phone,
            "id_provinsi" => (int)$request->id_provinsi,
            "id_city" => (int)$request->id_city,
            "id_kec" => (int)$request->id_kec,
            "alamat" => $request->alamat,
            "kode_pos" => $request->kode_pos
        );
        if (!empty($path_img)) {
            $nama = str_replace(' ', '', $request->nama);
            if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
            $nama = strtolower($nama);
            $nama_file = $_tgl . '' . $nama;
            $nama_file = Crypt::encryptString($nama_file);
            $fileSize = $path_img->getSize();
            $extension = $path_img->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/members';
            $_extension = array('png', 'jpg', 'jpeg');
            if ($fileSize > 2099200) { // satuan bytes
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'file size over 2048',
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
            $path_img->move($tujuan_upload, $imageName);
            $data += array("photo_identitas" => $imageName);
        }
        if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => $request->id_member_reg);
            DB::table('reg_mitra')->where('id_reg_mitra', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "id_transaksi" => 0, "cni_id" => "");
            $id = DB::table('reg_mitra')->insertGetId($data, "id_reg_mitra");
        }
        $result = array();
        if ($id > 0) {
            if ($id_address > 0) {
                $data_alamat += array("updated_at" => $tgl);
                DB::table('address_member')->where('id_address', $id)->update($data_alamat);
            } else {
                $data_alamat += array("created_at" => $tgl, "id_member" => 0);
                $id_address = DB::table('address_member')->insertGetId($data_alamat, "id_address");
                DB::table('reg_mitra')->where('id_reg_mitra', $id)->update(array("id_address" => $id_address));
            }
            $data += array('id_reg_mitra' => $id);
            $data += array('id_address' => $id_address);
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

    function history_mitra(Request $request)
    {
        $result = array();
        $_data = array();
        $id_member = (int)$request->id_member > 0 ? (int)$request->id_member : 0;
        $status = (int)$request->status > 0 ? (int)$request->status : 0;
        $where = array('reg_mitra.id_member_reg' => $id_member);
        if ($status == 8) $where += array("status" => $status);
        $_data = DB::table('reg_mitra')->where($where)->orderBy('id_reg_mitra', 'DESC')->get();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => null
        );
        if (!empty($_data)) {
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $_data
            );
        }
        return response($result);
    }

    function detail(Request $request)
    {
        $result = array();
        $_data = array();
        $id_reg_mitra = (int)$request->id_reg_mitra > 0 ? (int)$request->id_reg_mitra : 0;
        $where = array('reg_mitra.id_reg_mitra' => $id_reg_mitra);
        $_data = DB::table('reg_mitra')->select(
            'reg_mitra.*',
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
            ->where($where)
            ->leftJoin('address_member', 'address_member.id_address', '=', 'reg_mitra.id_address')
            ->leftJoin('kecamatan', 'kecamatan.id_kecamatan', '=', 'address_member.id_kec')
            ->leftJoin('city', 'city.id_city', '=', 'address_member.id_city')
            ->leftJoin('provinsi', 'provinsi.id_provinsi', '=', 'address_member.id_provinsi')
            ->leftJoin('warehouse', 'warehouse.id_wh', '=', 'provinsi.id_wh')->first();
        $photo_identitas = !empty($_data->photo_identitas) ? env('APP_URL') . '/api_cni/uploads/members/' . $_data->photo_identitas : '';
        unset($_data->photo_identitas);
        $_data->photo_identitas = $photo_identitas;
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $_data
        );
        return response($result);
    }

    function verify_token(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $_data = array();
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        $token = (int)$request->token > 0 ? (int)$request->token : 0;
        if ((int)$id_transaksi <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_transaksi is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ((int)$token <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'token is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('id_transaksi' => $id_transaksi, 'is_regmitra' => 1);
        $cnt_data = DB::table('transaksi')->where($where)->count();
        if ((int)$cnt_data > 0) {
            $_data = DB::table('transaksi')->select('token_mitra')->where($where)->first();
            $token_mitra = !empty($_data->token_mitra) ? Crypt::decryptString($_data->token_mitra) : '';
            if (!empty($token_mitra)) {
                if ($token_mitra == $token) {
                    DB::table('transaksi')->where($where)->update(array("token_mitra" => 0));
                    $_data = DB::table('transaksi')->where($where)->first();
                    DB::table('reg_mitra')->where(array("id_transaksi" => $id_transaksi))->update(array('status' => 8, 'updated_at' => $tgl));
                    $result = array(
                        'err_code' => '00',
                        'err_msg' => 'ok',
                        'data' => $_data
                    );
                } else {
                    $result = array(
                        'err_code' => '03',
                        'err_msg' => 'token tidak sesuai',
                        'data' => null
                    );
                }
            } else {
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'transaksi invalid',
                    'data' => null
                );
            }
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'data not found',
                'data' => null
            );
        }
        return response($result);
    }

    function resend_token(Request $request)
    {
        $result = array();
        $_data = array();
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        if ((int)$id_transaksi <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_transaksi is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'is_regmitra' => 1);
        $cnt_data = DB::table('transaksi')->where($where)->count();
        if ((int)$cnt_data > 0) {
            $_data = DB::table('transaksi')->select(
                'transaksi.*',
                'reg_mitra.nama as nama_member',
                'reg_mitra.email',
                'reg_mitra.phone as phone_member'

            )->where($where)->leftJoin('reg_mitra', 'reg_mitra.id_transaksi', '=', 'transaksi.id_transaksi')->first();

            $token_mitra = !empty($_data->token_mitra) ? Crypt::decryptString($_data->token_mitra) : '';
            if (!empty($token_mitra)) {
                $data_item = DB::table('transaksi_detail')->select('product_name', 'img', 'harga', 'jml')->where('id_trans', $id_transaksi)->get();
                $setting = DB::table('setting')->get()->toArray();
                $out = array();
                if (!empty($setting)) {
                    foreach ($setting as $val) {
                        $out[$val->setting_key] = $val->setting_val;
                    }
                }
                $content_email_hold_cust = $out['content_email_otp_mitra'];
                $content_email_hold_cust = str_replace('[#kode_otp#]', $token_mitra, $content_email_hold_cust);                
                $content_email_hold_cust = str_replace('[#ttl_bayar#]', number_format($_data->sub_ttl), $content_email_hold_cust);
                $content_email_hold_cust = str_replace('[#cara_bayar#]', $cara_bayar, $content_email_hold_cust);
                $content_email_hold_cust = str_replace('[#key_payment#]', $_data->key_payment, $content_email_hold_cust);
				$html = '<table cellpadding="0" cellspacing="0" border="0" width="80%" style="border-collapse:collapse;color:rgba(49,53,59,0.96);">
							<tbody>';
                foreach ($data_item as $di) {
                    $html .= '<tr>';
                    $html .= '<td valign="top" width="64" style="padding:0 0 16px 0">
					<img src="' . $di->img . '" width="64" style="border-radius:8px" class="CToWUd"></td>';
                    $html .= '<td valign="top" style="padding:0 0 16px 16px">
								<div style="margin:0 0 4px;line-height:16px">' . $di->product_name . '</div>
								<p style="font-weight:bold;margin:4px 0 0">' . number_format($di->jml) . ' x
									<span style="font-weight:bold;font-size:14px;color:#fa591d">Rp. ' . number_format($di->harga) . '</span>
								</p>
							</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody></table>';
                $content_email_hold_cust = str_replace('[#detail_pesanan#]', $html, $content_email_hold_cust);

                $_data->content_email = $content_email_hold_cust;
                $mail = Mail::send([], ['users' => $_data], function ($message) use ($_data) {
                    $message->to($_data->email, $_data->nama_member)->subject('Transaksi Mitra')->setBody($_data->content_email, 'text/html');
                });
                //Log::info(serialize($mail));
                $result = array(
                    'err_code' => '00',
                    'err_msg' => 'ok',
                    'data' => $token_mitra
                );
            } else {
                $result = array(
                    'err_code' => '05',
                    'err_msg' => 'transaksi invalid',
                    'data' => null
                );
            }
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'data not found',
                'data' => null
            );
        }
        return response($result);
    }

    function cancel_transaksi(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $result = array();
        $_data = array();
        $id_transaksi = (int)$request->id_transaksi > 0 ? (int)$request->id_transaksi : 0;
        if ((int)$id_transaksi <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_transaksi is required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array();
        $where = array('transaksi.id_transaksi' => $id_transaksi, 'is_regmitra' => 1, 'status' => 0);
        $cnt_data = DB::table('transaksi')->where($where)->count();
        if ((int)$cnt_data > 0) {
            $dt_update_mitra = array(
                'id_transaksi' => 0,
                'id_transaksi_cancel' => $id_transaksi,
                'status' => 0,
                'updated_at' => $tgl
            );
            $dt_update_trans = array(
                'cancel_ondate' => $tgl,
                'status' => 6,
                'token_mitra' => 0,
            );
            DB::table('reg_mitra')->where(array("id_transaksi" => $id_transaksi))->update($dt_update_mitra);
            DB::table('transaksi')->where(array("id_transaksi" => $id_transaksi))->update($dt_update_trans);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $dt_update_mitra
            );
        } else {
            $result = array(
                'err_code' => '04',
                'err_msg' => 'data not found',
                'data' => null
            );
        }
        return response($result);
    }
}
