<?php

namespace App\Services;

//交割合约
use App\Admin\Controllers\AdminSettingController;
use App\Exceptions\ApiException;
use App\Models\Admin\AdminSetting;
use App\Models\FollowPlan;
use App\Models\FollowRecord;
use App\Models\OptionSceneOrder;
use App\Models\TimeOrder;
use App\Models\User;
use App\Models\UserWallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimeContractService
{
    //增加跟单记录
    public function addFollowOrder($user, $followPlan, $profit_ratio = 0)
    {
        $record = new FollowRecord();
        $record->user_id = $user->user_id;
        $record->follow_id = $followPlan->id;
        $record->pair_id = $followPlan->pair_id;
        $record->pair_name = $followPlan->pair_name;
        $record->side = $followPlan->side;
        $userWallet = UserWallet::query()->where(['user_id' => $user->user_id, 'coin_id' => 1])->first();
        $amount = $followPlan->amount;
        if (strpos($amount, "%") !== false) {
            $amount = str_replace("%", "", $amount);
            $amount = $userWallet->usable_balance * $amount / 100;
        }
        $record->amount = $amount;
        $record->cycle = $followPlan->cycle;
        $record->order_time = $followPlan->order_time;
        $record->profit_ratio = $profit_ratio == 0 ? $followPlan->profit_ratio : $profit_ratio;
        $record->kongyk = $followPlan->kongyk;
        $record->status = 0;
        if (!$record->save()) {
            throw new ApiException('记录保存失败');
        }
        $user->update_wallet_and_log(1, 'usable_balance', -$amount, UserWallet::asset_account, 'time_place', $record->pair_name, '', $record->id, FollowRecord::class);
        return true;
    }

    //同步盈亏设置到下面的订单
    public function syncKyk()
    {
        $lists = FollowPlan::query()->where('status', 0)->get();
        foreach ($lists as $list) {
            FollowRecord::query()->where(['follow_id' => $list->id])->where('kongyk', '!=', $list->kongyk)->update(['kongyk' => $list->kongyk]);
        }
    }

    //执行跟单计划
    public function excFollowPlan()
    {
        $lists = FollowPlan::query()->where('status', 0)->where('order_time', '<=', date('Y-m-d H:i:s'))->get();
        foreach ($lists as $list) {
            //将跟单历史中的记录进行下单
            $records = FollowRecord::query()->where(['follow_id' => $list->id, 'status' => 0])->get();
            $insertData = [];
//            $cacheKey = 'market:' . strtolower(str_before($list->pair_name, '/') . str_after($list->pair_name, '/')) . '_newPrice';
//            $cacheData = Cache::store('redis')->get($cacheKey);
//            $now_price = $cacheData['price'];
            $now_price = $this->getOpenPrice($list->pair_name, $list->order_time);
            foreach ($records as $record) {
                array_push($insertData, [
                    'follow_id' => $list->id,
                    'follow_record_id' => $record->id,
                    'user_id' => $record->user_id,
                    'pair_id' => $record->pair_id,
                    'pair_name' => $record->pair_name,
                    'side' => $record->side,
                    'amount' => $record->amount,
                    'open_price' => $now_price,
                    'order_time' => $record->order_time,
                    'settle_time' => Carbon::parse($record->order_time)->addMinutes($record->cycle),
                    'cycle' => $record->cycle,
                    'profit_ratio' => $record->profit_ratio,
                    'is_win' => 0,
                    'kongyk' => $record->kongyk,
                    'status' => 1,
                    'created_at' => $record->order_time,
                    'updated_at' => $record->order_time,
                ]);
                $record->status = 1;
                $record->save();
            }
            $list->status = 1;
            $list->save();
            DB::table('time_orders')->insert($insertData);
        }
    }

    //执行散户订单
    public function excTimePlan()
    {
        $lists = TimeOrder::query()->where('status', 0)->where('order_time', '<=', date('Y-m-d H:i:s'))->get();
        foreach ($lists as $list) {
//            $cacheKey = 'market:' . strtolower(str_before($list->pair_name, '/') . str_after($list->pair_name, '/')) . '_newPrice';
//            $cacheData = Cache::store('redis')->get($cacheKey);
//            $open_price = $cacheData['price'];
            $open_price = $this->getOpenPrice($list->pair_name, $list->order_time);
            $list->open_price = $open_price;
            $list->status = 1;
            $list->save();
        }
    }

    //结算订单
    public function settleOrder()
    {
        $lists = TimeOrder::query()->where('status', 1)->where('settle_time', '<=', date('Y-m-d H:i:s'))->get();
        foreach ($lists as $list) {
            $user = User::query()->where('user_id', $list->user_id)->first();
            if ($list->kongyk == 0) { //没有设置单控盈亏 走系统概率
                $settle_price = $this->getSettlePrice($list->pair_name, $list->settle_time);
                $winAmount = (100 + $list->profit_ratio) * $list->amount / 100;
                if ($list->side == 'buy') { //用户买涨
                    $is_win = $settle_price > $list->open_price ? 1 : 0;
                } else { //用户买跌
                    $is_win = $settle_price > $list->open_price ? 0 : 1;
                }
            } else { //设了单控
                $cacheKey = 'market:' . strtolower(str_before($list->pair_name, '/') . str_after($list->pair_name, '/')) . '_newPrice';
                $cacheData = Cache::store('redis')->get($cacheKey);
                $now_price = $cacheData['price'];
                $randnum = $this->getPriceWave($list->open_price,$list->cycle);
                $is_win = $list->kongyk == 1 ? 1 : 0;
                $winAmount = (100 + $list->profit_ratio) * $list->amount / 100;
//                if ($list->kongyk == 0) {
//                    //自然概率
//                    $r = mt_rand(0, 9);
//                    $is_win = $r <= 1 ? 1 : 0; //自然盈亏
//                }
                $follow_settle_price = 0;//跟单的结算价格
                if ($list->follow_id != 0) {
                    $fa_follow_plan = FollowPlan::query()->where(['id' => $list->follow_id])->first();
                    if ($fa_follow_plan->user_id != $list->user_id) {
                        $follow_settle_price = TimeOrder::query()->where(['follow_id' => $list->follow_id, 'user_id' => $fa_follow_plan->user_id])->value('close_price');
                        Log::info("充值结算价格:" . $follow_settle_price);
                    }
                }
                //计算结算价格
                if ($list->side == 'buy') { //买涨
                    if ($now_price >= $list->open_price) {
                        $settle_price = $is_win == 1 ? $now_price + floatval($randnum) : $list->open_price - floatval($randnum);
                    } else {
                        $settle_price = $is_win == 1 ? $list->open_price + floatval($randnum) : $now_price;
                    }
                } else { //买跌
                    if ($now_price >= $list->open_price) {
                        $settle_price = $is_win == 1 ? $list->open_price - floatval($randnum) : $now_price;
                    } else {
                        $settle_price = $is_win == 1 ? $now_price : $list->open_price + floatval($randnum);
                    }
                }
                if ($follow_settle_price > 0) {
                    $settle_price = $follow_settle_price;
                }
            }

            $list->close_price = $settle_price;
            $list->is_win = $is_win;
            $list->status = 2;
            $profit_ratio_amount = ($list->profit_ratio / 100) * $list->amount;//盈利金额(不包含本金)
            $list->result = $is_win == 1 ? '+' . $profit_ratio_amount : '-' . $list->amount;
            $list->save();
            if ($is_win == 1) {
                $user->update_wallet_and_log(1, 'usable_balance', $winAmount, UserWallet::asset_account, 'time_order_close', $list->pair_name, $list->id);
            }
            if ($list->follow_record_id != 0) {
                FollowRecord::query()->where(['id' => $list->follow_record_id])->update(['status' => 2]);
            }
        }
    }

    //获取统一的开盘价
    public function getOpenPrice($pair_name, $time)
    {
        $key = 'open_price:' . $pair_name . ':' . $time;
        $openPrice = Cache::store('redis')->get($key, null);
        if (!$openPrice) {
            $cacheKey = 'market:' . strtolower(str_before($pair_name, '/') . str_after($pair_name, '/')) . '_newPrice';
            $cacheData = Cache::store('redis')->get($cacheKey);
            $openPrice = $cacheData['price'];
            Cache::store('redis')->set($key, $openPrice, 1200);
        }
        return $openPrice;
    }

    //获取系统结算价格 此处最全局概率配置
    public function getSettlePrice($pair_name, $time)
    {
        $settleKey = 'settle_price:' . $pair_name . ':' . $time;
        $settlePrice = Cache::store('redis')->get($settleKey, null);
        if (!$settlePrice) {
            $cacheKey = 'market:' . strtolower(str_before($pair_name, '/') . str_after($pair_name, '/')) . '_newPrice';
            $cacheData = Cache::store('redis')->get($cacheKey);
            $settlePrice = $cacheData['price'];
        }
        return $settlePrice;
    }

    //每分钟的第30s 去设置结算价格
    public function setSettlePrice()
    {
        $currentSecond = now()->second;
        $nextMinute = date('Y-m-d H:i:00', strtotime(now()->addMinutes()->toTimeString()));
        // 检查当前秒数是否接近 0 或 30（允许一定误差，例如 ±2 秒）
        if ($currentSecond > 28 && $currentSecond < 32) {
            echo "当前秒数:" . $currentSecond . "下一分钟时间:" . $nextMinute . PHP_EOL;
            //没有设置去概率设置价格
            $time_order_scale = Cache::store('redis')->get('time_order_scale_arr', '[]');
            $time_order_scale_arr = json_decode($time_order_scale, true);
            if (count($time_order_scale_arr) == 0) { //没有概率了 进行初始化
                $this->initScale();
                //再取一次
                $time_order_scale = Cache::store('redis')->get('time_order_scale_arr', '[]');
                $time_order_scale_arr = json_decode($time_order_scale, true);
            }
            $result = array_shift($time_order_scale_arr);//取盈亏 1-盈利 2-亏损
            //取完盈亏再存进去
            Cache::store('redis')->forever('time_order_scale_arr', json_encode($time_order_scale_arr));
            //查出所有下一分钟待结算的订单
            $orders = TimeOrder::query()->where(['settle_time' => $nextMinute, 'status' => 1])->get();
            if ($orders->count() == 0) {
                return;
            }
            foreach ($orders as $order) {
                $randnum = $this->getPriceWave($order->open_price,$order->cycle);
                //先看结算价格设定了没有
                $settleKey = 'settle_price:' . $order->pair_name . ':' . $order->settle_time;
                $settlePrice = Cache::store('redis')->get($settleKey, null);
                if ($settlePrice) {
                    continue; //已经设置了跳过
                }
                //没有设置看系统是赢利还是亏损
                $totalBuy = $orders->where('pair_name', $order->pair_name)->where('side', 'buy')->sum('amount') ?? 0;//买涨总金额
                $totalSell = $orders->where('pair_name', $order->pair_name)->where('side', 'sell')->sum('amount') ?? 0;//买跌总金额
                if ($totalSell >= $totalBuy) { //买跌金额大
                    $settlePrice = $result == 1 ? $order->open_price + floatval($randnum) : $order->open_price - floatval($randnum);
//                    if ($result == 1){ //系统盈利
//                        $settlePrice = $order->open_price + floatval($randnum);
//                    }else{ //系统亏损
//                        $settlePrice = $order->open_price - floatval($randnum);
//                    }
                } else {//买涨的金额大
                    $settlePrice = $result == 1 ? $order->open_price - floatval($randnum) : $order->open_price + floatval($randnum);
//                    if ($result == 1){ // 系统盈利
//                        $settlePrice = $order->open_price - floatval($randnum);
//                    }else{
//                        $settlePrice = $order->open_price + floatval($randnum);
//                    }
                }
                Cache::store('redis')->set($settleKey, $settlePrice, 1200);
            }
        }
    }

    //初始化概率
    public function initScale()
    {
        $switch = AdminSetting::query()->where('key', 'time_order_switch')->value('value');
        $scale = AdminSetting::query()->where('key', 'time_order_scale')->value('value');
        if ($switch != 1) {
            $scale = 0.8; //默认系统2次亏损 8次盈利
        }
        $scaleCount = $scale * 100;
        $array = array_fill(0, $scaleCount, 1); //盈利是1
        $array2 = array_fill(0, 100 - $scaleCount, 2);//亏损是2
        $res = array_merge($array, $array2);
        shuffle($res);
        Cache::store('redis')->forever('time_order_scale_arr', json_encode($res));
    }

    //获取价格随机波动范围
    public function getPriceWave($openPirce, $cycle)
    {
        $decimal = $this->getDecimalPlaces($openPirce);
        switch ($cycle) {
            case 1:
                $randScale = round(mt_rand(1, 5) / 10000, 4);
                $randNum = bcmul($openPirce, $randScale, $decimal);
                break;
            case 2:
                $randScale = round(mt_rand(2, 5) / 10000, 4);
                $randNum = bcmul($openPirce, $randScale, $decimal);
                break;
            case 5:
                $randScale = round(mt_rand(3, 5) / 10000, 4);
                $randNum = bcmul($openPirce, $randScale, $decimal);
                break;
            case 10:
                $randScale = round(mt_rand(4, 5) / 10000, 4);
                $randNum = bcmul($openPirce, $randScale, $decimal);
                break;
            default:
                $randScale = round(mt_rand(1, 5) / 10000, 4);
                $randNum = bcmul($openPirce, $randScale, $decimal);
                break;
        }
        return $randNum;
    }


    public function getDecimalPlaces($price)
    {
        // 将价格转换为字符串
        $priceStr = strval($price);
        // 检查是否包含小数点
        if (strpos($priceStr, '.') !== false) {
            // 分割字符串，获取小数部分
            $decimalPart = explode('.', $priceStr)[1];
            // 返回小数部分的长度
            return strlen($decimalPart);
        }
        // 没有小数点，返回 0
        return 0;
    }
}
