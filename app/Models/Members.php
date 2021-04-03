<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Members extends Model{
	protected $table = 'members';
	public $primaryKey = 'id_member';
	protected $hidden = ['deleted_at','deleted_by','created_at','updated_by','updated_at'];
}