<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model{
	protected $table = 'admin';
	public $primaryKey = 'id_admin';
	protected $hidden = ['deleted_at','deleted_by','created_at','created_by','updated_by','updated_at'];
}