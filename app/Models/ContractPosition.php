<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/7/8
 * Time: 10:55
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContractPosition extends Model
{
    //#合约持仓信息

    protected $primaryKey = 'id';
    protected $table = 'contract_position';
    protected $guarded = [];

    protected $attributes = [
        'margin_mode' => 1,
        'liquidation_price' => 0,
        'hold_position' => 0,
        'avail_position' => 0,
        'freeze_position' => 0,
        'position_margin' => 0,
    ];
    protected $casts = [
        'position_margin' => 'real',
        'avg_price' => 'real',
    ];

    public $appends = ['margin_mode_text'];

    public function getMarginModeTextAttribute()
    {
        $map = [1 => __('全仓')];
        return $map[$this->margin_mode];
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','user_id');
    }

    // 获取仓位信息 TODO::获取仓位信息 要有之前合仓模式 改为一个订单对应一个仓位
    public static function getPosition($params,$lockForUpdate = false)
{
    $where = [
        'user_id' => $params['user_id'],
        'contract_id' => $params['contract_id'],
        'side' => $params['side'],
    ];
    $contract = ContractPair::query()->where('id',$params['contract_id'])->select('contract_coin_id','margin_coin_id','symbol','default_lever','unit_amount')->first();
    $data = [
        'margin_mode' => 1,
        'lever_rate' => $contract['default_lever'] ?? 200,
        'symbol' => $contract['symbol'],
        'contract_coin_id' => $contract['contract_coin_id'],
        'margin_coin_id' => $contract['margin_coin_id'],
        'unit_amount' => $contract['unit_amount'] // 一手几个币
    ];

    if(self::query()->where($where)->exists()){
        if($lockForUpdate){
            return self::query()->where($where)->lockForUpdate()->first();
        }
        return self::query()->where($where)->first();
    }else{
        return self::query()->create(array_merge($where,$data));
    }

//        return self::query()->firstOrCreate($where,$data);
}

    // 创建新仓位 一个开仓订单创建一个仓位
    public static function createPosition($params,$lockForUpdate = false)
    {
        $where = [
            'user_id' => $params['user_id'],
            'contract_id' => $params['contract_id'],
            'side' => $params['side'],
        ];
        $contract = ContractPair::query()->where('id',$params['contract_id'])->select('contract_coin_id','margin_coin_id','symbol','default_lever','unit_amount')->first();
        $data = [
            'margin_mode' => 1,
            'lever_rate' => $contract['default_lever'] ?? 200,
            'symbol' => $contract['symbol'],
            'contract_coin_id' => $contract['contract_coin_id'],
            'margin_coin_id' => $contract['margin_coin_id'],
            'unit_amount' => $contract['unit_amount'], // 一手几个币
            'open_order_id' => $params['open_order_id']
        ];

//        if(self::query()->where($where)->exists()){
//            if($lockForUpdate){
//                return self::query()->where($where)->lockForUpdate()->first();
//            }
//            return self::query()->where($where)->first();
//        }else{
            return self::query()->create(array_merge($where,$data));
//        }

//        return self::query()->firstOrCreate($where,$data);
    }

    // 通过ID获取仓位
    public static function getPositionById($position_id,$lockForUpdate = false)
    {
        if($lockForUpdate){
            return self::query()->where(['id' => $position_id])->lockForUpdate()->first();
        }
        return self::query()->where(['id' => $position_id])->first();
    }

}
