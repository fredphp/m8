<?php

namespace App\Http\Controllers\Appapi\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\FollowPlan;
use App\Models\FollowRecord;
use App\Models\InsideTradePair;
use App\Models\TimeOrder;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\TimeContractService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Exception;

//交割合约控制器
class TimeContractController extends ApiController
{

    protected $timeContractService;

    public function __construct(TimeContractService $timeContractService)
    {
        $this->timeContractService = $timeContractService;
    }

    //获取初始Kline数据
    public function getKline(Request $request)
    {
        if ($vr = $this->verifyField($request->all(), [
            'symbol' => 'required',
            'period' => 'required',
            'size' => 'required',
            'zip' => '',
        ])) return $vr;

        $params = $request->all();
        $zip = $request->input('zip', 0);
        if (strpos($params['symbol'], '/') !== false) {
            $symbol = strtolower(str_before($params['symbol'], '/') . str_after($params['symbol'], '/'));
        } else {
            $symbol = $params['symbol'];
        }

        $history_data_key = 'market:' . $symbol . '_kline_book_' . $params['period'];
        $history_cache_data = Cache::store('redis')->get($history_data_key);
        $data['data'] = $history_cache_data;
        $data['ch'] = "market." . $symbol . ".kline." . $params['period'];
        $data['ts'] = Carbon::now()->getPreciseTimestamp(3);
        $data['status'] = 'ok';

        $coins = config('coin.exchange_symbols');
        foreach ($coins as $coin => $class) {
            $coin = strtolower($coin);
            if ($symbol == $coin . 'usdt') {
                $data['data'] = $class::getKlineData($symbol, $params['period'], $params['size']);
                $data['ch'] = "market." . $symbol . ".kline." . $params['period'];
                $data['ts'] = Carbon::now()->getPreciseTimestamp(3);
                $data['status'] = 'ok';
                break;
            }
        }

        if ($zip) {
            $json = json_encode($data['data']);
            $gzstr = gzcompress($json);
            $data['data'] = base64_encode($gzstr);
            return $this->successWithData($data);
        } else {
            return $this->successWithData($data);
        }
    }

    //发起跟单计划
    public function pushFollowPlan(Request $request)
    {
        $user = $this->current_user();
        if ($vr = $this->verifyField($request->all(), [
            'follow_name' => 'required|string',
            'symbol' => 'required|string',
            'is_public' => 'required|integer|in:0,1',
            'side' => 'required|string|in:buy,sell',
            'amount' => 'required|string',
            'cycle' => 'required|integer|in:1,2,5,10',
            'order_time' => 'required|string',
            'profit_ratio' => 'required|numeric|min:55|max:66',
        ])) return $vr;
        $params = $request->only(['follow_name', 'symbol', 'is_public', 'side', 'amount', 'cycle', 'order_time', 'profit_ratio']);
        if ($params['order_time'] <= date('H:i')) {
            return $this->error(4001, "带单时间不正确");
        }
        if ($user['trade_status'] == 0) {
            return $this->error(4001, '交易失败');
        }
        $orderLockKey = 'time_order_lock:' . $user['user_id'];
        if (!$this->setKeyLock($orderLockKey, 1)) { //订单锁
            return $this->error(4001, "请勿频繁操作");
        }
        $order_time = date('Y-m-d') . ' ' . $params['order_time'];
        $pair = InsideTradePair::query()->where('pair_name', $params['symbol'])->where('status', 1)->first();
        if (!$pair) {
            return $this->error(4001, "交易对不存在");
        }
        $userWallet = UserWallet::query()->where(['user_id' => $user['user_id'], 'coin_id' => 1])->first();
        $amount = $params['amount'];
        if (strpos($amount, "%") !== false) {
            $amount = str_replace("%", "", $amount);
            $amount = $userWallet->usable_balance * $amount / 100;
        }
        if ($amount < $pair->min_total){
            return $this->error(4001,"交易额低于最低交易标准");
        }
        if ($userWallet->usable_balance < 1000000) {
            return $this->error(4001, "未获得带单权限");
        }
        $newPlan = [];
//        $newPlan = new FollowPlan();
        $newPlan['user_id'] = $user->user_id;
        $newPlan['pair_id'] = $pair->pair_id;
        $newPlan['pair_name'] = $pair->pair_name;
        $newPlan['follow_name'] = $params['follow_name'];
        $newPlan['follow_code'] = strtoupper(Str::random(8));
        $newPlan['side'] = $params['side'];
        $newPlan['amount'] = $params['amount'];
        $newPlan['cycle'] = $params['cycle'];
        $newPlan['order_time'] = $order_time;
        $newPlan['is_public'] = $params['is_public'];
        $newPlan['profit_ratio'] = $params['profit_ratio'];
        $newPlan['created_at'] = $newPlan['updated_at'] = Carbon::now();
        DB::beginTransaction();
        try {
            $lastId = DB::table('follow_plans')->insertGetId($newPlan);

            $newPlanM = FollowPlan::query()->find($lastId);
            $this->timeContractService->addFollowOrder($user, $newPlanM);
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            FollowPlan::query()->where('id', $lastId)->delete();
            return $this->error(4001, "提交失败");
        }
    }

