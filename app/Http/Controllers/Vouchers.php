<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Vouchers extends Controller
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
        $tgl = date('Y-m-d H:i:s');
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'title';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $is_cms = (int)$request->is_cms > 0 ? 1 : 0;
        $column_int = array("id_voucher");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = ['vouchers.deleted_at' => null];
        $count = 0;
        $_data = array();
        $data = array();

        $sql = "select id_voucher from vouchers where vouchers.deleted_at is null";
        $voucher_valid = DB::select(DB::raw($sql));
        $whereIn = array();
        if (!empty($voucher_valid)) {
            foreach ($voucher_valid as $pa) {
                $whereIn[] = $pa->id_voucher;
            }
        }
        if (count($whereIn) > 0) {
            if (!empty($keyword)) {
                $_data = DB::table('vouchers')->select('vouchers.*')->where($where)
                    ->whereIn('id_voucher', $whereIn)->whereRaw("LOWER(vouchers.title) like '%" . $keyword . "%'")->get();
                $count = count($_data);
            } else {
                $count = DB::table('vouchers')->where($where)->whereIn('id_voucher', $whereIn)->count();
                //$count = count($ttl_data);
                $per_page = $per_page > 0 ? $per_page : $count;
                $offset = ($page_number - 1) * $per_page;
                $_data = DB::table('vouchers')->select('vouchers.*')->where($where)
                    ->whereIn('id_voucher', $whereIn)->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
            }
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        $_dp = array();
        if ($count > 0) {
            $data_produk = DB::table('product')->select('product.product_name', 'id_product')->get();
            if (!empty($data_produk)) {
                foreach ($data_produk as $dp) {
                    $_dp[$dp->id_product] = $dp->product_name;
                }
            }
            foreach ($_data as $d) {
                $path_img = null;
                $path_file = null;
                $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/vouchers/' . $d->img : null;
                $path_file = !empty($d->path_file) ? env('APP_URL') . '/api_cni/uploads/vouchers/' . $d->path_file : null;
                unset($d->created_by);
                unset($d->updated_by);
                unset($d->deleted_by);
                unset($d->created_at);
                unset($d->updated_at);
                unset($d->deleted_at);
                unset($d->img);
                $produk_utama_name = '';
                $produk_bonus_name = '';
                if ($d->tipe == 3) {
                    $produk_utama_name = isset($_dp[$d->produk_utama]) ? $_dp[$d->produk_utama] : '';
                    $produk_bonus_name = isset($_dp[$d->produk_bonus]) ? $_dp[$d->produk_bonus] : '';
                }
                $d->produk_utama_name = $produk_utama_name;
                $d->produk_bonus_name = $produk_bonus_name;
                $d->img = $path_img;
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

    function get_voucher_available(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = $request->has('id_member') ? (int)$request->id_member : 0;
        $platform = $request->has('platform') ? (int)$request->platform : 0;
        $total_belanjaan = $request->has('total_belanjaan') ? str_replace(',', '', $request->total_belanjaan) : 0;
        $list_item = $request->has('list_item') ? json_decode($request->list_item) : '';
        $where = array('id_member' => $id_member, 'deleted_at' => null);
        $list_member_voucher = DB::table('list_member_voucher')->select('id_voucher', 'is_used')->where($where)->get();
        $member = DB::table('members')->where($where)->first();
        $tipe_member = isset($member) ? (int)$member->type : 0;
        $keyword = !empty($request->keyword) ? strtoupper($request->keyword) : '';
        $id_product_item = array();
        $whereIn_id_product = array();
        $data = [];
        $_whereIn = '';
        if (!empty($list_item)) {
            for ($i = 0; $i < count($list_item); $i++) {
                $whereIn_id_product[] = $list_item[$i]->id_product;
                $_whereIn = implode(', ', $whereIn_id_product);
                array_push($id_product_item, $list_item[$i]->id_product);
            }
        }

        $sql_list_product = "select id_produk as id_product, id_voucher from list_produk_voucher where id_produk in (" . $_whereIn . ") and deleted_at is null";
        $list_voucher_products = DB::select(DB::raw($sql_list_product));
        $lp = array();
        if (!empty($list_voucher_products)) {
            foreach ($list_voucher_products as $lvp) {
                $lp[$lvp->id_voucher][] = $lvp->id_product;
            }
        }
        $_sql = '';
        if ($tipe_member == 1) {
            $_sql = "select id_voucher from vouchers
            where member=1 and new_konsumen = 0 and deleted_at is null and vouchers.is_publish = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";
        }
        $list_trans = array();
        if ($tipe_member == 2) {
            $sql_cnt_transaksi = "select id_transaksi from transaksi where id_member= $id_member and status != 2";
            $list_trans = DB::select(DB::raw($sql_cnt_transaksi));
            if (empty($list_member_voucher)) {
                $_sql = "select id_voucher from vouchers where (new_konsumen=1 or konsumen=1) and deleted_at is null and vouchers.is_publish = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";
            } else {
                $_sql = "select id_voucher from vouchers where konsumen=1 and new_konsumen = 0 and deleted_at is null and vouchers.is_publish = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";
            }
        }

        $voucher_tipe_member = array();
        if (!empty($_sql)) $voucher_tipe_member = DB::select(DB::raw($_sql));
        $sql = "select * from vouchers
            where vouchers.deleted_at is null and vouchers.is_publish = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";

        $whereIn = '';
        $whereNotIn = '';
        $id_ta = array();
        $voucher_used = array();
        if (!empty($list_member_voucher)) {
            foreach ($list_member_voucher as $lmv) {
                $id_ta[] = (int)$lmv->id_voucher;
                $is_used = (int)$lmv->is_used;
                if ($is_used > 0) {
                    array_push($voucher_used, $lmv->id_voucher);
                } else {
                    $whereIn = implode(',', $id_ta);
                }
            }
        }
        if (!empty($voucher_tipe_member)) {
            foreach ($voucher_tipe_member as $vtm) {
                $id_ta[] = (int)$vtm->id_voucher;
                $whereIn = implode(',', $id_ta);
            }
        }
        //if(!empty($whereNotIn)) $sql .= " and vouchers.id_voucher not in (".$whereNotIn.")";
        if (!empty($whereIn)) $sql .= " and (vouchers.deleted_at is null and vouchers.id_voucher in (" . $whereIn . "))";
        if (!empty($keyword)) $sql .= " and upper(kode_voucher) = '$keyword'";
        Log::info($sql);
        $list_vouchers = DB::select(DB::raw($sql));
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if (!empty($list_vouchers)) {
            $data = [];
            $data_produk = DB::table('product')->select('product.product_name', 'id_product', 'berat')->get();
            $_dp = array();
            $_brt = array();
            if (!empty($data_produk)) {
                foreach ($data_produk as $dp) {
                    $_dp[$dp->id_product] = $dp->product_name;
                    $_brt[$dp->id_product] = $dp->berat;
                }
            }
            foreach ($list_vouchers as $pa) {
                $path_img = null;
                $sisa = 0;
                $is_limit = (int)$pa->is_limited;
                $user_tertentu = (int)$pa->user_tertentu;
                $cek_user_tertentu = 1;
                if ($user_tertentu > 0) {
                    $where = array('id_member' => $id_member, 'deleted_at' => null, 'id_voucher' => $pa->id_voucher);
                    $member_voucher = DB::table('list_member_voucher')->select('id_member')->where($where)->first();
                    $cek_user_tertentu = isset($member_voucher) && $member_voucher->id_member > 0 ? 1 : 0;
                }
                $sisa = $is_limit > 0 ? (int)$pa->sisa : 20000;
                if (($pa->deleted_at == null || empty($pa->deleted_at)) && (int)$pa->is_publish == 1 && (int)$cek_user_tertentu == 1) {
                    if (!in_array($pa->id_voucher, $voucher_used) && (int)$sisa > 0) {
                        $path_img = !empty($pa->img) ? env('APP_URL') . '/api_cni/uploads/vouchers/' . $pa->img : null;
                        $is_available = 0;
                        $is_available_platform = 0;
                        $is_available_min_pembelian = 0;
                        $is_available_produk_utama = 0;
                        $is_available_produk_tertentu = 0;
                        if ($pa->tipe == 1 || $pa->tipe == 2) {
                            $is_available_produk_utama = 1;
                            $is_available_min_pembelian = $total_belanjaan >= $pa->min_pembelian ? 1 : 0;
                        }
                        $_lp = [];
                        if ($pa->produk_tertentu > 0) {
                            $_lp = isset($lp[$pa->id_voucher]) ? $lp[$pa->id_voucher] : [];
                            $is_available_produk_tertentu = !empty($_lp) ? 1 : 0;

                        }
                        $produk_utama_name = '';
                        $produk_bonus_name = '';
                        $berat = 0;
                        if ($pa->tipe == 3) {
                            $is_available_produk_tertentu = 1;
                            $is_available_min_pembelian = 1;
                            $produk_utama = (int)$pa->produk_utama;
                            $produk_utama_name = $_dp[$pa->produk_utama];
                            $produk_bonus_name = $_dp[$pa->produk_bonus];
                            $berat = $_brt[$pa->produk_bonus];
                            if (in_array($produk_utama, $id_product_item)) {
                                $is_available_produk_utama = 1;
                            }
                        }


                        if ($platform == 1) $is_available_platform = (int)$pa->mobile == 1 ? 1 : 0;
                        if ($platform == 2) $is_available_platform = (int)$pa->website == 1 ? 1 : 0;
                        $is_available = (int)$is_available_min_pembelian > 0 && (int)$is_available_platform > 0 && (int)$is_available_produk_utama > 0 ? 1 : 0;
                        if ($pa->produk_tertentu > 0) {
                            $is_available = $is_available > 0 && (int)count($_lp) > 0 ? 1 : 0;
                        }
                        unset($pa->created_by);
                        unset($pa->updated_by);
                        unset($pa->deleted_by);
                        unset($pa->created_at);
                        unset($pa->updated_at);
                        unset($pa->deleted_at);
                        unset($pa->img);
                        $pa->is_available = $is_available;
                        $pa->produk_utama_name = $produk_utama_name;
                        $pa->produk_bonus_name = $produk_bonus_name;
                        $pa->berat = (int)$berat;
                        $pa->img = $path_img;
                        $pa->list_id_product = count($_lp) > 0 ? $_lp : '';
                        $data[] = $pa;
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

    function get_list_vouchers(Request $request)
    {
        $result = array();
        $data = array();
        $tgl = date('Y-m-d H:i:s');
        $id_member = $request->has('id_member') ? (int)$request->id_member : 0;
        $total_belanjaan = $request->has('total_belanjaan') ? str_replace(',', '', $request->total_belanjaan) : 0;
        if ($id_member == 0) {
            $result = array(
                'err_code' => '02',
                'err_msg' => 'id_member required',
                'data' => null
            );
            return response($result);
            return false;
        }
        $where = array('id_member' => $id_member, 'deleted_at' => null);
        $list_member_voucher = DB::table('list_member_voucher')->select('id_voucher', 'is_used')->where($where)->get();
        $member = DB::table('members')->where($where)->first();
        $tipe_member = isset($member) ? (int)$member->type : 0;
        $_sql = '';
        if ($tipe_member == 1) {
            $_sql = "select id_voucher from vouchers
            where member=1 and deleted_at is null and vouchers.is_publish = 1 and vouchers.is_show = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";
        }
        if ($tipe_member == 2) {
            $_sql = "select id_voucher from vouchers
            where konsumen=1 and deleted_at is null and vouchers.is_publish = 1 and vouchers.is_show = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";
        }

        $voucher_tipe_member = array();
        if (!empty($_sql)) $voucher_tipe_member = DB::select(DB::raw($_sql));
        $sql = "select * from vouchers
            where vouchers.deleted_at is null and vouchers.is_publish = 1 and vouchers.is_show = 1 and vouchers.start_date::timestamp <= '" . $tgl . "' and vouchers.end_date::timestamp >= '" . $tgl . "'";

        $whereIn = '';
        $whereNotIn = '';
        $id_ta = array();
        $voucher_used = array();
        if (!empty($list_member_voucher)) {
            foreach ($list_member_voucher as $lmv) {
                $id_ta[] = (int)$lmv->id_voucher;
                $is_used = (int)$lmv->is_used;
                if ($is_used > 0) {
                    array_push($voucher_used, $lmv->id_voucher);
                } else {
                    $whereIn = implode(',', $id_ta);
                }
            }
        }
        if (!empty($voucher_tipe_member)) {
            foreach ($voucher_tipe_member as $vtm) {
                $id_ta[] = (int)$vtm->id_voucher;
                $whereIn = implode(',', $id_ta);
            }
        }

        if (!empty($whereIn)) $sql .= " and (vouchers.deleted_at is null and vouchers.id_voucher in (" . $whereIn . "))";

        $list_vouchers = DB::select(DB::raw($sql));
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
            'data' => null
        );
        if (!empty($list_vouchers)) {
            $data_produk = DB::table('product')->select('product.product_name', 'id_product', 'berat')->get();
            $_dp = array();
            $_brt = array();
            if (!empty($data_produk)) {
                foreach ($data_produk as $dp) {
                    $_dp[$dp->id_product] = $dp->product_name;
                    $_brt[$dp->id_product] = $dp->berat;
                }
            }
            foreach ($list_vouchers as $pa) {
                $path_img = null;
                $sisa = 0;
                $is_limit = (int)$pa->is_limited;
                $sisa = $is_limit > 0 ? (int)$pa->sisa : 20000;
                if (($pa->deleted_at == null || empty($pa->deleted_at)) && (int)$pa->is_publish == 1 && (int)$pa->is_show == 1) {
                    if (!in_array($pa->id_voucher, $voucher_used) && (int)$sisa > 0) {
                        $path_img = !empty($pa->img) ? env('APP_URL') . '/api_cni/uploads/vouchers/' . $pa->img : null;
                        $is_available = 1;

                        unset($pa->created_by);
                        unset($pa->updated_by);
                        unset($pa->deleted_by);
                        unset($pa->created_at);
                        unset($pa->updated_at);
                        unset($pa->deleted_at);
                        unset($pa->img);
                        $produk_utama_name = '';
                        $produk_bonus_name = '';
                        $berat = 0;
                        if ($pa->tipe == 3) {
                            $produk_utama_name = $_dp[$pa->produk_utama];
                            $produk_bonus_name = $_dp[$pa->produk_bonus];
                            $berat = $_brt[$pa->produk_bonus];
                        }
                        $pa->produk_utama_name = $produk_utama_name;
                        $pa->produk_bonus_name = $produk_bonus_name;
                        $pa->berat = (int)$berat;
                        $pa->is_available = $is_available;
                        $pa->img = $path_img;
                        $data[] = $pa;
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

    function store(Request $request)
    {
        $result = array();
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('YmdHis');
        $data = array();
        $id = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $title = $request->has('title') ? $request->title : '';
        $kode_voucher = $request->has('title') ? strtoupper($request->kode_voucher) : '';
        $short_description = $request->has('short_description') ? $request->short_description : '';
        $deskripsi = $request->has('deskripsi') ? $request->deskripsi : '';
        $is_limited = $request->has('is_limited') ? (int)$request->is_limited : 0;
        $min_pembelian = $request->has('min_pembelian') ? str_replace(',', '', $request->min_pembelian) : 0;
        $kuota = $request->has('kuota') ? str_replace(',', '', $request->kuota) : 0;
        $satuan_potongan = $request->has('satuan_potongan') ? (int)$request->satuan_potongan : 0;
        $potongan = $request->has('potongan') ? str_replace(',', '', $request->potongan) : '';
        $max_potongan = $request->has('max_potongan') ? str_replace(',', '', $request->max_potongan) : '';
        $start_date = $request->has('start_date') ? date('Y-m-d H:i', strtotime($request->start_date)) : '';
        $end_date = $request->has('end_date') ? date('Y-m-d H:i', strtotime($request->end_date.' 23:59')) : '';
        $tipe = $request->has('tipe') ? (int)$request->tipe : 0;
        $produk_utama = $request->has('produk_utama') ? (int)$request->produk_utama : 0;
        $produk_bonus = $request->has('produk_bonus') ? (int)$request->produk_bonus : 0;
        $user_tertentu = $request->has('user_tertentu') ? (int)$request->user_tertentu : 0;
        $produk_tertentu = $request->has('produk_tertentu') ? (int)$request->produk_tertentu : 0;
        $website = $request->has('website') ? (int)$request->website : 0;
        $mobile = $request->has('mobile') ? (int)$request->mobile : 0;
        $member = $request->has('member') ? (int)$request->member : 0;
        $konsumen = $request->has('konsumen') ? (int)$request->konsumen : 0;
        $new_konsumen = $request->has('new_konsumen') ? (int)$request->new_konsumen : 0;
        $is_show = $request->has('is_show') && (int)$request->is_show > 0 ? 1 : 0;

        $new_konsumen = (int)$member > 0 || (int)$user_tertentu > 0 || (int)$konsumen > 0 ? 0 : $new_konsumen;

        if ($tipe == 1) {
            $satuan_potongan = 1;
            $max_potongan = $potongan;
        }
        if ($tipe == 3) {
            $satuan_potongan = 0;
            $max_potongan = 0;
        }
        if ($is_limited == 0) $kuota = 0;
        $path_img = $request->file("img");
        $data = array(
            'title' => $title,
            'short_description' => $short_description,
            'deskripsi' => $deskripsi,
            'is_limited' => $is_limited,
            'min_pembelian' => $min_pembelian,
            'kuota' => $kuota,
            'cnt_used' => 0,
            'sisa' => $kuota,
            'satuan_potongan' => $satuan_potongan,
            'potongan' => $potongan,
            'max_potongan' => $max_potongan,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'user_tertentu' => $user_tertentu,
            'produk_tertentu' => $produk_tertentu,
            'produk_utama' => $produk_utama,
            'produk_bonus' => $produk_bonus,
            'is_publish' => 0,
            'website' => $website,
            'mobile' => $mobile,
            'member' => $member,
            'konsumen' => $konsumen,
            'kode_voucher' => $kode_voucher,
            'is_show' => $is_show,
            'new_konsumen' => $new_konsumen,
        );
        if (!empty($path_img)) {
            $nama = str_replace(' ', '_', $path_img->getClientOriginalName());
            $nama = strtolower($nama);
            $nama_file = $_tgl . '' . $nama;
            $nama_file = base64_encode($nama_file);
            $fileSize = $path_img->getSize();
            $extension = $path_img->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/vouchers';
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
            $data += array("img" => $imageName);
        }

        if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => (int)$request->id_operator);
            DB::table('vouchers')->where('id_voucher', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => (int)$request->id_operator, 'tipe' => $tipe);
            $id = DB::table('vouchers')->insertGetId($data, "id_voucher");
        }

        if ($id > 0) {
            $data += array('id_voucher' => $id);
            $path_img = null;
            $path_img = !empty($data['img']) ? env('APP_URL') . '/api_cni/uploads/vouchers/' . $data['img'] : null;
            unset($data['img']);
            $data += array('img' => $path_img);
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

    function proses_delete(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => $request->id_operator);
        DB::table('vouchers')->where('id_voucher', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function publish(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $data = array("publish_date" => $tgl, "publish_by" => $request->id_operator, 'is_publish' => 1);
        DB::table('vouchers')->where('id_voucher', $id)->update($data);
        $data += array('id_voucher' => $id);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function detail(Request $request)
    {
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $where = array('vouchers.deleted_at' => null, 'id_voucher' => $id);
        $count = 0;
        $count = DB::table('vouchers')->where($where)->count();
        $data_produk = array();
        $data_member = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => $id
        );
        if ($count > 0) {
            $data = DB::table('vouchers')->where($where)->first();
            $tipe = (int)$data->tipe;
            $produk_tertentu = (int)$data->produk_tertentu;
            $user_tertentu = (int)$data->user_tertentu;
            $produk_utama_name = '';
            $produk_bonus_name = '';
            $sort_order = 'ASC';
            $list_produk_tertentu = null;
            if ($produk_tertentu > 0) {
                $count_lp = 0;
                $where = array('list_produk_voucher.deleted_at' => null, 'id_voucher' => $id);
                $count_lp = DB::table('list_produk_voucher')->select('list_produk_voucher.*')->where($where)->count();
                $sort_column = 'product_name';
                $sort_column = $sort_column . " " . $sort_order;
                if ($count_lp > 0) {
                    $_data = DB::table('list_produk_voucher')->select('product.id_product', 'product_name', 'img', 'short_description')->where($where)
                        ->leftJoin('product', 'product.id_product', '=', 'list_produk_voucher.id_produk')->orderByRaw($sort_column)->get();
                    foreach ($_data as $d) {
                        $path_img = null;
                        $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                        unset($d->img);
                        $d->img = $path_img;
                        $list_produk_tertentu[] = $d;
                    }
                }
            }
            if ($tipe == 3) {
                $produk_utama = (int)$data->produk_utama;
                $produk_bonus = (int)$data->produk_bonus;
                if ($produk_utama > 0) {
                    $where = array();
                    $where = array('product.deleted_at' => null, 'id_product' => $produk_utama);
                    $dt_produk_utama = DB::table('product')->where($where)->first();
                    $produk_utama_name = isset($dt_produk_utama) ? $dt_produk_utama->product_name : '';
                }
                if ($produk_bonus > 0) {
                    $where = array();
                    $where = array('product.deleted_at' => null, 'id_product' => $produk_bonus);
                    $dt_produk_bonus = DB::table('product')->where($where)->first();
                    $produk_bonus_name = isset($dt_produk_bonus) ? $dt_produk_bonus->product_name : '';
                }
            }

            $photo = '';
            $photo = !empty($data->img) ? env('APP_URL') . '/api_cni/uploads/vouchers/' . $data->img : '';
            unset($data->img);
            $data->produk_utama_name = $produk_utama_name;
            $data->produk_bonus_name = $produk_bonus_name;
            $data->img = $photo;
            $data->list_produk_tertentu = $list_produk_tertentu;
            // $data->product = $data_produk;
            // $data->member = $data_member;
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        }

        return response($result);
    }

    function list_produk(Request $request)
    {
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $where = array('list_produk_voucher.deleted_at' => null, 'id_voucher' => $id_voucher);
        $count = DB::table('list_produk_voucher')->select('list_produk_voucher.*')->where($where)->count();
        $sort_column = 'product_name';
        $sort_order = 'ASC';
        $sort_column = $sort_column . " " . $sort_order;
        $_data = array();
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $_data = DB::table('list_produk_voucher')->select('product.id_product', 'product_name', 'img', 'short_description')->where($where)
                ->leftJoin('product', 'product.id_product', '=', 'list_produk_voucher.id_produk')->orderByRaw($sort_column)->get();
            foreach ($_data as $d) {
                $path_img = null;
                $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                unset($d->img);
                $d->img = $path_img;
                $data[] = $d;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $data,
                // 'product'       => $data
            );
        }
        return response($result);
    }

    function list_produk_available(Request $request)
    {
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $where = array('list_produk_voucher.deleted_at' => null, 'id_voucher' => $id_voucher);
        $list_produk = DB::table('list_produk_voucher')->select('id_produk')->where($where)->get();
        $whereIn = array();
        if (!empty($list_produk)) {
            foreach ($list_produk as $lp) {
                $whereIn[] = $lp->id_produk;
            }
        }
        $sort_column = 'product_name';
        $sort_order = 'ASC';
        $sort_column = $sort_column . " " . $sort_order;
        $wheree = array('deleted_at' => null);
        $_data = DB::table('product')->select('id_product', 'product_name')->where($wheree)
            ->whereNotIn('id_product', $whereIn)->orderByRaw($sort_column)->get();
        $cnt = count($_data);
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $cnt,
            'data' => null
        );
        if ((int)$cnt > 0) {
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $cnt,
                'data' => $_data
            );
        }
        return response($result);
    }

    function assign_produk(Request $request)
    {

        $tgl = date('Y-m-d H:i:s');
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $list_prod = $request->id_prod;

        $whereIn = array();

        if (count($list_prod) > 0) {
            for ($i = 0; $i < count($list_prod); $i++) {
                $data[] = array(
                    "id_voucher" => $id_voucher,
                    "created_at" => $tgl,
                    "created_by" => (int)$request->id_operator,
                    "id_produk" => $list_prod[$i]
                );
            }
            DB::table('list_produk_voucher')->insert($data);
        }
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function remove_produk(Request $request)
    {

        $tgl = date('Y-m-d H:i:s');
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $id_prod = $request->id_prod;
        $data = array("deleted_at" => $tgl, "deleted_by" => (int)$request->id_operator);
        DB::table('list_produk_voucher')->where(array("id_voucher" => $id_voucher, "id_produk" => $id_prod, "deleted_at" => null))->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function list_member(Request $request)
    {
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $where = array();
        $where = array('list_member_voucher.deleted_at' => null, 'id_voucher' => $id_voucher);
        $count = DB::table('list_member_voucher')->select('list_member_voucher.*')->where($where)->count();
        $sort_column = 'nama';
        $sort_order = 'ASC';
        $sort_column = $sort_column . " " . $sort_order;
        $_data = array();
        $data_member = array();
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
            $_data = DB::table('list_member_voucher')->select('members.nama', 'members.id_member', 'members.cni_id', 'members.type', 'photo')->where($where)
                ->leftJoin('members', 'members.id_member', '=', 'list_member_voucher.id_member')->orderByRaw($sort_column)->get();
            foreach ($_data as $d) {
                $path_img = null;
                $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/members/' . $d->photo : null;
                unset($d->photo);
                $d->img = $path_img;
                $data_member[] = $d;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $data_member,
                // 'member'      	=> $_data
            );
        }
        return response($result);
    }

    function list_member_available(Request $request)
    {
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $member = (int)$request->member > 0 ? (int)$request->member : 0;
        $konsumen = (int)$request->konsumen > 0 ? (int)$request->konsumen : 0;
        $where = array();
        $where = array('list_member_voucher.deleted_at' => null, 'id_voucher' => $id_voucher);
        $list_member = DB::table('list_member_voucher')->select('id_member')->where($where)->get();
        $whereIn = array();
        if (!empty($list_member)) {
            foreach ($list_member as $lm) {
                $whereIn[] = $lm->id_member;
            }
        }
        $sort_column = 'nama';
        $sort_order = 'ASC';
        $sort_column = $sort_column . " " . $sort_order;
        $wheree = array();
        $data = array();
        $wheree = array('deleted_at' => null, 'status' => 1);
        if ($member > 0 && $konsumen <= 0) $wheree += array('type' => 1);
        if ($member <= 0 && $konsumen > 0) $wheree += array('type' => 2);
        $_data = DB::table('members')->select('id_member', 'nama', 'cni_id', 'type')->where($wheree)
            ->whereNotIn('id_member', $whereIn)->orderByRaw($sort_column)->get();
        $cnt = count($_data);
        $result = array();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $cnt,
            'data' => null
        );
        if ((int)$cnt > 0) {
            foreach ($_data as $d) {
                $nama = '';
                $nama = $d->nama;
                $nama .= (int)$d->type == 1 ? '(' . $d->cni_id . ')' : '';
                unset($d->nama);
                $d->nama = $nama;
                $data[] = $d;
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $cnt,
                'data' => $data
            );
        }
        return response($result);
    }

    function assign_member(Request $request)
    {

        $tgl = date('Y-m-d H:i:s');
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $list_member = $request->id_member;

        $whereIn = array();
        // $_data = DB::table('members')->select('id_member','type')->whereIn('id_member', $list_member)->get();
        if (count($list_member) > 0) {
            for ($i = 0; $i < count($list_member); $i++) {
                $data[] = array(
                    "id_voucher" => $id_voucher,
                    "created_at" => $tgl,
                    "created_by" => (int)$request->id_operator,
                    "id_member" => $list_member[$i]
                );
            }
            DB::table('list_member_voucher')->insert($data);
        }
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function remove_member(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_voucher = (int)$request->id_voucher > 0 ? (int)$request->id_voucher : 0;
        $id_operator = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
        $id_member = $request->id_member;
        $data = array("deleted_at" => $tgl, "deleted_by" => (int)$request->id_operator);
        DB::table('list_member_voucher')->where(array("id_voucher" => $id_voucher, "id_member" => $id_member, "deleted_at" => null))->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }
}
