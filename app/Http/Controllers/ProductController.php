<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
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

    //

    public function index(Request $request)
    {
        $tgl = Carbon::now();
        $is_cms = (int)$request->is_cms > 0 ? 1 : 0;
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'product_name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $start_price = (int)$request->start_price > 0 ? (int)$request->start_price : 0;
        $end_price = (int)$request->end_price > 0 ? (int)$request->end_price : 0;
        $special_promo = (int)$request->special_promo > 0 ? 1 : 0;
        $favourite = (int)$request->favourite > 0 ? 1 : 0;

        $id_category = (int)$request->id_category > 0 ? (int)$request->id_category : 0;
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
        $type = !empty($data_member) ? (int)$data_member->type : 0;
        $cni_id = !empty($data_member) ? $data_member->cni_id : '';
        $sc = $sort_column;
        if ($sort_column == "harga" || $sort_column == "harga_member" || $sort_column == "harga_konsumen") {
            //$sort_column = $type == 1 ? "harga_member" : "harga_konsumen";
            $sort_column = 'product_name';
        }

        $column_int = array("harga_member", "qty", "berat", "min_pembelian", "harga_konsumen", "priority_number_favourite");
        if (in_array($sort_column, $column_int)) $sort_column = $sort_column . "::integer";
        $sort_column = $sort_column . " " . $sort_order;
        $where = array('product.deleted_at' => null);
        if ($id_category > 0) $where += array('product.id_category' => $id_category);
        if ($special_promo > 0) $where += array('product.special_promo' => 1);
        if ($favourite > 0) $where += array('product.favourite' => 1);
        if ($is_cms <= 0) $where += array('product.is_active' => 1);

        $is_grace_periode = 0;
        $last_month_member = 0;

        if ($is_cms <= 0) {
            $tgll = date('Y-m-d');
            if ($type == 2 && !empty($cni_id)) {
                $end_date = date('Y-m-d', strtotime($data_member->end_member));
                if ($tgll > $end_date) {
                    $date1 = date_create($end_date);
                    $date2 = date_create($tgll);
                    $diff = date_diff($date1, $date2);
                    $is_grace_periode = 16 - (int)$diff->format("%R%a");
                }
            }
            if ($type == 3) {
                $end_date = date('Y-m-d', strtotime($data_member->end_member));
                if ($tgll > $end_date) {
                    $date1 = date_create($end_date);
                    $date2 = date_create($tgll);
                    $diff = date_diff($date1, $date2);
                    $is_grace_periode = 16 - (int)$diff->format("%R%a");
                }
            }
            if ($type == 1) {
                $end_date = date('Y-m-d', strtotime($data_member->end_member));
                $last_month_member_date = date('Y-m-d', strtotime("-1 months", strtotime($end_date)));
                if ($tgll >= $last_month_member_date && $tgll <= $end_date) $last_month_member = 1;
                if ($tgll > $end_date) {
                    $date1 = date_create($end_date);
                    $date2 = date_create($tgll);
                    $diff = date_diff($date1, $date2);
                    $is_grace_periode = 16 - (int)$diff->format("%R%a");
                    $dataa = array("type" => 2, "updated_at" => date('Y-m-d H:i:s'));
                    DB::table('members')->where('id_member', $id_member)->update($dataa);
                    //$data = Members::where($where)->first();
                }
            }
        }

        $is_wishlist = 0;
        $count = 0;
        $_data = array();
        $wishlist = array();
        $data = array();
        $whereIn = array();

        $sql = '';
        $query = '';
        $_pricelist = array();
        if ($end_price > 0 || $start_price > 0) {
            if ($type == 2) $query .= " and pricelist.harga_konsumen::numeric >= '" . $start_price . "' and pricelist.harga_konsumen::numeric <= '" . $end_price . "'";
            if ($type == 1 || $type == 3) $query .= " and pricelist.harga_member::numeric >= '" . $start_price . "' and pricelist.harga_member::numeric <= '" . $end_price . "'";
        }
        if ((int)$last_month_member == 0 && (int)$is_grace_periode <= 0) {
            $query .= " and ABS(product.id_product) > 1";
        }

        if ($is_cms == 0) {
            $sql = "select product.id_product, id_pricelist, pricelist.harga_member::numeric, pricelist.harga_konsumen::numeric,pv,rv from product left join pricelist on pricelist.id_product = product.id_product
            where product.deleted_at is null and pricelist.deleted_at is null and
           ((product.start_date::timestamp <= '" . $tgl . "' and product.end_date::timestamp >= '" . $tgl . "') or
           (product.start_date::timestamp >= '" . $tgl . "' and product.end_date::timestamp <= '" . $tgl . "')) and
           ((pricelist.start_date::timestamp <= '" . $tgl . "' and pricelist.end_date::timestamp >= '" . $tgl . "') or
           (pricelist.start_date::timestamp >= '" . $tgl . "' and pricelist.end_date::timestamp <= '" . $tgl . "'))";
            $sql .= $query;
        } else {
            $sql_pricelist = '';
            $sql_pricelist = "select id_pricelist,id_product, harga_konsumen,harga_member,pv,rv from pricelist
            where deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";


            $pricelist_active = DB::select(DB::raw($sql_pricelist));
            if (!empty($pricelist_active)) {
                foreach ($pricelist_active as $pa) {
                    $whereIn[] = $pa->id_product;
                    $_pricelist['id_pricelist'][$pa->id_product] = $pa->id_pricelist;
                    //$_pricelist['id_product'][$pa->id_product] = $pa->id_product;
                    $_pricelist['harga_member'][$pa->id_product] = $pa->harga_member;
                    $_pricelist['harga_konsumen'][$pa->id_product] = $pa->harga_konsumen;
                    $_pricelist['pv'][$pa->id_product] = $pa->pv;
                    $_pricelist['rv'][$pa->id_product] = $pa->rv;
                }
            }
            $sql = "select product.id_product from product where product.deleted_at is null";
        }
        $product_active = DB::select(DB::raw($sql));
        // Log::info(serialize($whereIn));
        //$_pricelist = array();
        if (!empty($product_active)) {
            foreach ($product_active as $pa) {
                $whereIn[] = $pa->id_product;
                if ($is_cms == 0) {
                    $_pricelist['id_pricelist'][$pa->id_product] = $pa->id_pricelist;
                    //$_pricelist['id_product'][$pa->id_product] = $pa->id_product;
                    $_pricelist['harga_member'][$pa->id_product] = $pa->harga_member;
                    $_pricelist['harga_konsumen'][$pa->id_product] = $pa->harga_konsumen;
                    $_pricelist['pv'][$pa->id_product] = $pa->pv;
                    $_pricelist['rv'][$pa->id_product] = $pa->rv;
                }
            }
        }

        if (!empty($whereIn)) {
            if (!empty($keyword)) {
                $_data = DB::table('product')->select('product.*', 'category_name')
                    ->whereIn('id_product', $whereIn);
                if ($is_cms <= 0) $_data = $_data->where('product.qty', '>', 0)->whereNotIn('id_product', [64, 65, 66]);
                $_data = $_data->leftJoin('category', 'category.id_category', '=', 'product.id_category')
                    ->where($where)->whereRaw("(LOWER(product.product_name) like '%" . $keyword . "%' or LOWER(product.kode_produk) like '%" . $keyword . "%')")->get();
                $count = count($_data);
            } else {
                $count = DB::table('product')->where($where);
                if ($is_cms <= 0) $_data = $count->where('product.qty', '>', 0)->whereNotIn('id_product', [64, 65, 66]);
                $count = $count->whereIn('id_product', $whereIn)->count();
                $per_page = $per_page > 0 ? $per_page : $count;
                $offset = ($page_number - 1) * $per_page;
                $_data = DB::table('product')->select('product.*', 'category_name')
                    ->whereIn('id_product', $whereIn);
                if ($is_cms <= 0) $_data = $_data->where('product.qty', '>', 0)->whereNotIn('id_product', [64, 65, 66]);
                $_data = $_data->leftJoin('category', 'category.id_category', '=', 'product.id_category')
                    ->where($where)->offset($offset)->limit($per_page)->orderByRaw($sort_column)->get();
            }
        }

        $result = array();
        $_limit = array();
        $_jml_beli = array();
        $data_cart = [];
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => 0,
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

            if ($id_member > 0) {
                $cnt_cart = DB::table('cart')->where('id_member', $id_member)->whereIn('id_product', $whereIn)->count();
                if ((int)$cnt_cart > 0) {
                    $dt_cart = DB::table('cart')->select('id_product', 'qty')->where('id_member', $id_member)->whereIn('id_product', $whereIn)->get();
                    if (!empty($dt_cart)) {
                        foreach ($dt_cart as $dc) {
                            $data_cart[$dc->id_product] = (int)$dc->qty;
                        }
                    }
                }
            }

            $where = array('id_member' => $id_member);
            $cnt_wishlist = DB::table('wishlist')->where($where)->count();
            if ((int)$cnt_wishlist > 0) {
                $chk_wishlist = DB::table('wishlist')->select('id_product')->where($where)->get();
                foreach ($chk_wishlist as $cw) {
                    array_push($wishlist, $cw->id_product);
                }
            }

            foreach ($_data as $d) {
                $is_wishlist = 0;
                if (in_array($d->id_product, $wishlist)) $is_wishlist = 1;
                $path_img = null;
                if (empty($d->deleted_at) || $d->deleted_at == '') {
                    $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                    unset($d->created_by);
                    unset($d->updated_by);
                    unset($d->deleted_by);
                    unset($d->created_at);
                    unset($d->updated_at);
                    unset($d->deleted_at);
                    unset($d->img);
                    $limit_beli = isset($_limit['id_lp'][$d->id_product]) ? (int)$_limit['limit_beli'][$d->id_product] : 9999999999;
                    $jml_beli = isset($_jml_beli[$d->id_product]) ? (int)$_jml_beli[$d->id_product] : 0;
                    $d->id_pricelist = isset($_pricelist['id_pricelist'][$d->id_product]) ? $_pricelist['id_pricelist'][$d->id_product] : 0;
                    $d->harga_member = isset($_pricelist['harga_member'][$d->id_product]) ? $_pricelist['harga_member'][$d->id_product] : 0;
                    $d->harga_konsumen = isset($_pricelist['harga_konsumen'][$d->id_product]) ? $_pricelist['harga_konsumen'][$d->id_product] : 0;
                    $d->pv = isset($_pricelist['pv'][$d->id_product]) ? $_pricelist['pv'][$d->id_product] : 0;
                    $d->rv = isset($_pricelist['rv'][$d->id_product]) ? $_pricelist['rv'][$d->id_product] : 0;
                    $d->harga = $type == 1 || $type == 3 ? $d->harga_member : $d->harga_konsumen;
                    $d->is_wishlist = $is_wishlist;
                    $d->img = $path_img;
                    $d->terjual = rand(1, 1000);
                    $d->jml_limit_beli = $limit_beli;
                    $d->jml_beli = $jml_beli;
                    $d->qty_cart = isset($data_cart[$d->id_product]) ? (int)$data_cart[$d->id_product] : 0;
                    $d->is_limit_beli = (int)$jml_beli >= (int)$limit_beli ? 1 : 0;
                    if ($is_cms > 0) $data[] = $d;
                    if ((int)$d->harga > 0 && $is_cms <= 0) $data[] = $d;
                }
            }
            if ($sc == "harga" || $sc == "harga_member" || $sc == "harga_konsumen") {
                $columns = array_column($data, $sc);
                if ($sort_order == 'ASC') array_multisort($columns, SORT_ASC, $data);
                if ($sort_order == 'DESC') array_multisort($columns, SORT_DESC, $data);
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

    function getProduct(Request $request)
    {
        $sort_column = 'product_name';
        $sort_order = 'ASC';
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $count = 0;
        $_data = array();
        $where = array('product.deleted_at' => null);
        if (!empty($keyword)) {
            $_data = DB::table('product')->select('product.id_product as value', 'product_name as label')
                ->where($where)->where('id_product', '>', 1)->whereRaw("LOWER(product.product_name) like '%" . $keyword . "%'")->get();
            $count = count($_data);
        } else {
            $count = DB::table('product')->where($where)->count();
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = DB::table('product')->select('product.id_product as value', 'product_name as label')
                ->where($where)->where('id_product', '>', 1)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ((int)$count > 0) {

            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'data' => $_data
            );
        }
        return response($result);
    }

    function store(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $_tgl = date('YmdHi');
        $data = array();
        $id = (int)$request->id_product > 0 ? (int)$request->id_product : 0;

        $favourite = (int)$request->favourite > 0 ? (int)$request->favourite : 0;
        $priority_number_favourite = (int)$request->priority_number_favourite > 0 && (int)$favourite > 0 ? $request->priority_number_favourite : 0;
        $path_img = $request->file("img");
        $kode_produk = $request->kode_produk;
        if (!$this->isValidProductCode($id, $kode_produk)) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'Product Code already exist',
                'data' => null
            );
            return response($result);
            return false;
        }
        if ($favourite > 0) {
            if (!$this->isValidPriority($id, $priority_number_favourite)) {
                $result = array(
                    'err_code' => '07',
                    'err_msg' => 'Number already exist',
                    'data' => null
                );
                return response($result);
                return false;
            }
        }
        $data = array(
            'id_category' => (int)$request->id_category > 0 ? $request->id_category : '',
            'product_name' => $request->product_name,
            'kode_produk' => $kode_produk,
            // 'harga'         => str_replace(',', '', $request->harga),
            'deskripsi' => $request->deskripsi,
            'short_description' => $request->short_description,
            'berat' => str_replace(',', '', $request->berat),
            'kondisi' => !empty($request->kondisi) ? $request->kondisi : null,
            'min_pembelian' => str_replace(',', '', $request->min_pembelian),
            'video_url' => !empty($request->video_url) ? $request->video_url : null,
            'qty' => (int)$request->qty > 0 ? str_replace(',', '', $request->qty) : 0,
            // 'diskon_member' => !empty($request->diskon_member) ? $request->diskon_member : 0,
            'special_promo' => (int)$request->special_promo,
            'favourite' => $favourite,
            'priority_number_favourite' => (int)$priority_number_favourite,
            'start_date' => !empty($request->start_date) ? date('Y-m-d H:i', strtotime($request->start_date)) : '',
            'end_date' => !empty($request->end_date) ? date('Y-m-d H:i', strtotime($request->end_date)) : '',
        );
        if (!empty($path_img)) {
            $nama = str_replace(' ', '', $request->product_name);
            if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
            $nama = strtolower($nama);
            $nama_file = $_tgl . '' . $nama;
            $nama_file = Crypt::encryptString($nama_file);
            $fileSize = $path_img->getSize();
            $extension = $path_img->getClientOriginalExtension();
            $imageName = $nama_file . '.' . $extension;
            $tujuan_upload = 'uploads/products';
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
            $data += array("updated_at" => $tgl, "updated_by" => $request->id_operator);
            DB::table('product')->where('id_product', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => $request->id_operator);
            $id = DB::table('product')->insertGetId($data, "id_product");
        }
        $result = array();
        if ($id > 0) {
            $data += array('id_product' => $id);
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
        $id = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => $request->id_operator);
        DB::table('product')->where('id_product', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function detail(Request $request)
    {
        $data = array();
        $sql_pricelist = '';
        $_pricelist = array();
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? Helper::last_login((int)$request->id_member) : 0;
        $id = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $id_member_share = (int)$request->id_member_share > 0 ? (int)$request->id_member_share : 0;
        $where = array('product.deleted_at' => null, 'id_product' => $id);

        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'data' => $id_member
        );
        $count = 0;
        $count = DB::table('product')->where($where)->count();
        if ($count > 0) {
            $is_wishlist = DB::table('wishlist')->select('id_product')->where(array('id_member' => $id_member, 'id_product' => $id))->count();
            $data_member = DB::table('members')->where(array('id_member' => $id_member))->first();
            $type = !empty($data_member) ? (int)$data_member->type : 0;
            $data = DB::table('product')->select('product.*', 'category_name')
                ->leftJoin('category', 'category.id_category', '=', 'product.id_category')
                ->where($where)->first();
            $sql_pricelist = "select id_pricelist,id_product, harga_konsumen,harga_member,pv,rv from pricelist
                where id_product=$id and deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";
            $pricelist_active = DB::select(DB::raw($sql_pricelist));
            if (!empty($pricelist_active)) {
                foreach ($pricelist_active as $pa) {
                    $_pricelist['id_pricelist'][$pa->id_product] = $pa->id_pricelist;
                    //$_pricelist['id_product'][$pa->id_product] = $pa->id_product;
                    $_pricelist['harga_member'][$pa->id_product] = $pa->harga_member;
                    $_pricelist['harga_konsumen'][$pa->id_product] = $pa->harga_konsumen;
                    $_pricelist['pv'][$pa->id_product] = $pa->pv;
                    $_pricelist['rv'][$pa->id_product] = $pa->rv;
                }
            }
            $where = array();
            $where = array('deleted_at' => null, 'id_product' => $id);
            $data_img = DB::table('product_img')->where($where)->get();
            $list_img = null;
            if (!empty($data_img)) {
                foreach ($data_img as $d) {
                    $path_img = null;
                    $path_img = !empty($d->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $d->img : null;
                    $list_img[] = array(
                        'id' => $d->id,
                        'img_product' => $path_img
                    );
                }
            }
            $where = array();
            $where = array('status_ulasan' => 2, 'id_product' => $id);
            $cnt_ulasan = DB::table('transaksi_detail')->where($where)->count();
            $data_ulasan = '';
            if ((int)$cnt_ulasan > 0) {
                $sort_column = 'tgl_ulasan';
                $sort_order = 'DESC';
                $data_ulasan = DB::table('transaksi_detail')->select('rating', 'ulasan', 'img_ulasan', 'tgl_ulasan', 'members.nama as nama_member')
                    ->leftJoin('transaksi', 'transaksi.id_transaksi', '=', 'transaksi_detail.id_trans')
                    ->leftJoin('members', 'members.id_member', '=', 'transaksi.id_member')
                    ->where($where)->orderBy($sort_column, $sort_order)->get();
            }
            $sql_limit = "select id_lp,id_product, limit_pembelian, start_date::timestamp, end_date::timestamp from limit_pembelian
            where deleted_at is null and id_product = $id and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";
            $limit_product = DB::select(DB::raw($sql_limit));
            $jml_beli = 0;
            $limit_beli = 0;
            if ($id_member > 0 && (int)count($limit_product) > 0) {
                $start_date = $limit_product[0]->start_date;
                $end_date = $limit_product[0]->end_date;
                $sql_beli = "select sum(jml) as jml_beli from transaksi left join transaksi_detail on transaksi_detail.id_trans = transaksi.id_transaksi where id_product = $id and id_member=$id_member and ((created_at::timestamp >= '" . $start_date . "' or created_at::timestamp <= '" . $end_date . "'))";

                $beli_product = DB::select(DB::raw($sql_beli));
                if ((int)count($beli_product) > 0) {
                    $jml_beli = (int)$beli_product[0]->jml_beli;
                }
            }

            $qty_cart = 0;
            if ((int)$id_member > 0) {
                $where = array();
                $where = array('cart.id_member' => $id_member, 'id_product' => $id);
                $dt_cart = DB::table('cart')->select('id_product', 'qty')->where($where)->first();
                $qty_cart = isset($dt_cart) ? (int)$dt_cart->qty : 0;
            }

            if ((int)count($limit_product) > 0) {
                $limit_beli = (int)$limit_product[0]->limit_pembelian;
            }

            $photo = '';
            $photo = !empty($data->img) ? env('APP_URL') . '/api_cni/uploads/products/' . $data->img : '';
            unset($data->img);
            unset($data->created_by);
            unset($data->updated_by);
            unset($data->deleted_by);
            unset($data->created_at);
            unset($data->updated_at);
            unset($data->deleted_at);

            $data->id_pricelist = isset($_pricelist['id_pricelist'][$data->id_product]) ? $_pricelist['id_pricelist'][$data->id_product] : 0;
            $data->harga_member = isset($_pricelist['harga_member'][$data->id_product]) ? $_pricelist['harga_member'][$data->id_product] : 0;
            $data->harga_konsumen = isset($_pricelist['harga_konsumen'][$data->id_product]) ? $_pricelist['harga_konsumen'][$data->id_product] : 0;
            $data->pv = isset($_pricelist['pv'][$data->id_product]) ? $_pricelist['pv'][$data->id_product] : 0;
            $data->rv = isset($_pricelist['rv'][$data->id_product]) ? $_pricelist['rv'][$data->id_product] : 0;
            $data->harga = $type == 1 || $type == 3 ? $data->harga_member : $data->harga_konsumen;
            $data->is_wishlist = (int)$is_wishlist > 0 ? $is_wishlist : 0;

            $data->img = $photo;
            $data->jml_limit_beli = (int)$limit_beli > 0 ? $limit_beli : 9999999999;
            $data->jml_beli = $jml_beli;
            $data->is_limit_beli = (int)$jml_beli >= (int)$data->jml_limit_beli ? 1 : 0;
            $data->terjual = rand(1, 1000);
            $data->qty_cart = $qty_cart;
            $data->list_img = $list_img;
            $data->list_ulasan = $data_ulasan;
            if ($id_member_share > 0) {
                Helper::share_product((int)$id_member_share, $id_product, 1);
            }
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        }
        return response($result);
    }

    function upload_img(Request $request)
    {
        $data = array();
        $tgl = date('Y-m-d H:i:s');
        $id_product = (int)$request->id_product;
        $_img = $request->file("img");
        $result = array();
        if ($id_product <= 0 && (int)$request->id <= 0) {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'id_product required',
                'data' => null
            );
            return response($result);
            return false;
        }
        // if (empty($img)) {
        //     $result = array(
        //         'err_code'  => '06',
        //         'err_msg'   => 'img required',
        //         'data'      => null
        //     );
        //     return response($result);
        //     return false;
        // }
        if ($request->hasFile('img')) {
            $tujuan_upload = 'uploads/products';
            foreach ($_img as $img) {
                $_tgl = date('YmdHi');
                $nama = str_replace(' ', '', $img->getClientOriginalName());
                if (strlen($nama) > 32) $nama = substr($nama, 0, 32);
                $nama = strtolower($nama);
                $nama_file = $_tgl . '' . $nama;
                $nama_file = Crypt::encryptString($nama_file);
                $fileSize = $img->getSize();
                $extension = $img->getClientOriginalExtension();
                $imageName = $nama_file . '.' . $extension;
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
                $img->move($tujuan_upload, $imageName);
                $data[] = array("img" => $imageName, "id_product" => $id_product, "created_at" => $tgl, "created_by" => $request->id_operator);
            }
            DB::table('product_img')->insert($data);
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'data' => $data
            );
        } else {
            $result = array(
                'err_code' => '06',
                'err_msg' => 'img required',
                'data' => null
            );
        }
        return response($result);
    }

    function proses_delete_list_img(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id > 0 ? (int)$request->id : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => $request->id_operator);
        DB::table('product_img')->where('id', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    public function get_img(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = array('deleted_at' => null, 'id_product' => (int)$request->id_product);
        $count = 0;
        $_data = array();
        $data = array();
        $count = DB::table('product_img')->where($where)->count();
        $per_page = $per_page > 0 ? $per_page : $count;
        $offset = ($page_number - 1) * $per_page;
        $_data = DB::table('product_img')->select('id', 'id_product', 'img')
            ->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'data' => null
        );
        if ($count > 0) {
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
                'data' => $data
            );
        }
        return response($result);
    }

    function store_pricelist(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_pricelist > 0 ? (int)$request->id_pricelist : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $start_date = !empty($request->start_date) ? date('Y-m-d H:i', strtotime($request->start_date)) : '';
        $end_date = !empty($request->end_date) ? date('Y-m-d H:i', strtotime($request->end_date)) : '';
        Log::info($request->all());
        $result = array();
        $sql = '';
        $sql = "select id_pricelist, start_date, end_date from pricelist where id_product=$id_product and deleted_at is null and (
            (start_date::timestamp >= '" . $start_date . "' and start_date::timestamp <= '" . $end_date . "') or
            (end_date::timestamp >= '" . $start_date . "' and end_date::timestamp <= '" . $end_date . "') or
            (start_date::timestamp <= '" . $start_date . "' and end_date::timestamp >= '" . $end_date . "')
        )";
        if ($id > 0) {
            $sql .= " and id_pricelist != " . $id;
        }
        $pricelist_active = DB::select(DB::raw($sql));
        $date_duplicate = array();
        DB::connection()->enableQueryLog();
        if (!empty($pricelist_active)) {
            foreach ($pricelist_active as $pa) {
                $sd = date('d/m/Y H:i', strtotime($pa->start_date)) . ' - ' . date('d/m/Y H:i', strtotime($pa->end_date));
                array_push($date_duplicate, $sd);
            }
            $totalDuplicate = count($date_duplicate);
            if ($totalDuplicate > 1) {
                $date_duplicate = implode(', ', array_slice($date_duplicate, 0, $totalDuplicate - 1)) . ' dan ' . end($date_duplicate);
            } else {
                $date_duplicate = implode(', ', $date_duplicate);
            }
            $result = array(
                'err_code' => '05',
                'err_msg' => 'Tanggal bentrok',
                'data' => $date_duplicate
            );
            return response($result);
            return false;
        }
        $data = array(
            'id_product' => (int)$id_product,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'pv' => $request->has('pv') ? str_replace(',', '', $request->pv) : 0,
            'rv' => $request->has('rv') ? str_replace(',', '', $request->rv) : 0,
            'harga_member' => $request->has('harga_member') ? str_replace(',', '', $request->harga_member) : 0,
            'harga_konsumen' => $request->has('harga_konsumen') ? str_replace(',', '', $request->harga_konsumen) : 0,
            'hm_non_ppn' => $request->has('hm_non_ppn') ? str_replace(',', '', $request->hm_non_ppn) : 0,
            'hk_non_ppn' => $request->has('hk_non_ppn') ? str_replace(',', '', $request->hk_non_ppn) : 0,
            'ppn_hm' => $request->has('ppn_hm') ? str_replace(',', '', $request->ppn_hm) : 0,
            'ppn_hk' => $request->has('ppn_hk') ? str_replace(',', '', $request->ppn_hk) : 0,
            'status' => 1
        );
        if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => $request->id_operator);
            DB::table('pricelist')->where('id_pricelist', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => $request->id_operator);
            $id = DB::table('pricelist')->insertGetId($data, "id_pricelist");
        }
        //Log::info(DB::getQueryLog());
        if ($id > 0) {
            $data += array('id_pricelist' => $id);
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

    function del_pricelist(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_pricelist > 0 ? (int)$request->id_pricelist : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => $request->id_operator);
        DB::table('pricelist')->where('id_pricelist', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    public function get_pricelist(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_pricelist';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = array('deleted_at' => null, 'id_product' => (int)$request->id_product);
        $count = 0;
        $_data = array();
        $data = array();
        $count = DB::table('pricelist')->where($where)->count();
        $per_page = $per_page > 0 ? $per_page : $count;
        $offset = ($page_number - 1) * $per_page;
        $_data = DB::table('pricelist')
            ->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        $data = DB::table('product')->select('product_name')->where($where)->first();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'id_product' => $request->id_product,
            'product_name' => $data->product_name,
            'data' => null
        );
        if ($count > 0) {
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'id_product' => $request->id_product,
                'product_name' => $data->product_name,
                'data' => $_data
            );
        }
        return response($result);
    }

    function upd_active(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $status = (int)$request->status > 0 ? (int)$request->status : 0;
        $data = array("is_active" => $status, "is_active_by" => $request->id_operator, 'is_active_date' => $tgl);
        DB::table('product')->where('id_product', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function upd_sold_out(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $is_sold_out = (int)$request->is_sold_out > 0 ? (int)$request->is_sold_out : 0;
        $data = array("is_sold_out" => $is_sold_out, "updated_by" => $request->id_operator, 'updated_at' => $tgl);
        DB::table('product')->where('id_product', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    function store_limit(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_lp > 0 ? (int)$request->id_lp : 0;
        $id_product = (int)$request->id_product > 0 ? (int)$request->id_product : 0;
        $start_date = !empty($request->start_date) ? date('Y-m-d H:i', strtotime($request->start_date)) : '';
        $end_date = !empty($request->end_date) ? date('Y-m-d H:i', strtotime($request->end_date)) : '';
        $limit_pembelian = $request->has('limit_pembelian') ? str_replace(',', '', $request->limit_pembelian) : 0;
        $result = array();
        $sql = '';
        $sql = "select id_lp, start_date, end_date from limit_pembelian where id_product=$id_product and deleted_at is null and (
            (start_date::timestamp >= '" . $start_date . "' and start_date::timestamp <= '" . $end_date . "') or
            (end_date::timestamp >= '" . $start_date . "' and end_date::timestamp <= '" . $end_date . "') or
            (start_date::timestamp <= '" . $start_date . "' and end_date::timestamp >= '" . $end_date . "')
        )";
        if ($id > 0) {
            $sql .= " and id_lp != " . $id;
        }
        $pricelist_active = DB::select(DB::raw($sql));
        $date_duplicate = array();
        DB::connection()->enableQueryLog();
        if (!empty($pricelist_active)) {
            foreach ($pricelist_active as $pa) {
                $sd = date('d/m/Y H:i', strtotime($pa->start_date)) . ' - ' . date('d/m/Y H:i', strtotime($pa->end_date));
                array_push($date_duplicate, $sd);
            }
            $totalDuplicate = count($date_duplicate);
            if ($totalDuplicate > 1) {
                $date_duplicate = implode(', ', array_slice($date_duplicate, 0, $totalDuplicate - 1)) . ' dan ' . end($date_duplicate);
            } else {
                $date_duplicate = implode(', ', $date_duplicate);
            }
            $result = array(
                'err_code' => '05',
                'err_msg' => 'Tanggal bentrok',
                'data' => $date_duplicate
            );
            return response($result);
            return false;
        }
        $data = array(
            'id_product' => (int)$id_product,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'limit_pembelian' => (int)$limit_pembelian
        );
        if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => $request->id_operator);
            DB::table('limit_pembelian')->where('id_lp', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => $request->id_operator);
            $id = DB::table('limit_pembelian')->insertGetId($data, "id_lp");
        }
        Log::info(DB::getQueryLog());
        if ($id > 0) {
            $data += array('id_lp' => $id);
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

    function del_lp(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id = (int)$request->id_lp > 0 ? (int)$request->id_lp : 0;
        $data = array("deleted_at" => $tgl, "deleted_by" => $request->id_operator);
        DB::table('limit_pembelian')->where('id_lp', $id)->update($data);
        $result = array();
        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => null
        );
        return response($result);
    }

    public function get_lp(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'id_lp';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'DESC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = array('deleted_at' => null, 'id_product' => (int)$request->id_product);
        $count = 0;
        $_data = array();
        $data = array();
        $count = DB::table('limit_pembelian')->where($where)->count();
        $per_page = $per_page > 0 ? $per_page : $count;
        $offset = ($page_number - 1) * $per_page;
        $_data = DB::table('limit_pembelian')
            ->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        $data = DB::table('product')->select('product_name')->where($where)->first();
        $result = array(
            'err_code' => '04',
            'err_msg' => 'data not found',
            'total_data' => $count,
            'id_product' => $request->id_product,
            'product_name' => $data->product_name,
            'data' => null
        );
        if ($count > 0) {
            $result = array(
                'err_code' => '00',
                'err_msg' => 'ok',
                'total_data' => $count,
                'id_product' => $request->id_product,
                'product_name' => $data->product_name,
                'data' => $_data
            );
        }
        return response($result);
    }

    function cek_limit(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_member = (int)$request->id_member > 0 ? $request->id_member : 0;
        $list_item = json_decode($request->list_item);
        $whereIn = [];

        try {
            for ($i = 0; $i < count($list_item); $i++) {
                $whereIn[] = $list_item[$i]->id_product;
                $_whereIn = implode(', ', $whereIn);
            }
            if ($id_member <= 0) {
                $result = array(
                    'err_code' => '02',
                    'err_msg' => 'id_member required',
                    'data' => null
                );
                return response($result);
                return false;
            }
            if (count($whereIn) <= 0) {
                $result = array(
                    'err_code' => '02',
                    'err_msg' => 'list_item required',
                    'data' => null
                );
                return response($result);
                return false;
            }
            $dt_limit_beli = [];
            $dt_min_beli = [];
            $sudah_beli = [];
            $data = [];
            $sql = "select id_product, limit_pembelian, start_date, end_date from limit_pembelian
            where id_product in (" . $_whereIn . ") and deleted_at is null and to_char(start_date, 'YYYY-MM-DD HH24:MI') <= '" . $tgl . "' and to_char(end_date, 'YYYY-MM-DD HH24:MI') >= '" . $tgl . "'";
            Log::info($sql);
            $limit_pembelian = DB::select(DB::raw($sql));
            if (!empty($limit_pembelian)) {
                foreach ($limit_pembelian as $lp) {
                    $dt_limit_beli[$lp->id_product] = (int)$lp->limit_pembelian;
                    $from = date('Y-m-d H:i', strtotime($lp->start_date));
                    $to = date('Y-m-d H:i', strtotime($lp->end_date));
                    $sql_jml_beli = "select sum(jml) as jml_beli from transaksi_detail left join transaksi on transaksi_detail.id_trans = transaksi.id_transaksi where id_member =$id_member
                                 and id_product=$lp->id_product and transaksi.status in(0,1,2,3,4,5) and to_char(transaksi.created_at, 'YYYY-MM-DD HH24:MI') >= '" . $from . "' and to_char(transaksi.created_at, 'YYYY-MM-DD HH24:MI') <= '" . $to . "'";
                    Log::info($sql_jml_beli);
                    $cek_jml_beli = DB::select(DB::raw($sql_jml_beli));
                    $sudah_beli[$lp->id_product] = isset($cek_jml_beli) && (int)$cek_jml_beli[0]->jml_beli > 0 ? (int)$cek_jml_beli[0]->jml_beli : 0;
                }
            }

            $sql_min = "select id_product, min_pembelian from product where id_product in (" . $_whereIn . ") and deleted_at is null";
            $min_beli = DB::select(DB::raw($sql_min));
            if (!empty($min_beli)) {
                foreach ($min_beli as $lp) {
                    $dt_min_beli[$lp->id_product] = (int)$lp->min_pembelian;
                }
            }
            for ($i = 0; $i < count($list_item); $i++) {
                $limit_pembelian = isset($dt_limit_beli[$list_item[$i]->id_product]) ? (int)$dt_limit_beli[$list_item[$i]->id_product] : 999999;
                $sudahBeli = isset($sudah_beli[$list_item[$i]->id_product]) ? (int)$sudah_beli[$list_item[$i]->id_product] : 0;
                $sisa_beli = $limit_pembelian - $sudahBeli;
                $min_pembelian = isset($dt_min_beli[$list_item[$i]->id_product]) ? (int)$dt_min_beli[$list_item[$i]->id_product] : 0;
                $data[] = array(
                    'id_product' => $list_item[$i]->id_product,
                    'jml' => $list_item[$i]->jml,
                    'limit_pembelian' => $limit_pembelian,
                    'min_pembelian' => $min_pembelian,
                    'sudah_beli' => $sudahBeli,
                    'is_available' => (int)$list_item[$i]->jml >= $min_pembelian ? 1 : 0
                    /* 'is_available' => (int)$list_item[$i]->jml <= $sisa_beli && (int)$list_item[$i]->jml >= $min_pembelian ? 1 : 0*/
                );
            }
        } catch (Exception $e) {
            Log::info($e);

        }

        $result = array(
            'err_code' => '00',
            'err_msg' => 'ok',
            'data' => $data
        );
        return response($result);
    }

    function test_get_hrg()
    {
        $tgl = date('Y-m-d H:i:s');
        $sql_pricelist = '';
        $sql_pricelist = "select id_pricelist,id_product, harga_konsumen,harga_member from pricelist
        where deleted_at is null and ((start_date::timestamp <= '" . $tgl . "' and end_date::timestamp >= '" . $tgl . "') or (start_date::timestamp >= '" . $tgl . "' and end_date::timestamp <= '" . $tgl . "'))";
        $pricelist_active = DB::select(DB::raw($sql_pricelist));
        $_pricelist = array();
        $whereIn = array();
        $id_ta = array();
        if (!empty($pricelist_active)) {
            foreach ($pricelist_active as $pa) {
                $id_ta[] = $pa->id_product;
                $whereIn = implode(', ', $id_ta);
                $_pricelist['id_pricelist'][$pa->id_product] = $pa->id_pricelist;
                $_pricelist['id_product'][$pa->id_product] = $pa->id_product;
                $_pricelist['harga_member'][$pa->id_product] = $pa->harga_member;
                $_pricelist['harga_konsumen'][$pa->id_product] = $pa->harga_konsumen;
            }
        }
        $sql = "select product.id_product, id_pricelist, pricelist.harga_member, pricelist.harga_konsumen from product left join pricelist on pricelist.id_product = product.id_product
             where product.deleted_at is null and pricelist.deleted_at is null and
            ((product.start_date::timestamp <= '" . $tgl . "' and product.end_date::timestamp >= '" . $tgl . "') or
            (product.start_date::timestamp >= '" . $tgl . "' and product.end_date::timestamp <= '" . $tgl . "')) and
            ((pricelist.start_date::timestamp <= '" . $tgl . "' and pricelist.end_date::timestamp >= '" . $tgl . "') or
            (pricelist.start_date::timestamp >= '" . $tgl . "' and pricelist.end_date::timestamp <= '" . $tgl . "'))
            and pricelist.id_product in (" . $whereIn . ")";
        $pa = DB::select(DB::raw($sql));
        $result = array(
            'err_code' => '05',
            'err_msg' => 'Tanggal bentrok',
            'data' => $pa
        );
        return response($result);
    }

    function isValidProductCode($id_product, $kode_produk)
    {
        $where = array();
        $where = array('deleted_at' => null, 'kode_produk' => $kode_produk);
        $res_idproduct = DB::table('product')->select('id_product')->where($where)->first();
        $res_id = !empty($res_idproduct) ? $res_idproduct->id_product : 0;
        if ((int)$res_id && $res_idproduct->id_product != $id_product) {
            return false;
        } else {
            return true;
        }
    }

    function isValidPriority($id_product, $priority_number)
    {
        $where = array();
        $where = array('deleted_at' => null, 'priority_number_favourite' => (int)$priority_number);
        $res_idproduct = DB::table('product')->select('id_product')->where($where)->first();
        $res_id = !empty($res_idproduct) ? $res_idproduct->id_product : 0;
        if ((int)$res_id && $res_idproduct->id_product != $id_product) {
            return false;
        } else {
            return true;
        }
    }
}
