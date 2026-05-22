<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PledgeOrder extends Model
{
    // 币币交易交易对

    protected $table = 'pledge_order';
    protected $primaryKey = 'id';
    protected $guarded = [];

    //状态
    const status_freeze = 0;
    const status_normal = 1;
    public static $statusMap = [
        self::status_freeze => '结束',
        self::status_normal => '质押中',
    ];

//    public function PledgeProduct()
//    {
//        return $this->belongsTo('Models\PledgeProduct','product_id');
//    }
}
