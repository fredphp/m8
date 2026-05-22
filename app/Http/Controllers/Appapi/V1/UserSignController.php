<?php

namespace App\Http\Controllers\Appapi\V1;

use App\Models\UserSign;
use App\Models\RewardConfig;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
// 小白 签到
class UserSignController extends ApiController
{
    // 签到列表
    public function sign_list()
    {
        // 获取用户信息
        $user = $this->current_user();

        // 获取签到记录
        $sign_info = UserSign::query()->where(['user_id'=>$user->user_id])->orderBy('sign_time',"desc")->first();

        // 获取今日开始时间
        $bengin_time = strtotime(date('Y-m-d 15:00:00'));
        $zuotime = $bengin_time - '172800';
        $days = $sign_info['days'];
        if($sign_info['sign_time'] < $zuotime){
            $days = 0;
        }

        // 获取签到记录
        $qian_money = UserSign::query()->where(['user_id'=>$user->user_id])->sum("money");

        // 获取配置
        $reward = RewardConfig::query()->where(['status'=>'1'])->first();
        if(empty($reward)){
            return $this->error(0,'签到配置异常');
        }

        $data = array(
            'days' => $days,
            "sign_reward"   => $reward['sign_reward'].'MED',
            "lx_sign_reward"   => $reward['lx_sign_reward'].'MED',
            "qian_money" => $qian_money,
        );
        return $this->successWithData($data);
    }


    // 开始签到
    public function start_signing()
    {
        // 获取用户信息
        $user = $this->current_user();
            
        // 判断当前时间在伦敦属于
        $time = time();
        $bengin_time = strtotime(date('Y-m-d 15:00:00'));
        if($time > $bengin_time){
            $end_time = $bengin_time + '86400';
        }else{
            $end_time = $bengin_time;
            $bengin_time = $end_time - '86400'; 
        }
        // 获取今天是否签到
        $sign = UserSign::query()->where(['user_id'=>$user->user_id])->whereBetween('sign_time',[$bengin_time,$end_time])->orderBy("id","desc")->first();

        if(!empty($sign)){
            return $this->error(0,'您已签到');
        }

        // 获取昨日天是否签到
        $zuotime = $bengin_time - '86400';
        $zuo_sign = UserSign::query()->where(['user_id'=>$user->user_id])->whereBetween('sign_time',[$zuotime,$bengin_time])->orderBy("id","desc")->first();
        // 获取配置
        $reward = RewardConfig::query()->where(['status'=>'1'])->first();
        if(empty($reward)){
            return $this->error(0,'签到配置异常');
        }
        // 获取钱包
        $wallet = UserWallet::query()->where(['coin_name'=>'MED','user_id'=>$user->user_id])->first();
        if(empty($wallet)){
            return $this->error(0,'钱包异常');
        }

        // 开始签到
        $sign_data = array(
            'user_id'   => $user->user_id,
            'sign_time' => time(),
        );
        if(empty($zuo_sign)){
            $sign_data['days'] = 1;
            $zuo_days = 1;
            $sign_data['money'] = $reward->sign_reward;
        }else{
            if($zuo_sign->days +1 == 7){
                $sign_data['days'] = 0;
                $sign_data['money'] = $reward->sign_reward;
            }else{
                $sign_data['days'] = $zuo_sign->days +1;
                $sign_data['money'] = $reward->lx_sign_reward;
            }
            $zuo_days = $zuo_sign->days;
        }

        DB::beginTransaction();
        try {
            UserSign::query()->create($sign_data);

            // 签到发放奖励
            if($zuo_days == 7){
                $user->update_wallet_and_log($wallet->coin_id, 'usable_balance', $reward->lx_sign_reward,UserWallet::asset_account, 'sign_send_stai');
            }else{
                $user->update_wallet_and_log($wallet->coin_id, 'usable_balance', $reward->sign_reward,UserWallet::asset_account, 'sign_send_stai');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->success($e);
        }
        return $this->success('签到成功');
    }
}