    //跟单
    public function followOrder(Request $request)
    {
        $user = $this->current_user();
        if ($vr = $this->verifyField($request->all(), [
            'follow_id' => 'required|integer',
            'profit_ratio' => 'required',
        ])) return $vr;
        $params = $request->only(['follow_id', 'profit_ratio']);
        $followPlan = FollowPlan::query()->where('id', $params['follow_id'])->where('status', 0)->first();
        if ($user['trade_status'] == 0) {
            return $this->error(4001, '交易失败');
        }

        if (!$followPlan) {
            return $this->error(4001, "带单计划已失效");
        }
        $pair = InsideTradePair::query()->where('pair_name', $followPlan->pair_name)->where('status', 1)->first();
        if (!$pair) {
            return $this->error(4001, "交易对不存在");
        }
        $userWallet = UserWallet::query()->where(['user_id' => $user['user_id'], 'coin_id' => 1])->first();
        $amount = $followPlan->amount;
        if (strpos($amount, "%") !== false) {
            $amount = str_replace("%", "", $amount);
            $amount = $userWallet->usable_balance * $amount / 100;
        }else{
            if($userWallet->usable_balance < $amount){
                return $this->error(4001,'资产不足');
            }
        }
        if ($amount < $pair->min_total){
            return $this->error(4001,"交易额低于最低交易标准");
        }
        if ($user->user_id == $followPlan->user_id) {
            return $this->error(4001, "不能跟随自己");
        }
        if (FollowRecord::query()->where(['follow_id' => $params['follow_id'], 'user_id' => $user->user_id])->exists()) {
            return $this->error(4001, "您已经跟过单了,请勿重复操作");
        }
        DB::beginTransaction();
        try{
            $res = $this->timeContractService->addFollowOrder($user, $followPlan, $params['profit_ratio']);
            if (!$res) {
                return $this->error(4001, "跟单失败");
            }
            DB::commit();
            return $this->success();
        }catch (\Exception $exception){
            DB::rollBack();
            return $this->error(4001, $exception->getMessage());
        }
    }

