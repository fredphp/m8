<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// 小白
class UserAddress extends Model
{
    protected $table = 'user_address';
    protected $primaryKey = 'id';
    protected $guarded = [];
}
