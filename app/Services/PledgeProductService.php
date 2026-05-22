<?php

namespace App\Services;

use App\Models\RewardConfig;   // 小白
use App\Exceptions\ApiException;
use App\Models\PledgeOrder;
use App\Models\PledgeProduct;
use App\Models\UserWallet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PledgeProductService
{
    public function getProduct($id)
    {
        $user     = auth('api')->user();
        $is_login = false;
        $data     = PledgeProduct::query()->where(['id' => $id, 'status' => 1])->first();
        if ($data) {
            $data['cover']      = getFullPath($data['cover']);
            $data['spread_img'] = getFullPath($data['spread_img']);
            $data['can_buy'] = false;
            if (isset($user->user_id)) {
                $is_login   = true;
                $userWallet = (new UserWalletService())->withdrawalBalance($user->user_id,
                    ['coin_name' => $data['coin_name']]);
                $count = PledgeOrder::query()->where(['user_id' => $user->user_id, 'product_id' => $data['id']])->count();
                if($count < $data['can_buy_num']){
                    $data['can_buy'] = true;
                }
            }
            $data['is_login'] = $is_login;
            $data['coin_num'] = $userWallet->original['data']['usable_balance'] ?? 0;
        } else {
            $data = [];
        }
        return $data;
    }

    // 获取行情
    public function getquotation($id)
    {
        $user     = auth('api')->user();
        $is_login = false;
        $data     = PledgeProduct::query()->where(['id' => $id, 'status' => 1])->first();
        if ($data) {
            // 获取行情
            $mark = 'kkkusdt';
            $symbol_name = 'market:' . $mark . '_newPrice';
            $data2 = Cache::store('redis')->get($symbol_name);
            $data['price'] = $data2["price"];

        } else {
            $data = [];
        }
        return $data;
    }

    public function getProductList()
    {
        $user = auth('api')->user();
        $data = PledgeProduct::query()->where(['status' => 1])->get();
        $data = $data->map(function ($item, $key) use ($user) {
            $item['cover']      = getFullPath($item['cover']);
            $item['spread_img'] = getFullPath($item['spread_img']);
            $item['can_buy'] = false;
            if (isset($user->user_id)) {
                // 判断是否获得额外次数
                $invit_can_num = 0;
                if($item['is_invit_unlock'] == 1){
                    // 获取邀请人数
                    $invit_num = User::query()->where(['pid'=>$user->user_id,'can_pledge'=>'1'])->count();
                    $item['user_invit_num'] = $invit_num;
                    if($item['invit_num'] != 0){
                        $invit_can_num = floor($invit_num / $item['invit_num']);
                    }
                }
                // 判断次数
                $count = PledgeOrder::query()->where(['user_id' => $user->user_id, 'product_id' => $item['id']])->count();
                $item['can_buy_num'] = $item['can_buy_num'] + $invit_can_num;
                if($count < ($item['can_buy_num'])){
                    $item['can_buy'] = true;
                }
            }
            return $item;
        })->toArray();
        return $data;
    }

    public function getOrder($user, $id)
    {
        $data = PledgeOrder::query()->where(['id' => $id, 'user_id' => $user['user_id']])->first();
        if ($data) {
            //$data['status'] = PledgeOrder::$statusMap[$data['status']];
            $data['end_time'] = Carbon::parse($data['created_at'])->addDays($data['cycle']+1)->toDateString().' 00:00:00';
            $data['product_name'] = PledgeProduct::query()->where('id',$data['product_id'])->value('name');
        } else {
            $data = [];
        }
        return $data;
    }

    public function getOrderList($user)
    {
        $limit = request()->input('limit') ?? 15;
        $data  = PledgeOrder::query()->where(['user_id' => $user['user_id']])->orderByDesc('id')->simplePaginate($limit);
        $data  = $data->map(function ($item, $key) {
            //$item['status'] = PledgeOrder::$statusMap[$item['status']];
            $item['end_time'] = Carbon::parse($item['created_at'])->addDays($item['cycle']+1)->toDateString().' 00:00:00';
            $item['product_name'] = PledgeProduct::query()->where('id',$item['product_id'])->value('name');
            return $item;
        })->toArray();
        return $data;
    }

    // 小白
    public function buyProduct($user, $params)
    {
        $params['num'] = PriceCalculate($params['num'], '*', 1, 4);

        $product = PledgeProduct::query()->where(['id' => $params['id'], 'status' => 1])->first();
        if (blank($product)) {
            throw new ApiException('产品错误');
        }

        if ($product->min_amount > $params['num'] || $product->max_amount < $params['num']) {
            throw new ApiException('可买数量超出限制');
        }

        $wallet = UserWallet::query()->where(['user_id' => $user->user_id, 'coin_id' => $product->coin_id])->first();
        if (blank($wallet)) {
            throw new ApiException('钱包类型错误'.$user->user_id.$product->coin_id);
        }

        // 第二币种判断
        $price = 0;
        if($product->two_coin_id != 0){
            $two_wallet = UserWallet::query()->where(['user_id' => $user->user_id, 'coin_id' => $product->two_coin_id])->first();
            if (blank($two_wallet)) {
                throw new ApiException('钱包类型错误2');
            }
            // 第一币种余额判断
            $balance = $wallet->usable_balance;
            $one_proportion = (100 - $product->proportion)/100;
            $two_proportion = $product->proportion/100;
            if ($balance < $params['num'] * $one_proportion) {
                throw new ApiException($wallet->coin_name.'余额不足');
            }

            // 获取行情
            $mark = 'kkkusdt';
            $symbol_name = 'market:' . $mark . '_newPrice';
            $data = Cache::store('redis')->get($symbol_name);
            $price = $data["price"];

            // 第二币种余额判断
            if($two_wallet->usable_balance < ($params['num'] * $two_proportion / $price)){
                throw new ApiException($two_wallet->coin_name.'余额不足');
            }
        }else{
            $balance = $wallet->usable_balance;
            if ($balance < $params['num']) {
                throw new ApiException('余额不足');
            }
        }
        

        // 获取邀请获得的奖励
        $invit_can_num = 0;
        if($product->is_invit_unlock == 1){
            // 获取邀请人数
            $invit_num = User::query()->where(['pid'=>$user->user_id,'can_pledge'=>'1'])->count();
            if($product->invit_num != 0){
                $invit_can_num = floor($invit_num / $product->invit_num);
            }
        }

        $count = PledgeOrder::query()->where(['user_id' => $user->user_id, 'product_id' => $params['id']])->count();
        $can_buy_num =  $product->can_buy_num + $invit_can_num;
        if($count >= $can_buy_num){
            throw new ApiException('超出可购买'.$product->can_buy_num.'次,邀请奖励'.$invit_can_num.'次');
        }

        

        DB::beginTransaction();
        try {
            $reward = PriceCalculate($params['num'], '*', ($product->rate / 100), 4);
            //创建订单
            $order_data = [
                'user_id'    => $user['user_id'],
                'order_no'   => get_order_sn('ZY'),
                'product_id' => $product->id,
                'coin_id'    => $product->coin_id,
                'coin_name'  => $product->coin_name,
                'two_coin_id'  => $product->two_coin_id,
                'two_coin_name'  => $product->two_coin_name,
                'two_num'       => 0,
                'quotation'  => $price,
                'proportion' => $product->proportion,
                'cycle'      => $product->cycle,
                'rate'       => $product->rate,
                'num'        => $params['num'],
                'reward'     => $reward,
                'total'      => $params['num'] + $reward,
                'status'     => 1,
            ];

            if($product->two_coin_id != 0){
                $order_data['two_num'] =  $params['num'] * $two_proportion / $price;
                $order_num =  $params['num'] * $one_proportion;

                $order_data['one_num'] =  $order_num;
                $reward = PriceCalculate($order_num, '*', ($product->rate / 100), 4);

                $order_data['reward'] =  $reward;
                $order_data['total'] =  $order_num + $reward;
            }

            $order = PledgeOrder::query()->create($order_data);

            

            // 获取配置
            $reward = RewardConfig::query()->where(['status'=>'1'])->first();
            if(empty($reward)){
                throw new ApiException('配置异常'); 
            }

            if($product->two_coin_id != 0){
                // 第一币种金额
                $one_proportion = (100 - $product->proportion)/100;
                $one_price = $params['num'] * $one_proportion;
                
                //扣除用户可用资产 冻结
                $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', -$one_price,
                    UserWallet::asset_account, 'buy_pledge_product');
                $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', $one_price,
                    UserWallet::asset_account, 'buy_pledge_product');

                // 第二币种金额
                $two_price = ($params['num'] * $two_proportion / $price);
                // 第二币种扣除
                $user->update_wallet_and_log($two_wallet['coin_id'], 'usable_balance', -$two_price,
                    UserWallet::asset_account, 'buy_pledge_product');
                $user->update_wallet_and_log($two_wallet['coin_id'], 'freeze_balance', $two_price,
                    UserWallet::asset_account, 'buy_pledge_product');
            }else{
                //扣除用户可用资产 冻结
                $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', -$params['num'],
                    UserWallet::asset_account, 'buy_pledge_product');
                $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', $params['num'],
                    UserWallet::asset_account, 'buy_pledge_product');
            }
            // 发放推广奖励佣金
            $this->invit_commission($user,$params['num'],$wallet['coin_name'],$reward);

            
            // 成功质押
            if($user->can_pledge == 2){
                User::query()->where(['user_id'=>$user->user_id])->update(['can_pledge'=>1]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
        return $order;
    }

    // 小白
    // 发放佣金奖励
    public function invit_commission($user,$money,$coin_name,$reward)
    {
        if(empty($user)){
            throw new ApiException('用户异常');
        }
        if(empty($money)){
            throw new ApiException('消费金额错误');
        }
        if($money<=0){
            throw new ApiException('消费金额不能低于0');
        }
        if(empty($coin_name)){
           throw new ApiException('钱包类型错误3'.$coin_name); 
        }

        // 获取行情
        $mark = 'kkkusdt';
        $symbol_name = 'market:' . $mark . '_newPrice';
        $data = Cache::store('redis')->get($symbol_name);
        $price = $data["price"];
        if(!empty($reward)){
            // 获取奖励金额
            $invit_money = $money * ($reward->buy_sup_reward/100);

            // 获取上级用户
            $invit_user = User::query()->where(['user_id'=>$user->pid])->first();
            if($invit_user){
                // 获取钱包
                $invit_wallet = UserWallet::query()->where(['coin_name'=>'MED','user_id'=>$invit_user->user_id])->first();
                if($invit_wallet){
                    $invit_money = sprintf("%.8f",$invit_money / $price);
                    $invit_user->update_wallet_and_log($invit_wallet->coin_id, 'usable_balance', $invit_money,UserWallet::asset_account, 'dividend');
                }
            }

            // 获取上级的上级
            $team_money = $money * ($reward->buy_sup_team_reward/100);
            $team_user = User::query()->where(['user_id'=>$invit_user->pid])->first();
            if($team_user){
                // 获取钱包
                $team_wallet = UserWallet::query()->where(['coin_name'=>'MED','user_id'=>$team_user->user_id])->first();
                if($team_wallet){
                    $team_money = sprintf("%.8f",$team_money / $price);
                    $team_user->update_wallet_and_log($team_wallet->coin_id, 'usable_balance', $team_money,UserWallet::asset_account, 'dividend');
                }
            }
        }
        
    }
}
