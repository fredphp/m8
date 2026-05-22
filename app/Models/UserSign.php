<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// 小白
class UserSign extends Model
{
    //

    protected $table = 'user_sign';
    protected $primaryKey = 'id';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','user_id');
    }

}
