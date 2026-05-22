<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankImport extends Model
{
    protected $table = 'bank_imports';

    public function agree($id)
    {
        // 查询订单是否存在
        $res =  $this->find($id)->toArray();
        // 判断订单状态
        if ($res['status'] !== 2) {
            return false;
        }
        $uid = $res['user_id'];
        $num = $res['zhe_number'];
        // 查询用户是否存在
        $userinfo = DB::table('users')
            ->where('user_id', $uid)
            ->select('username')
            ->first();
        $username = $userinfo->username;
        // 查询用户钱包是否存在
        $userwallet = DB::table('user_wallet')
            ->where([
                ['user_id', '=', $uid],
                ['coin_name', '=', 'USDT']
            ])
            ->first();
        if (!$userwallet) {
            return false;
        }
        // 开启事务
        DB::beginTransaction();
        try {
            $q1 = DB::table('user_wallet')
                ->where([['user_id', '=', $uid], ['coin_name', '=', 'USDT']])
                ->increment('usable_balance', $num);
            $q2 = DB::table('user_wallet_logs')
                ->insert([
                    'user_id' => $uid,
                    'account_type' => 1,
                    'coin_id' => 1,
                    'coin_name' => 'USDT',
                    'rich_type' => 'usable_balance',
                    'amount' => $num,
                    'log_type' => 'recharge'
                ]);
            $q3 = DB::table('user_wallet_recharge')
                ->insert([
                    'user_id' => $uid,
                    'username' => $username,
                    'coin_id' => 1,
                    'coin_name' => 'USDT',
                    'datetime' => time(),
                    'amount' => $num,
                    'status' => 1,
                    'type' => 1,
                    'account_type' => 1,
                    'note' => 'C2C'
                ]);
            $q4 = DB::table('bank_imports')
                ->where('id', $id)
                ->update([
                    'id' => $id,
                    'status' => 3
                ]);
            if ($q1 && $q2 && $q3 && $q4) {
                DB::commit();
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Log::info($e);
            DB::rollBack();
             dd($e);
            throw new ApiException($e->getMessage());
            return false;
        }
        return true;
    }

    public function agree2($id){
        // 查询订单是否存在
        $res =  self::where('id',$id)->first();
        // 判断订单状态
        if ($res->status !== 0) {
            return false;
        }
        $bankCofig = BankConfig::where('id',1)->first();
        if ($res->type == 1){
            $res->bank_name = $bankCofig->bank_name;
            $res->bank_branch = $bankCofig->bank_branch;
            $res->bank_branch_code = $bankCofig->bank_branch_code;
            $res->bank_number = $bankCofig->bank_number;
            $res->bank_username = $bankCofig->bank_username;
        }else{
            $res->qr_url = $bankCofig->qr_url;
        }
        $res->status = 1;
        $res->save();
        return true;
    }
    /**
     * @description: 拒绝充值
     * @param {*}
     * @return {*}
     */
    public function reject($id)
    {
        // 更改手动充值状态
        $q1 = DB::table('bank_imports')
            ->where('id', $id)
            ->update(['status' => 4]);
        if (!$q1) {
            return false;
        }
        return true;
    }
}