    //跟单计划列表
    public function planRecord(Request $request)
    {
        $search = $request->input('follow_code', '');
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 15);
        $lists = FollowPlan::query()->where('status', 0)->orderByDesc('id');
        if (!empty($search)) {
            $lists = $lists->where('follow_code', $search);
        }else{
            $lists = $lists->where('is_public', 1);
        }
        $lists = $lists->latest()->select(['id as follow_id', 'pair_name', 'follow_name', 'follow_code', 'side', 'amount', 'cycle', 'order_time', 'is_public'])->paginate($limit);
        return $this->successWithData($lists);
    }

    //散户下单
    public function placeOrder(Request $request)
    {
        $user = $this->current_user();
        if ($vr = $this->verifyField($request->all(), [
            'symbol' => 'required|string',
            'profit_ratio' => 'required|numeric|min:55|max:66',
            'order_time' => 'required|string',
            'side' => 'required|string|in:buy,sell',
            'cycle' => 'required|integer|in:1,2,5,10',
            'amount' => 'required',
        ])) return $vr;
        $params = $request->only(['symbol', 'profit_ratio', 'order_time', 'side', 'cycle', 'amount']);
        if ($user['trade_status'] == 0) {
            return $this->error(4001, '交易失败');
        }
        if ($params['order_time'] <= date('H:i')) {
            return $this->error(4001, "下单时间不正确");
        }
        $orderLockKey = 'time_order_lock:' . $user['user_id'];
        if (!$this->setKeyLock($orderLockKey, 1)) { //订单锁
            return $this->error(4001, "请勿频繁操作");
        }
        $order_time = date('Y-m-d') . ' ' . $params['order_time'];
        $pair = InsideTradePair::query()->where('pair_name', $params['symbol'])->where('status', 1)->first();
        if (!$pair) {
            return $this->error(4001, "交易对不存在");
        }
        $userWallet = UserWallet::query()->where(['user_id' => $user['user_id'], 'coin_id' => 1])->first();
        if($params['amount'] < $pair->min_total){
            return $this->error(4001,"交易额低于最低交易标准");
        }
        if ($userWallet->usable_balance < $params['amount']) {
            return $this->error(4001, "账户余额不足");
        }
        $newTimeOrder = new TimeOrder();
        $newTimeOrder->follow_id = 0;
        $newTimeOrder->follow_record_id = 0;
        $newTimeOrder->user_id = $user['user_id'];
        $newTimeOrder->pair_id = $pair->pair_id;
        $newTimeOrder->pair_name = $pair->pair_name;
        $newTimeOrder->side = $params['side'];
        $newTimeOrder->amount = $params['amount'];
        $newTimeOrder->order_time = $order_time;
        $newTimeOrder->cycle = $params['cycle'];
        $newTimeOrder->profit_ratio = $params['profit_ratio'];
        $newTimeOrder->settle_time = Carbon::parse($order_time)->addMinutes($params['cycle']);
        DB::beginTransaction();
        try {
            $newTimeOrder->save();
            $user->update_wallet_and_log(1, 'usable_balance', -$params['amount'], UserWallet::asset_account, 'time_place',$pair->pair_name);
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error(4001, '下单失败');
        }
    }

    //我的订单记录
    public function myOrderList(Request $request)
    {
        $user = $this->current_user();
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 15);
        $order_id = $request->input('order_id',0);
        $lists = TimeOrder::query()->where('user_id', $user['user_id'])->orderByDesc('id');
        if (!empty($order_id)) {
            $lists = $lists->where('id', $order_id);
        }
        $lists = $lists->select(['id as order_id', 'pair_name', 'side', 'amount', 'open_price', 'close_price', 'order_time', 'settle_time', 'cycle', 'profit_ratio', 'is_win', 'result', 'status', 'created_at'])
            ->paginate($limit);
        return $this->successWithData($lists);
    }

    //我的跟单计划
    public function myPlanRecord(Request $request)
    {
        $user = $this->current_user();
        $lists = FollowPlan::query()->where('user_id', $user['user_id'])->orderByDesc('id')
            ->select(['id as follow_id', 'pair_name', 'follow_name', 'follow_code', 'side', 'amount', 'cycle', 'order_time', 'is_public','status'])->paginate(20);
        return $this->successWithData($lists);
    }

    //我的跟单历史
    public function myFollowRecord(Request $request)
    {
        $user = $this->current_user();
        $status = $request->input('status', -1);
        $lists = FollowRecord::query()->where('user_id', $user['user_id'])->orderByDesc('id');
        if (floatval($status) != -1) {
            $lists = $lists->where('status', $status);
        }
        $lists = $lists->select(['id as record_id','follow_id', 'pair_name', 'side', 'amount', 'cycle', 'order_time', 'profit_ratio', 'created_at', 'status'])->paginate(20);
        foreach ($lists->items() as &$item) {
            $item->follow_name = FollowPlan::query()->where('id', $item->follow_id)->value('follow_name');
            $item->follow_code = FollowPlan::query()->where('id', $item->follow_id)->value('follow_code');
        }
        return $this->successWithData($lists);
    }

    //取消跟单
    public function cancelFollow(Request $request)
    {
        $record_id = $request->input('record_id', 0);
        $user = $this->current_user();
        $followRecord = FollowRecord::query()->where(['user_id' => $user['user_id'], 'id' => $record_id, 'status' => 0])->first();
        if (!$followRecord) {
            return $this->error(4001, "状态不正确");
        }
        $followPlan = FollowPlan::query()->where(['id' => $followRecord->follow_id])->first();
        //处理取消跟单并退款
        $record_ids = [$record_id];
        if ($followPlan->user_id == $followRecord->user_id) { //带单者取消订单
            $ids = FollowRecord::query()->where(['follow_id' => $followRecord->follow_id])->where('id', '!=', $record_id)->pluck('id')->toArray();
            $record_ids = array_merge($record_ids, $ids);
        }
        DB::beginTransaction();
        $res = [];
        try {
            foreach ($record_ids as $record_id) {
                $r = FollowRecord::query()->where(['id' => $record_id])->first();
                $rUser = User::query()->where(['user_id' => $r->user_id])->first();
                $r->status = 3;
                $res[] = $r->save();
                $res[] = $rUser->update_wallet_and_log(1, 'usable_balance', $r->amount, UserWallet::asset_account, 'time_cancel',$r->pair_name);
            }
            if (in_array(false, $res)) {
                DB::rollBack();
                return $this->error(4001, "取消失败");
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error(4001, "取消失败");
        }
    }

    //删除跟单计划
    public function delFollowPlan(Request $request)
    {
        $user = $this->current_user();
        $follow_id = $request->input('follow_id', 0);
        $followPlan = FollowPlan::query()->where(['user_id' => $user->user_id, 'id' => $follow_id, 'status' => 0])->first();
        if (!$followPlan) {
            return $this->error(4001, '计划状态不正确');
        }
        $followPlan->status = 2; //已删除
        if (!$followPlan->save()) {
            return $this->error(4001, '删除失败');
        }
        return $this->success();
    }

    public function cycleList(Request $request)
    {
        $data = [
            [
                'cycle' => 1,
                'profit_ratio' => strval(round(mt_rand(5500,6000) / 100,2))
            ],
            [
                'cycle' => 2,
                'profit_ratio' => strval(round(mt_rand(5800,6200) / 100,2))
            ],
            [
                'cycle' => 5,
                'profit_ratio' => strval(round(mt_rand(6000,6400) / 100,2))
            ],
            [
                'cycle' => 10,
                'profit_ratio' => strval(round(mt_rand(6200,6600) / 100,2))
            ]
        ];
        return $this->successWithData($data);
    }
}
