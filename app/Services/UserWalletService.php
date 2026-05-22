<?php

namespace App\Services;

use App\Models\RewardConfig;

// 小白
use App\Events\WithdrawEvent;
use App\Handlers\ContractTool;
use App\Models\ContractPair;
use App\Models\ContractPosition;
use App\Models\ListingApplication;
use App\Models\OtcAccount;
use App\Models\OtcCoinlist;
use App\Models\SustainableAccount;
use App\Models\Recharge;
use App\Models\RechargeManualrg;
use App\Exceptions\ApiException;
use App\Models\Coins;
use App\Models\TimeOrder;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserDepositAddress;
use App\Models\UserPaymentMethod;
use App\Models\UserSubscribe;
use App\Models\UserSubscribeRecord;
use App\Models\UserTransferTranslation;
use App\Models\VolLog;
use App\Models\Withdraw;
use App\Models\UserWallet;
use App\Models\TransferRecord;
use App\Models\WithdrawalManagement;
use App\Models\InsideTradePair;
use App\Services\HuobiService\HuobiapiService;
use App\Services\HuobiService\lib\HuobiLibService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SubscribeActivity;
use Carbon\Carbon;


class UserWalletService
{
    public function accountPairList($user, $params)
    {
        $account_type = $params['account'];
        $account_class = array_first(UserWallet::$accountMap, function ($value, $key) use ($account_type) {
            return $value['id'] == $account_type;
        });
        if (blank($account_class)) {
            throw new ApiException('账户类型错误');
        }
        $account = new $account_class['model']();
        return $account->where(['user_id' => $user['user_id']])->get();
    }

    public function coinList($params)
    {
        if ($params['from_account'] == 2 || $params['to_account'] == 2) {
            $data = Coins::query()->where('status', 1)->where('coin_name', 'USDT')->select(['coin_id', 'coin_name', 'coin_icon'])->get();
        } elseif ($params['from_account'] == UserWallet::otc_account || $params['to_account'] == UserWallet::otc_account) {
            $data = OtcCoinlist::query()->where('status', 1)->select(['coin_id', 'coin_name'])->get();
        } else {
            $data = Coins::query()->where('status', 1)->where('coin_name', 'USDT')->select(['coin_id', 'coin_name', 'coin_icon'])->get();
        }
        return $data;
    }

    public function getBalance($user, $params)
    {
        $account_type = $params['account'];
        $account_class = array_first(UserWallet::$accountMap, function ($value, $key) use ($account_type) {
            return $value['id'] == $account_type;
        });
        if (blank($account_class)) {
            throw new ApiException('账户类型错误');
        }
        if ($account_class['is_need_pair'] == 1 && !isset($params['pair_id'])) throw new ApiException('缺少参数');

        $account = new $account_class['model']();
        if ($params['account'] == 2) {
            $wallet = $account->where(['user_id' => $user['user_id'], 'coin_name' => $params['coin_name']])->first();
        } elseif ($params['account'] == UserWallet::otc_account) {
            $wallet = $account->where(['user_id' => $user['user_id'], 'coin_name' => $params['coin_name']])->first();
        } else {
            $wallet = $account->where(['user_id' => $user['user_id'], 'coin_name' => $params['coin_name']])->first();
        }
        return $wallet;
    }

    public function transfer($user, $params)
    {
        DB::beginTransaction();
        try {
            $amount = $params['amount'];

            $from_account = $params['from_account'];
            $to_account = $params['to_account'];

            // TODO 从合约账户转出时 需要先平仓
            if ($from_account == UserWallet::sustainable_account) {
                $positions = ContractPosition::query()->where('user_id', $user['user_id'])->where('hold_position', '>', 0)->get();
                if (!blank($positions)) throw new ApiException('合约资金转出需先平仓');
            }

            $from_account_item = array_first(UserWallet::$accountMap, function ($value, $key) use ($from_account) {
                return $value['id'] == $from_account;
            });
            $to_account_item = array_first(UserWallet::$accountMap, function ($value, $key) use ($to_account) {
                return $value['id'] == $to_account;
            });
            if (($from_account_item['is_need_pair'] == 1 || $to_account_item['is_need_pair'] == 1) && !isset($params['pair_id'])) throw new ApiException('缺少参数');
            $draw_out_direction = $from_account_item['account'];
            $into_direction = $to_account_item['account'];

            $from_wallet = $from_account_item['model']::query()->where(['user_id' => $user['user_id'], 'coin_name' => $params['coin_name']])->first();
            $to_wallet = $to_account_item['model']::query()->where(['user_id' => $user['user_id'], 'coin_name' => $params['coin_name']])->first();
//            $from_wallet = $from_account_item['is_need_pair'] == 0 ?
//                $from_account_item['model']::query()->where(['user_id'=>$user['user_id'],'coin_name'=>$params['coin_name']])->first() :
//                $from_account_item['model']::query()->where(['user_id'=>$user['user_id'],$from_account_item['pair_key']=>$params['pair_id']])->first();
//            $to_wallet = $to_account_item['is_need_pair'] == 0 ?
//                $to_account_item['model']::query()->where(['user_id'=>$user['user_id'],'coin_name'=>$params['coin_name']])->first() :
//                $to_account_item['model']::query()->where(['user_id'=>$user['user_id'],$to_account_item['pair_key']=>$params['pair_id']])->first();

            #可用余额
            $usable_balance = $from_wallet['usable_balance'];
            if ($amount > $usable_balance) {
                return api_response()->error(0, "超出可划转余额,请重新输入");
            }

            TransferRecord::query()->insert([
                'user_id' => $user['user_id'],
                'coin_name' => $params['coin_name'],
                'amount' => $amount,
                'draw_out_direction' => $draw_out_direction,
                'into_direction' => $into_direction,
                'datetime' => time(),
                'status' => 1,
            ]);

            $user->update_wallet_and_log($from_wallet['coin_id'], 'usable_balance', -$amount, $from_account, 'fund_transfer');
            $user->update_wallet_and_log($to_wallet['coin_id'], 'usable_balance', $amount, $to_account, 'fund_transfer');

            DB::commit();
            return api_response()->successString('SUCCESS', true);
        } catch (\Exception $e) {
            DB::rollBack();
            return api_response()->error(0, $e->getMessage());
        }
    }

    public function transferRecords($user, $params)
    {
        $result = TransferRecord::query()->where(['user_id' => $user['user_id']])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    public function createWallet($user)
    {
        $coins = Coins::query()->where('status', 1)->get();
        $wallet_data = [];
        foreach ($coins as $coin) {
            $wallet_data[] = [
                'coin_id' => $coin['coin_id'],
                'coin_name' => $coin['coin_name'],
            ];
        }
        return $user->user_wallet()->createMany($wallet_data);
    }

    public function updateWallet($user)
    {
        $coins = Coins::query()->where(['status' => 1])->get();
        foreach ($coins as $coin) {
            $result = UserWallet::query()->where(['user_id' => $user['user_id'], 'coin_name' => $coin['coin_name']])->first();
            if (blank($result)) {
                UserWallet::query()->create([
                    'user_id' => $user['user_id'],
                    'coin_id' => $coin['coin_id'],
                    'coin_name' => $coin['coin_name'],
                ]);
            }
        }
    }

    #充币
    public function recharge($user_id, $coin_id, $address, $amount)
    {
        if (preg_match('/^[_0-9a-z]{30,50}$/i', $address)) {
            $user = User::query()->where(['user_id' => $user_id])->firstOrFail();
            $coin = Coins::query()->where(['coin_id' => $coin_id])->firstOrFail();

            $result = Recharge::query()->create([
                'user_id' => $user_id,
                'username' => $user['username'],
                'coin_id' => $coin_id,
                'coin_name' => $coin['coin_name'],
                'datetime' => time(),
                'address' => $address,
                'amount' => $amount,
            ]);
            if (!$result) {
                return api_response()->error(100, "提交失败");
            } else {
                return api_response()->successString('SUCCESS', true);
            }
        } else {
            return api_response()->error(100, "请填写正确的钱包地址");
        }
    }

    #自动充币
    public function chargeEth($data)
    {
        $coin = Coins::query()->where(['symbol' => $data['symbol']])->first();
        if ($coin['coin_name'] == "BTC_USDT") {
            $wallet = UserWallet::query()->where(['user_id' => $data['customerNo'], 'omni_wallet_address' => $data['address']])->first();
            $note = 'omni_usdt';
        } else {
            $wallet = UserWallet::query()->where(['user_id' => $data['customerNo'], 'coin_id' => $coin['coin_id'], 'wallet_address' => $data['address']])->first();
            $note = $data['symbol'] == 'eth_usdt' ? 'ERC20_USDT' : $data['symbol'];
        }

        if (blank($wallet) || blank($coin)) {
            return api_response()->error(100, false);
        } else {
            $amount = $data['amount'];
            $txid = $data['txid'];
            $reqTime = $data['reqTime'];
            $address = $data['address'];
            $customerNo = $data['customerNo'];
            $sign = "address=" . $address . "&amount=" . $amount . "&appKey=" . $coin["appKey"] . "&customerNo=" . $customerNo . "&reqTime=" . $reqTime
                . "&symbol=" . $coin["symbol"] . "&txid=" . $txid . "&appSecret=" . $coin['appSecret'];
            if (md5($sign) != $data["sign"]) {
                return api_response()->error(100, "不匹配" . $sign . "--md5--" . md5($sign));
            }

            $res = Recharge::query()->where(['txid' => $txid])->first();
            if ($res) {
                return api_response()->success("SUCCESS", "订单已成功了" . $res['id']);
            }

            DB::beginTransaction();
            try {

                // 更新用户余额
                $user = User::query()->findOrFail($wallet['user_id']);
                $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', $amount, UserWallet::asset_account, 'recharge');

                // 记录日志
                Recharge::query()->create([
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'coin_id' => $wallet['coin_id'],
                    'coin_name' => $wallet['coin_name'],
                    'datetime' => time(),
                    'address' => $address,
                    'txid' => $txid,
                    'amount' => $amount,
                    'status' => Recharge::status_pass,
                    'note' => $note,
                ]);

                DB::commit();
                return api_response()->success("SUCCESS", "成功");
            } catch (\Exception $e) {
                DB::rollBack();
                throw new ApiException($e->getMessage());
            }

        }

    }

    #充值处理
    public function rechargeDispose($user_id, $status, $coin_id)
    {
        if ($status == 1) {
            $money = Recharge::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'status' => 0])->firstOrFail();
            Recharge::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'id' => $money['id']])->update([
                'status' => $status
            ]);
            $user = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->firstOrFail();
            $usable_balance = $user['usable_balance'] + $money['amount'];
            UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->update([
                'usable_balance' => $usable_balance
            ]);
            $user = User::query()->find($user_id);
            $user->update_wallet_and_log($coin_id, 'usable_balance', $money['amount'], UserWallet::asset_account, 'recharge');
            return api_response()->success('SUCCESS', "充值成功");
        } elseif ($status == 2) {
            $first = Recharge::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'status' => 0])->firstOrFail();
            Recharge::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'id' => $first['id']])->update([
                'status' => $status
            ]);
            return api_response()->error(100, "充值失败");
        } else {
            return api_response()->error(100, "等待处理");
        }
    }

    #提币处理
    public function withdrawDispose($user_id, $status, $coin_id)
    {
        if ($status == 1) {
            $money = Withdraw::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'status' => 0])->firstOrFail();
            $coin = Coins::query()->where(['coin_id' => $coin_id])->firstOrFail();
            Withdraw::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'id' => $money['id']])->update([
                'status' => $status,
                'withdrawal_fee' => $coin['withdrawal_fee'],
            ]);
            $user = User::query()->find($user_id);
            $user->update_wallet_and_log($coin_id, 'usable_balance', -$money['amount'], UserWallet::asset_account, 'withdraw');
            return api_response()->success('SUCCESS', "提币成功");


        } elseif ($status == 2) {

            $money = Withdraw::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'status' => 0])->firstOrFail();
            Withdraw::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'id' => $money['id']])->update([
                'status' => $status
            ]);
            $user = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->firstOrFail();
            $coin = Coins::query()->where(['coin_id' => $coin_id])->firstOrFail();
            $usable_balance = $user['usable_balance'] + $money['amount'] + $coin['withdrawal_fee'];
            UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->update([
                'usable_balance' => $usable_balance
            ]);
            return api_response()->error(100, "提币失败,币种已返还");

        } else {
            return api_response()->error(100, "等待处理");
        }
    }

    #充币记录
    public function depositHistory($user_id)
    {
        $result = Recharge::query()->where(['user_id' => $user_id])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    public function cancelWithdraw($user, $params)
    {
        $withdraw = Withdraw::query()->where(['user_id' => $user['user_id'], 'status' => Withdraw::status_wait, 'id' => $params['withdraw_id']])->first();
        if (blank($withdraw)) throw new ApiException('Not Found');

        // 非待审核状态 不可撤销
        if ($withdraw['status'] != Withdraw::status_wait) {
            throw new ApiException('提交失败');
        }

        DB::beginTransaction();
        try {

            $withdraw->update(['status' => Withdraw::status_canceled]);

            // 更新用户资产
            $user->update_wallet_and_log($withdraw['coin_id'], 'usable_balance', $withdraw['total_amount'], UserWallet::asset_account, 'cancel_withdraw');

            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception);
            return false;
        }
    }

    #提币
    public function withdraw($user_id, $coin_id, $address, $amount, $address_note, $address_type)
    {
        $coin = Coins::query()->where(['coin_id' => $coin_id])->firstOrFail();
        if ($coin['is_withdraw'] != 1) return api_response()->error(4001, "该币种暂不支持提币");

        // 验证地址合法性
//        if (!$this->checkAddressLegality($coin_id, $address, $address_type)) return api_response()->error(4001, "请填写正确的钱包地址");

        //验证交易量
        $recharge = \App\Models\Recharge::where(['user_id' => $user_id, 'status' => 1, 'coin_id' => 1])->sum('amount') ?? 0;
        $exchangeAmount = TimeOrder::query()->where(['user_id' => $user_id, 'status' => 2])->sum('amount') ?? 0;
        $exchangeAmount += VolLog::query()->where(['user_id' => $user_id])->sum('number') ?? 0;
        if ($exchangeAmount < $recharge) {
            return api_response()->error(4001, "交易量为满足要求");
        }
        DB::beginTransaction();
        try {
            $user = User::query()->where(['user_id' => $user_id])->firstOrFail();
            $userWallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->firstOrFail();

            $money = $userWallet['usable_balance'];
            //判断真实交易量是否完成
            $actualExcAmount = TimeOrder::query()->where(['user_id' => $user_id, 'status' => 2])->sum('amount') ?? 0;
            if ($actualExcAmount < $recharge){ //真实交易量 不满足充值量
                $fee =  PriceCalculate($amount, '*', 0.1, 5);
            }else{
                $fee =  PriceCalculate($amount, '*', 0.05, 5);
            }
//            if ($coin_id == 1 && $address_type == 3) {
//                $fee = 1;
//            }
            $withdrawal_amount = PriceCalculate($amount, '-', $fee, 5);

            if ($amount < $coin['withdrawal_min'] || $amount > $coin['withdrawal_max']) {
                return api_response()->error(4001, '提币数量不在范围内');
            }
            if ($money < $amount) {
                return api_response()->error(100, "提币可用余额不足");
            }
            if ($coin_id == 1) {
                $cur_price = 1;
            } else {
                $cachePrice = Cache::store('redis')->get('swap:trade_detail_' . $coin['coin_name']) ?? ['price' => 0];
                $cur_price = $cachePrice['price'];
            }
            // 创建记录
            $w = Withdraw::query()->create([
                'user_id' => $user_id,
                'username' => $user['username'],
                'coin_id' => $coin_id,
                'coin_name' => $coin['coin_name'],
                'address' => $address,
                'address_type' => $address_type,
                'total_amount' => $amount,
                'amount' => $withdrawal_amount,
                'withdrawal_fee' => $fee,
                'datetime' => time(),
                'cur_price' => $cur_price
            ]);
            // 更新用户资产
            $user->update_wallet_and_log($coin_id, 'usable_balance', -$amount, UserWallet::asset_account, 'withdraw');

            $address_exist = WithdrawalManagement::query()->where(['user_id' => $user_id, 'address' => $address])->exists();
            if (!$address_exist) {
                WithdrawalManagement::query()->create([
                    'user_id' => $user_id,
                    'address' => $address,
                    'address_note' => $address_note,
                    'coin_name' => $coin['coin_name'],
                    'datetime' => time(),
                ]);
            }

            //用户提币事件
//            event(new WithdrawEvent($w));

            DB::commit();

            return api_response()->success("SUCCESS");
        } catch (\Exception $e) {
            DB::rollBack();
            return api_response()->error(100, "error");
        }
    }

    #手工提现
    public function draws($user_id, $coin_id, $address, $amount, $address_note, $address_type)
    {
        return api_response()->error(4001, "该币种暂不支持提币");
        $coin = Coins::query()->where(['coin_id' => $coin_id])->firstOrFail();
        if ($coin['is_withdraw'] != 1) return api_response()->error(4001, "该币种暂不支持提币");

        // 验证地址合法性
        if (!$this->checkAddressLegality($coin_id, $address, $address_type)) return api_response()->error(4001, "请填写正确的钱包地址");

        DB::beginTransaction();
        try {
            $user = User::query()->where(['user_id' => $user_id])->firstOrFail();
            $userWallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->firstOrFail();

            $money = $userWallet['usable_balance'];
            $fee = $coin['withdrawal_fee'];
            if ($coin_id == 1 && $address_type == 3) {
                $fee = 1;
            }
            $withdrawal_amount = PriceCalculate($amount, '-', $fee, 5);

            if ($amount < $coin['withdrawal_min'] || $amount > $coin['withdrawal_max']) {
                return api_response()->error(4001, '提币数量不在范围内');
            }
            if ($money < $amount) {
                return api_response()->error(100, "提币可用余额不足");
            }

            // 创建记录
            $w = Withdraw::query()->create([
                'user_id' => $user_id,
                'username' => $user['username'],
                'coin_id' => $coin_id,
                'coin_name' => $coin['coin_name'],
                'address' => $address,
                'address_type' => $address_type,
                'total_amount' => $amount,
                'amount' => $withdrawal_amount,
                'withdrawal_fee' => $fee,
                'datetime' => time(),
            ]);
            // 更新用户资产
            $user->update_wallet_and_log($coin_id, 'usable_balance', -$amount, UserWallet::asset_account, 'withdraw');

            $address_exist = WithdrawalManagement::query()->where(['user_id' => $user_id, 'address' => $address])->exists();
            if (!$address_exist) {
                WithdrawalManagement::query()->create([
                    'user_id' => $user_id,
                    'address' => $address,
                    'address_note' => $address_note,
                    'coin_name' => $coin['coin_name'],
                    'datetime' => time(),
                ]);
            }

            //用户提币事件
//            event(new WithdrawEvent($w));

            DB::commit();

            return api_response()->success("SUCCESS");
        } catch (\Exception $e) {
            DB::rollBack();
            return api_response()->error(100, "error");
        }
    }

    public function checkAddressLegality($coin_id, $address, $address_type)
    {
        if ($coin_id == 1) {
            if ($address_type == 1) {
                return isBTCAddress($address);
            } elseif ($address_type == 2) {
                return isETHAddress($address);
            } else {
                return true;
            }
        } elseif ($coin_id == 2) {
            return isBTCAddress($address);
        } elseif ($coin_id == 3) {
            return isETHAddress($address);
        } else {
            return true;
        }
    }

#提币记录
    public function withdrawalRecord($user_id)
    {
        $result = Withdraw::query()->where(['user_id' => $user_id])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    public function getUserAddress($user_id)
    {
        $userAddress = UserAddress::where(['user_id' => $user_id, 'status' => 1])->first();
        if (!$userAddress) { //给用户新分配地址
            $userAddress = UserAddress::where(['user_id' => 0, 'status' => 0])->first();
            if (!$userAddress) {
                return false; // 地址用完了
            }
            $userAddress->user_id = $user_id;
            $userAddress->status = 1;
            $userAddress->save();
        }
        return $userAddress;
    }

#钱包充值地址展示
    //public function walletImage($coin_id, $user_id, $address_type)
    public function walletImage($coin_id, $user_id, $address_type)
    {
        $wallet = [];
        $userAddress = $this->getUserAddress($user_id);
        if (!$userAddress) {
            return api_response()->success('SUCCESS', $wallet);
        }
//        if ($coin_id == 1 || $coin_id == 3) {
//            $wallet['address'] = $userAddress->eth_address;
//        } else {
//            $wallet['address'] = $userAddress->omni_address;
//        }
        if ($address_type == 2){
            $wallet['address'] = $userAddress->eth_address;
        }else{
            $wallet['address'] = $userAddress->omni_address;
        }
        $wallet['address1'] = 1;


//        $user_wallet1 = Coins::query()->where(['coin_id' => $coin_id])->firstOrFail();
        //return $user_wallet1['is_czlx'];die;
//        if($coin_id == 1){
//            $user_wallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->firstOrFail();
//
//
//           //var_dump($user_wallet1);die;
//
//                if($user_wallet1['is_czlx'] == 0){
//                    if ($address_type == 1) {
//                        $wallet['address'] = $user_wallet['omni_wallet_address'];
//                    }elseif ($address_type == 2) {
//                        $wallet['address'] = $user_wallet['wallet_address'];
//                    }else{
//                        $wallet['address'] = $user_wallet['trx_wallet_address'];
//                    }
//                    $wallet['address1'] = 0;
//                }else{
//                    $adss = explode(",",$user_wallet1['guding_query_link']); //后台设置的固定充值链接
//                    if ($address_type == 1) {
//                        $wallet['address'] = str_replace(",","",$adss[1]);
//
//                    }elseif ($address_type == 2) {
//                        $wallet['address'] = str_replace(",","",$adss[0]);
//                    }else{
//                        $wallet['address'] = str_replace(",","",$adss[2]);
//                    }
//                    $wallet['address1'] = 1;
//                }
//
//        }else{
//             if($user_wallet1['is_czlx'] == 0){
//            #钱包二维码
//            $user_wallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id])->firstOrFail();
//            $wallet['address'] = $user_wallet['wallet_address'];
//                $wallet['address1'] = 0;
//             }else{
//        $wallet['address'] = $user_wallet1['guding_query_link'];
//        $wallet['address1'] = 1;
//             }
//        }

        return api_response()->success('SUCCESS', $wallet);
    }

    #账户钱包资金划转
    public function fundsTransfer($user_id, $coin_name, $coin_id, $amount, $first_account, $last_account)
    {
        DB::beginTransaction();
        try {
            #资金划转期权账户开始#

            switch ($first_account) {
                case "UserWallet":
                    $first = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'coin_name' => $coin_name])->firstOrFail();
                    $draw_out_direction = "UserWallet";
                    break;
                case "ContractAccount":
                    $first = SustainableAccount::query()->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'coin_name' => $coin_name])->firstOrFail();
                    $draw_out_direction = "ContractAccount";
                    break;
                case "LeverageAccount":

                    break;
                case "FinancialAccount":

                    break;
            }
            switch ($last_account) {
                case "UserWallet":
                    $into_direction = "UserWallet";
                    break;
                case "ContractAccount":
                    $into_direction = "ContractAccount";
                    break;
                case "LeverageAccount":

                    break;
                case "FinancialAccount":

                    break;
            }

            #可用余额
            $usable_balance = $first['usable_balance'];
            if ($amount > $usable_balance) {
                return api_response()->error(0, "超出可划转余额,请重新输入");
            }

            $time = time();
            $result3 = TransferRecord::query()->insert([
                'user_id' => $user_id,
                'coin_id' => $coin_id,
                'coin_name' => $coin_name,
                'amount' => $amount,
                'draw_out_direction' => $draw_out_direction,
                'into_direction' => $into_direction,
                'datetime' => $time,
                'status' => 1,
            ]);
            $user = User::query()->find($user_id);
            if ($draw_out_direction == "UserWallet") {
                $user->update_wallet_and_log($first['coin_id'], 'usable_balance', -$amount, UserWallet::asset_account, 'fund_transfer');
                $user->update_wallet_and_log($first['coin_id'], 'usable_balance', $amount, UserWallet::sustainable_account, 'fund_transfer');
            } else {
                $user->update_wallet_and_log($first['coin_id'], 'usable_balance', -$amount, UserWallet::sustainable_account, 'fund_transfer');
                $user->update_wallet_and_log($first['coin_id'], 'usable_balance', $amount, UserWallet::asset_account, 'fund_transfer');
            }

            DB::commit();
            return api_response()->successString('SUCCESS', true);
        } catch (\Exception $e) {
            DB::rollBack();
            return api_response()->error(0, $e->getMessage());
        }
    }

    #钱包划转记录
    public function transferRecord($user_id)
    {
        $result = TransferRecord::query()->where(['user_id' => $user_id])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    #申购记录
    public function subscribeRecords($user_id)
    {
        $self_coin = config('coin.coin_symbol');
        $price = Cache::store('redis')->get('market:' . strtolower($self_coin) . 'usdt' . '_detail')['close'] ?? 0;

        $result = UserSubscribeRecord::query()->where(['user_id' => $user_id])->orderBy("id", 'desc')->paginate();
        foreach ($result->items() as &$item) {
            $subscribe_price = PriceCalculate($item['payment_amount'], '/', $item['subscription_currency_amount'], 5);
            $item['realtime_price'] = $price;
            $item['subscribe_price'] = $subscribe_price;
            if ($price) {
                $item['subscribe_profit'] = PriceCalculate(($price - $subscribe_price), '*', $item['subscription_currency_amount'], 6);
            } else {
                $item['subscribe_profit'] = 0;
            }
        }
        return api_response()->success('SUCCESS', $result);
    }

    #合约账户
    public function sustainableAccount($user_id)
    {
//        global $price;
//        $btc_tickers = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
//        $eth_tickers = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
//        $eos_tickers = Cache::store('redis')->get('market:' . 'eosusdt' . '_detail')['close'];
//        $etc_tickers = Cache::store('redis')->get('market:' . 'etcusdt' . '_detail')['close'];
//        $wallet_data = [];
//        $result = SustainableAccount::query()->where(['user_id' => $user_id])->paginate();
//        foreach ($result as $data) {
//            switch ($data['coin_name']) {
//                case "BTC":
//                    $price = $btc_tickers;
//                    $minDeposite = 0.0001;
//                    $maxWithdraw = 10;
//                    break;
//                case "ETH":
//                    $price = $eth_tickers;
//                    $minDeposite = 0.01;
//                    $maxWithdraw = 1000;
//                    break;
//                case "EOS":
//                    $price = $eos_tickers;
//                    $minDeposite = 1;
//                    $maxWithdraw = 10000;
//                    break;
//                case "ETC":
//                    $price = $etc_tickers;
//                    $minDeposite = 1;
//                    $maxWithdraw = 10000;
//                    break;
//                default;
//                    $price = 1;
//                    $minDeposite = 1;
//                    $maxWithdraw = 10000;
//                    break;
//            }
//            $logo = coins::query()->where(['coin_name' => $data['coin_name']])->firstOrFail();
//            $wallet_data['list'][] = [
//                'usable_balance' => $data['usable_balance'],
//                'freeze_balance' => $data['freeze_balance'],
//                'valuation' => $data['usable_balance'] + $data['freeze_balance'],
//                'coin_name' => $data['coin_name'],
//                'image' => getFullPath($logo['coin_icon']),
//                'full_name' => $logo['full_name'],
//                'usd_estimate' => ($data['usable_balance'] + $data['freeze_balance']) * $price,
//                'qtyDecimals' => $logo['qty_decimals'],
//                'priceDecimals' => $logo['price_decimals'],
//                'minDeposite' => $minDeposite,
//                'maxWithdraw' => $maxWithdraw,
//            ];
//        }
//        return api_response()->success('SUCCESS', $wallet_data);
    }

    // 合约账户资产
    public function contractAccount($user_id)
    {
        $data = SustainableAccount::query()->where('user_id', $user_id)->get();
        return api_response()->success('SUCCESS', $data);
    }

    public function otcAccount($user_id)
    {
        $accounts = OtcAccount::query()->where('user_id', $user_id)->get()->map(function ($item, $key) {
            $item['image'] = getFullPath(Coins::icon($item['coin_name']));
            return $item;
        });
        return api_response()->success('SUCCESS', $accounts);
    }

    #资金账户
    public function fundAccount($user_id, $is_recharge, $is_withdraw)
    {
        $symbol = [];
        $wallet_data = [];
        //$wallets = UserWallet::query()->where(['user_id' => $user_id])->orderByRaw("coin_id = 26 DESC")->orderBy('wallet_id')->get();
        $wallets = UserWallet::query()->where(['user_id' => $user_id])->orderBy('wallet_id')->where('coin_id','!=',1082);
        if ($is_recharge == 1){
            $wallets = $wallets->whereIn('coin_id',[1,1083]);
        }
        if ($is_withdraw == 1){
            $wallets = $wallets->whereIn('coin_id',[1]); //提现只开通USDT
        }
        $wallets = $wallets->get();
        foreach ($wallets as $wallet) {
            $coin_name = $wallet['coin_name'];
            if ($coin_name == "USDT") {
                $price = 1;
            } else {
                $currency = strtolower($coin_name . "usdt");
                $cachePrice = Cache::store('redis')->get('market:' . "$currency" . '_detail');
                $price = $cachePrice ? $cachePrice['close'] : 0;
            }
            $coin = Coins::query()->where(['coin_name' => $coin_name])->first();
            if ($coin_name == "USDT") {
                $symbol[$coin_name][] = ['coin_name' => strtolower("BTC" . "/" . "USDT"), 'coin_id' => $wallet['coin_id']];
            }
            {
                $symbol[$coin_name][] = ['coin_name' => strtolower($wallet['coin_name'] . "/" . "USDT"), 'coin_id' => $wallet['coin_id']];
            }

            $wallet_data['list'][] = [
                'usable_balance' => number_format($wallet['usable_balance'], 8, '.', ''),
                'freeze_balance' => $wallet['freeze_balance'],
                'valuation' => number_format($wallet['usable_balance'] + $wallet['freeze_balance'], 8, '.', ''),
                'coin_name' => $wallet['coin_name'],
                'coin_id' => $wallet['coin_id'],
                'image' => getFullPath($coin['coin_icon']),
                'full_name' => $coin['full_name'],
                'usd_estimate' => custom_number_format(($wallet['usable_balance'] + $wallet['freeze_balance']) * $price, 5),
                'symbol' => $symbol[$coin_name],
                'qtyDecimals' => $coin['qty_decimals'],
                'priceDecimals' => $coin['price_decimals'],
                'is_withdraw' => $coin['is_withdraw'],
                'is_recharge' => $coin['is_recharge'],
                'withdrawal_min' => $coin['withdrawal_min'],
                'withdrawal_max' => $coin['withdrawal_max'],
            ];
        }
        return api_response()->success('SUCCESS', $wallet_data);
    }

    #总资产
    public function personalAssets($user_id)
    {
        $btc_tickers = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
//        约总资产等于多少BTC
//        约总资产等于多少USD

//        合约账户 等于多少BTC  等于多少USD
//        资金账户 等于多少BTC  等于多少USD
//        $total_assets_btc
//        $total_assets_usd
//        global $user_coin_name;
//        $tickers = (new HuobiapiService())->get_market_tickers();
//        $btc_tickers = (new HuobiapiService())->getDetailMerged('btcusdt');
//        $eth_tickers = (new HuobiapiService())->getDetailMerged('ethusdt');
//        $eos_tickers = (new HuobiapiService())->getDetailMerged('eosusdt');
//        $etc_tickers = (new HuobiapiService())->getDetailMerged('etcusdt');
        global $price, $totalUsd, $totalBtc, $fundsUsd, $fundsBtc, $total_assets_btc, $total_assets_usd;

        $totalUsd = 0;
        $totalBtc = 0;
        $fundsUsd = 0;
        $fundsBtc = 0;
        $otcUsd = 0;
        $otcBtc = 0;
        $wallet_data = [];
        $user_wallet = UserWallet::query()->where(['user_id' => $user_id])->get();

        foreach ($user_wallet as $users) {
            if ($users['coin_name'] == "USDT") {
                $price = 1;
            } else {
                $currency = strtolower($users['coin_name'] . "UsdT");
                $cachePrice = Cache::store('redis')->get('market:' . "$currency" . '_detail');
                $price = $cachePrice ? $cachePrice['close'] : 0;
            }

            $fundsUsd += ($users['usable_balance'] + $users['freeze_balance']) * $price;
        }
        $sustaiable_wallet = SustainableAccount::query()->where(['user_id' => $user_id])->get();
        if (!blank($sustaiable_wallet)) {
            foreach ($sustaiable_wallet as $account) {
                if ($account['coin_name'] == "USDT") {
                    $price = 1;
                } else {
                    $currency = strtolower($account['coin_name'] . "UsdT");
                    $price = Cache::store('redis')->get('market:' . "$currency" . '_detail')['close'];
                }

                $positions = ContractPosition::query()->where('user_id', $user_id)->where('hold_position', '>', 0)->get();
                $totalUnrealProfit = 0;
                foreach ($positions as $position) {
                    $contract = ContractPair::query()->find($position['contract_id']);
                    // 获取最新一条成交记录 即实时最新价格
                    $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
                    $unRealProfit = ContractTool::unRealProfit($position, $contract, $realtime_price);
                    $totalUnrealProfit += $unRealProfit;
                }
                $account_equity = custom_number_format($account['usable_balance'] + $account['used_balance'] + $account['freeze_balance'] + $totalUnrealProfit, 4); // 永续账户权益

                $totalUsd += ($account_equity) * $price;
            }
        }

        // 法币账户
        $otc_wallets = OtcAccount::query()->where(['user_id' => $user_id])->get();
        foreach ($otc_wallets as $otc_wallet) {
            if ($otc_wallet['coin_name'] == "USDT") {
                $price = 1;
            } else {
                $price = Cache::store('redis')->get('market:' . strtolower($otc_wallet['coin_name'] . "USDT") . '_detail')['close'];
            }

            $otcUsd += ($otc_wallet['usable_balance'] + $otc_wallet['freeze_balance']) * $price;
        }

        $total_assets_usd = $totalUsd + $fundsUsd + $otcUsd;
        $total_assets_btc = $total_assets_usd / $btc_tickers;
        $assets_btc = $fundsUsd / $btc_tickers;
        $contract_btc = $totalUsd / $btc_tickers;
        $otcBtc = $otcUsd / $btc_tickers;
        $coins = Coins::query()->where(['coin_name' => "BTC"])->first();
        $wallet_data['funds_account_usd'] = $fundsUsd; #资金账户USD
        $wallet_data['funds_account_btc'] = $assets_btc;  #资金账户BTC
        $wallet_data['contract_account_usd'] = $totalUsd; #合约账户USD
        $wallet_data['contract_account_btc'] = $contract_btc;  #合约账户BTC
        $wallet_data['otc_account_usd'] = $otcUsd; #法币账户USD
        $wallet_data['otc_account_btc'] = $otcBtc;  #法币账户BTC
        $wallet_data['total_assets_usd'] = $total_assets_usd;  #总资产USD
        $wallet_data['total_assets_btc'] = $total_assets_btc; #总资产BTC
        $wallet_data['priceDecimals'] = $coins['price_decimals']; #价格精度
        $wallet_data['qtyDecimals'] = $coins['qty_decimals']; #数量精度

        return api_response()->success('SUCCESS', $wallet_data);
    }

    #币集合
    public function tokenList($user_id, $first_account)
    {
        $coins = Coins::query()->where(['status' => 1])->get();
        $resultUw = UserWallet::query()->where(['user_id' => $user_id])->firstOrFail();
        if (!$resultUw) {
            foreach ($coins as $coin) {
                $res = UserWallet::query()->where(['user_id' => $user_id])->firstOrFail();
                $result = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin['coin_name']])->first();
                if ($res || $result) {

                    $first = UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $coin['coin_id']])->first();
                    if (!$first) {
                        UserWallet::query()->where(['user_id' => $user_id])->insert(['user_id' => $user_id,
                            'coin_id' => $coin['coin_id'],
                            'coin_name' => $coin['coin_name'],]);
                        SustainableAccount::query()->insert(['user_id' => $user_id,
                            'coin_id' => $coin['coin_id'],
                            'coin_name' => $coin['coin_name'],]);
                    }

                }
            }
        }
        global $minQty;
        $wallet_data = [];
        switch ($first_account) {
            case "UserWallet":
                #查询当前字段中coin_name中BTC和USDT
                $first = UserWallet::query()->where(['user_id' => $user_id])->whereRaw('coin_name in ("BTC","USDT")')->get();
                foreach ($first as $result) {
                    $logo = Coins::query()->where(['coin_name' => $result['coin_name']])->first();
                    switch ($logo['coin_name']) {
                        case "BTC":
                            $minQty = '0.000001';
                            break;
                        case "ETH":
                            $minQty = '0.0001';
                            break;
                        case "EOS":
                            $minQty = '0.01';
                            break;
                        case "ETC":
                            $minQty = '0.001';
                            break;
                        case "EET":
                            $minQty = '1';
                            break;
                        case "USDT":
                            $minQty = '1';
                            break;
                    }
                    $wallet_data['list'][] = [
                        'coin_id' => $result['coin_id'],
                        'usable_balance' => $result['usable_balance'],
                        'full_name' => $logo['full_name'],
                        'image' => getFullPath($logo['coin_icon']),
                        'coin_name' => $logo['coin_name'],
                        'qtyDecimals' => $logo['qty_decimals'],
                        'priceDecimals' => $logo['price_decimals'],
                        'minQty' => "$minQty",
                    ];
                }

                break;
            case "ContractAccount":
                $first = SustainableAccount::query()->where(['user_id' => $user_id])->whereRaw('coin_name in ("BTC","USDT")')->get();
                foreach ($first as $result) {
                    $logo = Coins::query()->where(['coin_name' => $result['coin_name']])->first();
                    switch ($logo['coin_name']) {
                        case "BTC":
                            $minQty = '0.000001';
                            break;
                        case "ETH":
                            $minQty = '0.0001';
                            break;
                        case "EOS":
                            $minQty = '0.01';
                            break;
                        case "ETC":
                            $minQty = '0.001';
                            break;
                        case "EET":
                            $minQty = '1';
                            break;
                        case "USDT":
                            $minQty = '1';
                            break;
                    }
                    $wallet_data['list'][] = [
                        'coin_id' => $result['coin_id'],
                        'usable_balance' => $result['usable_balance'],
                        'full_name' => $logo['full_name'],
                        'img' => getFullPath($logo['coin_icon']),
                        'coin_name' => $logo['coin_name'],
                        'qtyDecimals' => $logo['qty_decimals'],
                        'priceDecimals' => $logo['price_decimals'],
                        'minQty' => "$minQty",
                    ];
                }
                break;
            case "LeverageAccount":

                break;
            case "FinancialAccount":

                break;
        }
        return api_response()->success('SUCCESS', $wallet_data);
    }

    #币种资产
    public function withdrawalBalance($user_id, $coin_name)
    {
        $wallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();
        $coin = Coins::query()->where(['coin_name' => $coin_name])->firstOrFail();
        $user = User::query()->find($user_id)->toArray();
        $verify_data = array_only($user, ['user_id', 'country_code', 'phone', 'phone_status', 'email', 'email_status', 'google_token', 'google_status']);
        $wallet_data = [
            'usable_balance' => $wallet['usable_balance'],
//            'withdrawal_fee' => $coin['withdrawal_fee'],
            'withdrawal_min' => $coin['withdrawal_min'],
            'withdrawal_max' => $coin['withdrawal_max'],
            'withdraw_switch' => get_setting_value('withdraw_switch', 'common', 0),
        ];
        $recharge = \App\Models\Recharge::where(['user_id' => $user_id, 'status' => 1,'coin_id' => 1])->sum('amount') ?? 0;
        $exchangeAmount = TimeOrder::query()->where(['user_id' => $user_id, 'status' => 2])->sum('amount') ?? 0;
        $exchangeAmount += VolLog::query()->where(['user_id' => $user_id])->sum('number') ?? 0;
        //判断真实交易量是否完成
        $actualExcAmount = TimeOrder::query()->where(['user_id' => $user_id, 'status' => 2])->sum('amount') ?? 0;
        if ($actualExcAmount < $recharge){
            $wallet_data['withdrawal_fee'] = '10%';
        }else{
            $wallet_data['withdrawal_fee'] = '5%';
        }
        $wallet_data['recharge_amount'] = $recharge;
        $wallet_data['exchange_amount'] = $exchangeAmount;
        return api_response()->success('SUCCESS', array_merge($wallet_data, $verify_data));
    }

    #提币地址管理
    public function withdrawalAddressManagement($user_id)
    {
        $wallet_data = [];
        $ii = 0;
        $data = WithdrawalManagement::query()->where(['user_id' => $user_id])->orderBy("id", 'desc')->get()->groupBy('coin_name');
        if (blank($data)) {
            $coin = Coins::query()->whereRaw('coin_name in ("USDT")')->get();
            foreach ($coin as $con) {
                $wallet_data[$ii]['coin_name'] = $con['coin_name'];
                $wallet_data[$ii]['coin_id'] = $con['coin_id'];
                $wallet_data[$ii]['full_name'] = $con['full_name'];
                $wallet_data[$ii]['coin_icon'] = $con['coin_icon'];
                $wallet_data[$ii]['withdrawal_fee'] = $con['withdrawal_fee'];
                $return_data[$ii]['total_address'] = 0;
                $ii++;
            }

            return api_response()->success('SUCCESS', $wallet_data);
        } else {
            $data = $data->toArray();
        }
        $return_data = [];
        $kk = 0;
        foreach ($data as $coin_name => $results) {
            $coin = Coins::query()->where(['coin_name' => $coin_name])->first();
            if (blank($coin)) {
                return api_response()->success('SUCCESS', []);
            } else {
                $coin = $coin->toArray();
            }
            if ($coin['coin_name'] == "BTC" || $coin['coin_name'] == "USDT") {
                $return_data[$kk]['coin_name'] = $coin['coin_name'];
                $return_data[$kk]['coin_id'] = $coin['coin_id'];
                $return_data[$kk]['full_name'] = $coin['full_name'];
                $return_data[$kk]['coin_icon'] = $coin['coin_icon'];
                $return_data[$kk]['withdrawal_fee'] = $coin['withdrawal_fee'];
                $return_data[$kk]['total_address'] = count($results);
                foreach ($results as $key => $item) {
                    $return_data[$kk]['list'][$key] = $item;
                }
                $kk++;
            }

        }


        return api_response()->success('SUCCESS', $return_data);
    }

    #提币地址删除
    public function withdrawalAddressDeleted($user_id, $id)
    {

        $result = WithdrawalManagement::query()->where(['user_id' => $user_id, 'id' => $id])->delete();
        if ($result) {
            return api_response()->successString('SUCCESS', true);
        } else {
            return api_response()->successString('SUCCESS', false);
        }

    }

    #提币地址添加
    public function withdrawalAddressAdd($user_id, $address, $coin_name, $address_note)
    {
        if ($coin_name != "BTC" && $coin_name != "USDT" && $coin_name != 'ETH') {
            return api_response()->error(100, "暂时只支持添加BTC和USDT");
        }
        if (preg_match('/^[_0-9a-z]{30,50}$/i', $address)) {
            $withdrawal_management = WithdrawalManagement::query()->where(['address' => $address])->first();
            if ($withdrawal_management && $withdrawal_management['address'] == $address) {
                return api_response()->error(100, "地址已存在请勿重新添加");
            }
            $result = WithdrawalManagement::query()->insert([
                'user_id' => $user_id,
                'address' => $address,
                'coin_name' => $coin_name,
                'address_note' => $address_note,
                'datetime' => time(),
            ]);
            if ($result) return api_response()->successString('SUCCESS', true);
        } else {
            return api_response()->error(100, "请填写正确的钱包地址");
        }

    }

    #手工充值
    public function withdrawalAddressAdd1($user_id, $account, $cbsj, $jymm, $coin_id)
    {


        $withdrawal_management = Coins::query()->where(['coin_id' => $coin_id])->first();

        $result = RechargeManualrg::query()->insert([
            'user_id' => $user_id,
            'account' => $account,
            'cbsj' => $cbsj,
            'jymm' => $jymm,
            'coin_id' => $coin_id,
            'pay_currency' => $withdrawal_management['coin_name'],
            'created_at' => date("Y-m-d H:i:s", time()),
        ]);
        if ($result) return api_response()->successString('SUCCESS', true);


    }

    #提币地址修改
    public function withdrawalAddressModify($user_id, $id, $address, $address_note)
    {
        if (preg_match('/^[_0-9a-z]{30,50}$/i', $address)) {
            $result = WithdrawalManagement::query()->where(['user_id' => $user_id, 'id' => $id])->update([
                'address' => $address,
                'address_note' => $address_note
            ]);
            if ($result) {
                return api_response()->successString('SUCCESS', true);

            } else {
                return api_response()->error(100, false);
            }

        } else {
            return api_response()->error(100, "请填写正确的钱包地址");
        }


    }

    #提币选择地址
    public function withdrawalSelectAddress($user_id)
    {
        $data = UserWallet::query()->where(['user_id' => $user_id])->whereIn('coin_id', [1, 2, 3])->get();

        $wallet_data = [];
        foreach ($data as $result) {
            $logo = Coins::query()->where(['coin_name' => $result['coin_name']])->first();
            $wallet_data['list'][] = [
                'coin_name' => $result['coin_name'],
                'full_name' => $logo['full_name'],
                'coin_id' => $result['coin_id'],
                'image' => getFullPath($logo['coin_icon']),
            ];
        }

        return api_response()->success('SUCCESS', $wallet_data);

    }

    #申购
    public function subscribe_copy($user_id)
    {
        $app_locale = App::getLocale();
        $symbol = config('coin.coin_symbol');
        $result = UserSubscribe::query()->where('coin_name', $symbol)->firstOrFail();
        if (time() > strtotime($result['start_subscription_time']) && strtotime($result['end_subscription_time']) > time()) {
            $status = 2;
        } else if (time() > strtotime($result['end_subscription_time']) && strtotime($result['announce_time']) > time()) {
            $status = 3;
        } else if (time() > strtotime($result['announce_time'])) {
            $status = 4;
        } else {
            $status = 1;
        }
        $return_data = [
            'id' => $result['id'],
            'coin_name' => $result['coin_name'],
            'issue_price' => $result['issue_price'],
            'subscribe_currency' => $result['subscribe_currency'],
            'expected_time_online' => $result['expected_time_online'],
            'start_subscription_time' => $result['start_subscription_time'],
            'end_subscription_time' => $result['end_subscription_time'],
            'announce_time' => $result['announce_time'],
            'status' => $status,
            'project_details' => $result['en_project_details'],
        ];
        if ($app_locale == "en") {
            $return_data['project_details'] = $result['en_project_details'];
        } else {
            $return_data['project_details'] = $result['project_details'];
        }
        return api_response()->success('SUCCESS', $return_data);
    }

    #申购 20210507--宁劲-zdx
    public function subscribe($user_id)
    {
        $app_locale = App::getLocale();
        $symbol = config('coin.coin_symbol');
        $result = UserSubscribe::query()->where('coin_name', $symbol)->firstOrFail();
        // var_dump($result->toArray());
        if (time() > strtotime($result['start_subscription_time']) && strtotime($result['end_subscription_time']) > time()) {
            $status = 2;
        } else if (time() > strtotime($result['end_subscription_time']) && strtotime($result['announce_time']) > time()) {
            $status = 3;
        } else if (time() > strtotime($result['announce_time'])) {
            $status = 4;
        } else {
            $status = 1;
        }
        $return_data = [
            'id' => $result['id'],
            'coin_name' => $result['coin_name'],
            'issue_price' => $result['issue_price'],
            'subscribe_currency' => $result['subscribe_currency'],
            'expected_time_online' => $result['expected_time_online'],
            'start_subscription_time' => $result['start_subscription_time'],
            'end_subscription_time' => $result['end_subscription_time'],
            'announce_time' => $result['announce_time'],
            'status' => $status,
            'project_details' => $result['en_project_details'],
        ];
        if ($app_locale == "en") {
            $return_data['project_details'] = $result['en_project_details'];
        } else {
            $return_data['project_details'] = $result['project_details'];
        }

        $return_data['project_details'] = baiduTransAPI($return_data['project_details'], 'auto', $app_locale);

        return api_response()->success('SUCCESS', $return_data);
    }

    public function subscribeActivity($user)
    {
        $data = [];
        $today = Carbon::now()->toDateTimeString();
        $activity = SubscribeActivity::query()
            ->whereDate('start_time', '<', $today)
//            ->whereDate('end_time','>',$today)
            ->where('status', 1)
            ->first();
        if (!blank($activity)) {
            $data['activity'] = $activity;
            if (empty($user)) {
                $subscribe_amount = 0;
            } else {
                $subscribe_amount = UserSubscribeRecord::query()
                    ->where('user_id', $user['user_id'])
                    ->whereBetween('subscription_time', [strtotime($activity['start_time']), strtotime($activity['end_time'])])
                    ->sum('subscription_currency_amount');
            }

            $params = $activity['params'];
            $step = 0;
            foreach ($params as $key => $item) {
                if ($subscribe_amount > $item['amount']) {
                    $step = $key + 1;
                }
            }
            $data['subscribe_amount'] = $subscribe_amount;
            $data['step'] = $step;
        }

        return $data;
    }

    #申购币种集合
    public function subscribeTokenList_copy($user_id)
    {
        $self_coin = config('coin.coin_symbol');
        $subscribe = UserSubscribe::query()->where('coin_name', $self_coin)->firstOrFail();

        $first = UserWallet::query()->where(['user_id' => $user_id])->first();

        if (!$first) {
            $btc_price = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
            $eth_price = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
            $return_data = [];

            $coins = explode('/', $subscribe['subscribe_currency']);
            foreach ($coins as $key => $coin_name) {
                $issue_price = Cache::store('redis')->get('market:' . strtolower($self_coin) . 'usdt' . '_detail')['close'];
                $coins = Coins::query()->where(['coin_name' => $coin_name])->first();
                if ($coin_name == "BTC") {
                    $currency_amount = $btc_price / $issue_price;
                } else if ($coin_name == "ETH") {
                    $currency_amount = $eth_price / $issue_price;
                } else {
                    $currency_amount = 1 / $issue_price;
                }

                $return_data[$key] = [
                    'coin_name' => $coin_name,
                    'qtyDecimals' => $coins['qty_decimals'],
                    'priceDecimals' => $coins['price_decimals'],
                    'proportion_amount' => $currency_amount,
                    'subscribe_coin_name' => $subscribe['coin_name'],
                ];
            }
        } else {
            $btc_price = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
            $eth_price = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
            $return_data = [];
            $coins = explode('/', $subscribe['subscribe_currency']);
            $results = UserWallet::query()->where(['user_id' => $user_id])->whereIn('coin_name', $coins)->get();
            foreach ($results as $key => $result) {
                $issue_price = Cache::store('redis')->get('market:' . strtolower($self_coin) . 'usdt' . '_detail')['close'];
                $coins = Coins::query()->where(['coin_name' => $result['coin_name']])->first();
                if ($result['coin_name'] == "BTC") {
                    $currency_amount = $btc_price / $issue_price;
                } else if ($result['coin_name'] == "ETH") {
                    $currency_amount = $eth_price / $issue_price;
                } else {
                    $currency_amount = 1 / $issue_price;
                }

                $return_data[$key] = [
                    'coin_name' => $result['coin_name'],
                    'qtyDecimals' => $coins['qty_decimals'],
                    'priceDecimals' => $coins['price_decimals'],
                    'proportion_amount' => $currency_amount,
                    'subscribe_coin_name' => $subscribe['coin_name'],
                    'usable_balance' => $result['usable_balance'],
                ];
            }
        }

        return api_response()->success('SUCCESS', $return_data);
    }

    #申购币种集合-- 20210507--宁劲-zdx---使用假数据
    public function subscribeTokenList($user_id)
    {

        $issue_price_mook = [
            "id" => 1620869280,
            "count" => 996.78864,
            "open" => 0.14111,
            "low" => 0.1411,
            "high" => 0.14115,
            "vol" => 1295.72449,
            "version" => 1620869280,
            "ts" => 1620869328679,
            "amount" => 996.78835,
            "close" => 0.14117,
            "price" => 0.14117,
            "increase" => 0.0197,
            "increaseStr" => "+1.97%",
            "prices" => [
                0.1406,
                0.1412,
                0.14111,
                0.1412,
                0.14104,
                0.14111,
                0.14111,
                0.14118,
                0.14102,
                0.14114,
            ]
        ];

        $self_coin = config('coin.coin_symbol');
        // var_dump($self_coin);exit;
        $subscribe = UserSubscribe::query()->where('coin_name', $self_coin)->firstOrFail();

        $first = UserWallet::query()->where(['user_id' => $user_id])->first();

        if (!$first) {
            $btc_price = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
            $eth_price = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
            $ltc_price = Cache::store('redis')->get('market:' . 'ltcusdt' . '_detail')['close'];
            $return_data = [];

            $coins = explode('/', $subscribe['subscribe_currency']);
            foreach ($coins as $key => $coin_name) {
                // $issue_price = Cache::store('redis')->get('market:' . strtolower($self_coin) . 'usdt' . '_detail')['close'];
                $issue_price = Cache::store('redis')->get('market:' . strtolower($self_coin) . 'usdt' . '_detail', $issue_price_mook)['close'];
                $coins = Coins::query()->where(['coin_name' => $coin_name])->first();
                if ($coin_name == "BTC") {
                    $currency_amount = $btc_price / $issue_price;
                } else if ($coin_name == "ETH") {
                    $currency_amount = $eth_price / $issue_price;
                } else if ($coin_name == "LTC") {
                    $currency_amount = $ltc_price / $issue_price;
                } else {
                    $currency_amount = 1 / $issue_price;
                }

                $return_data[$key] = [
                    'coin_name' => $coin_name,
                    'qtyDecimals' => $coins['qty_decimals'],
                    'priceDecimals' => $coins['price_decimals'],
                    'price' => $issue_price,
                    'proportion_amount' => $currency_amount,
                    'subscribe_coin_name' => $subscribe['coin_name'],
                ];
            }
        } else {
            $btc_price = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
            $eth_price = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
            $ltc_price = Cache::store('redis')->get('market:' . 'ltcusdt' . '_detail')['close'];
            $return_data = [];
            $coins = explode('/', $subscribe['subscribe_currency']);
            $results = UserWallet::query()->where(['user_id' => $user_id])->whereIn('coin_name', $coins)->get();

            foreach ($results as $key => $result) {
                // $issue_price = Cache::store('redis')->get('market:' . strtolower($self_coin) . 'usdt' . '_detail')['close'];

                $issue_price = Cache::store('redis')
                    ->get(
                        'market:' . strtolower($self_coin) . 'usdt' . '_detail',
                        $issue_price_mook
                    )['close'];

                $coins = Coins::query()->where(['coin_name' => $result['coin_name']])->first();

                if ($result['coin_name'] == "BTC") {
                    $currency_amount = $btc_price / $issue_price;
                } else if ($result['coin_name'] == "ETH") {
                    $currency_amount = $eth_price / $issue_price;
                } else if ($result['coin_name'] == "LTC") {
                    $currency_amount = $ltc_price / $issue_price;
                } else {
                    $currency_amount = 1 / $issue_price;
                }

                $return_data[$key] = [
                    'coin_name' => $result['coin_name'],
                    'qtyDecimals' => $coins['qty_decimals'],
                    'priceDecimals' => $coins['price_decimals'],
                    'price' => $issue_price,
                    'proportion_amount' => $currency_amount,
                    'subscribe_coin_name' => $subscribe['coin_name'],
                    'usable_balance' => $result['usable_balance'],
                ];
            }
        }

        return api_response()->success('SUCCESS', $return_data);
    }

    #申购
    public function subscribeNow($user_id, $amount, $coin_name)
    {
//        $agent_code = "BW5P71";
//        if ($invitation_code != $agent_code) {
//            return api_response()->error(100, '申购码不存在,请联系专属客服咨询');
//        }
        $btc_price = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
        $eth_price = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
        $ltc_price = Cache::store('redis')->get('market:' . 'ltcusdt' . '_detail')['close'];
        $user = User::query()->findOrFail($user_id);
        $user_wallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();
        $usable_balance = $user_wallet['usable_balance'];

        if ($amount > $usable_balance) {
            throw new ApiException('资金账户币种余额不足');
        }
        DB::beginTransaction();
        try {
            $symbol = config('coin.coin_symbol');
            $subscribe = UserSubscribe::query()->where('coin_name', $symbol)->first();

            if (time() < strtotime($subscribe['start_subscription_time'])) {
                throw new ApiException('申购预热中！！！');
            }
            if (time() > strtotime($subscribe['end_subscription_time'])) {
                throw new ApiException('申购已经结束,等待公布结果！！！');
            }

            $currency_price = Cache::store('redis')->get('market:' . strtolower($symbol) . 'usdt' . '_detail')['close'];

            $subscription_currency_name = $subscribe['coin_name'];

            if ($coin_name == "BTC") {
                $currency_amount = $btc_price * $amount / $currency_price;
            } else if ($coin_name == "ETH") {
                $currency_amount = $eth_price * $amount / $currency_price;
            } else if ($coin_name == "LTC") {
                $currency_amount = $ltc_price * $amount / $currency_price;
            } else {
                $currency_amount = 1 * $amount / $currency_price;
            }

            if ($currency_amount < $subscribe['minimum_purchase'] || $currency_amount > $subscribe['maximum_purchase']) {
                $app_locale = App::getLocale();
                if ($app_locale == 'zh-CN') {
                    $msg = "最少申购" . $subscribe['minimum_purchase'] . " ~ 最大申购" . $subscribe['maximum_purchase'];
                } else {
                    $msg = "Minimum purchase " . $subscribe['minimum_purchase'] . " ~ Maximum purchase " . $subscribe['maximum_purchase'];
                }
                throw new ApiException($msg);
            }

            // 添加申购记录
            $res = UserSubscribeRecord::query()->where(['user_id' => $user_id])->insert([
                'user_id' => $user_id,
                'payment_amount' => $amount,
                'payment_currency' => $coin_name,
                'subscription_time' => time(),
                'subscription_currency_name' => $subscription_currency_name,
                'subscription_currency_amount' => $currency_amount,
            ]);

            // 更新资产
            $subscribe_coin = Coins::query()->where('coin_name', $subscription_currency_name)->firstOrFail();
            $user->update_wallet_and_log($user_wallet['coin_id'], 'usable_balance', -$amount, UserWallet::asset_account, 'subscribe');
            $user->update_wallet_and_log($subscribe_coin['coin_id'], 'usable_balance', $currency_amount, UserWallet::asset_account, 'subscribe');

            // 发放奖励
            $this->invit_commission($user, $amount, $coin_name);

            DB::commit();

            return $res;

        } catch (\Exception $e) {
            DB::rollBack();

            throw new ApiException($e->getMessage());
        }

    }

    // 小白
    // 发放佣金奖励
    public function invit_commission($user, $money, $coin_name)
    {
        if (empty($user)) {
            throw new ApiException('用户异常');
        }
        if (empty($money)) {
            throw new ApiException('消费金额错误');
        }
        if ($money <= 0) {
            throw new ApiException('消费金额不能低于0');
        }
        if (empty($coin_name)) {
            throw new ApiException('钱包类型错误');
        }

        // 获取配置
        $reward = RewardConfig::query()->where(['status' => '1'])->first();

        if (!empty($reward)) {
            if ($coin_name == 'BTC') {
                // 获取发行币行情
                $mark = 'btcusdt';
                $symbol_name = 'market:' . $mark . '_newPrice';
                $btc_data = Cache::store('redis')->get($symbol_name);
                $btc_price = $btc_data["price"];

                // 判断usdt价格
                $money = $money * $btc_price;
            }
            if ($coin_name == 'ETH') {
                // 获取发行币行情
                $mark = 'ethusdt';
                $symbol_name = 'market:' . $mark . '_newPrice';
                $eth_data = Cache::store('redis')->get($symbol_name);
                $eth_price = $eth_data["price"];

                // 判断usdt价格
                $money = $money * $eth_price;
            }


            // 获取发行币行情
            $mark = 'kkkusdt';
            $symbol_name = 'market:' . $mark . '_newPrice';
            $data = Cache::store('redis')->get($symbol_name);
            $price = $data["price"];
            if ($price > 0) {
                // 获取奖励金额
                $invit_money = $money * ($reward->buy_sup_reward / 100);

                // 获取上级用户
                $invit_user = User::query()->where(['user_id' => $user->pid])->first();
                if ($invit_user) {
                    // 获取钱包
                    $invit_wallet = UserWallet::query()->where(['coin_name' => 'KKK', 'user_id' => $invit_user->user_id])->first();
                    if ($invit_wallet) {
                        $invit_money = sprintf("%.8f", $invit_money / $price);
                        $invit_user->update_wallet_and_log($invit_wallet->coin_id, 'usable_balance', $invit_money, UserWallet::asset_account, 'dividend');
                    }
                }

                // 获取上级的上级
                $team_money = $money * ($reward->buy_sup_team_reward / 100);
                $team_user = User::query()->where(['user_id' => $invit_user->pid])->first();
                if ($team_user) {
                    // 获取钱包
                    $team_wallet = UserWallet::query()->where(['coin_name' => 'MED', 'user_id' => $team_user->user_id])->first();
                    if ($team_wallet) {
                        $team_money = sprintf("%.8f", $team_money / $price);
                        $team_user->update_wallet_and_log($team_wallet->coin_id, 'usable_balance', $team_money, UserWallet::asset_account, 'dividend');
                    }
                }
            }
        }

    }

    #申购-- 20210507--宁劲-zdx
    public function subscribeNow_copy($user_id, $amount, $coin_name)
    {

        // var_dump($coin_name); // USDT

        $btc_price = Cache::store('redis')->get('market:' . 'btcusdt' . '_detail')['close'];
        $eth_price = Cache::store('redis')->get('market:' . 'ethusdt' . '_detail')['close'];
        $user = User::query()->findOrFail($user_id);
        $user_wallet = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();

        $usable_balance = $user_wallet['usable_balance'];
        if ($amount > $usable_balance) {
            throw new ApiException('资金账户币种余额不足');
        }
        DB::beginTransaction();
        try {
            $symbol = config('coin.coin_symbol');
            $subscribe = UserSubscribe::query()->where('coin_name', $symbol)->first();

            if (time() < strtotime($subscribe['start_subscription_time'])) {
                throw new ApiException('申购预热中！！！');
            }
            if (time() > strtotime($subscribe['end_subscription_time'])) {
                throw new ApiException('申购已经结束,等待公布结果！！！');
            }

            $currency_price = Cache::store('redis')->get('market:' . 'aetc' . 'usdt' . '_detail')['close'];

            $subscription_currency_name = $subscribe['coin_name'];
            if ($coin_name == "BTC") {
                $currency_amount = $btc_price * $amount / $currency_price;
            } else if ($coin_name == "ETH") {
                $currency_amount = $eth_price * $amount / $currency_price;
            } else {
                $currency_amount = 1 * $amount / $currency_price;
            }

            if ($currency_amount < $subscribe['minimum_purchase'] || $currency_amount > $subscribe['maximum_purchase']) {
                $app_locale = App::getLocale();
                if ($app_locale == 'zh-CN') {
                    $msg = "最少申购" . $subscribe['minimum_purchase'] . " ~ 最大申购" . $subscribe['maximum_purchase'];
                } else {
                    $msg = "Minimum purchase " . $subscribe['minimum_purchase'] . " ~ Maximum purchase " . $subscribe['maximum_purchase'];
                }
                throw new ApiException($msg);
            }

            // 添加申购记录
            $res = UserSubscribeRecord::query()->where(['user_id' => $user_id])->insert([
                'user_id' => $user_id,
                'payment_amount' => $amount,
                'payment_currency' => $coin_name,
                'subscription_time' => time(),
                'subscription_currency_name' => $subscription_currency_name,
                'subscription_currency_amount' => $currency_amount,
            ]);

            // 更新资产
            $subscribe_coin = Coins::query()->where('coin_name', $subscription_currency_name)->firstOrFail();

            $user->update_wallet_and_log($user_wallet['coin_id'], 'usable_balance', -$amount, UserWallet::asset_account, 'subscribe');
            $user->update_wallet_and_log($subscribe_coin['coin_id'], 'usable_balance', $currency_amount, UserWallet::asset_account, 'subscribe');

            DB::commit();

            return $res;

        } catch (\Exception $e) {
            DB::rollBack();

            throw new ApiException($e->getMessage());
        }

    }

    #申购公布结果
    public function subscribeAnnounceResults()
    {
        $result = UserSubscribeRecord::query()->orderBy('subscription_currency_amount', 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    #上币申请
    public function applicationForListing($user_id, $params)
    {
        $mobile = isMobile($params['contact_phone']);
        if (!$mobile) {
            return api_response()->error(100, "手机号输入错误");
        }
        $email = isEmail($params['contact_email']);
        if (!$email) {
            return api_response()->error(100, "邮箱输入有误");
        }
        $entrust = ListingApplication::query()->insert([
            //创建申请上币

            'user_id' => $user_id,
            'application_time' => time(),
            'coin_name' => $params['coin_name'],
            'coin_chinese_name' => $params['coin_chinese_name'],
            'contact_position' => $params['contact_position'],
            'contact_phone' => $params['contact_phone'],
            'coin_market_price' => $params['coin_market_price'],
            'contact_email' => $params['contact_email'],
            'cotes_const' => $params['cotes_const'],
            'agency_personnel' => $params['agency_personnel'],
            'currency_code' => $params['currency_code'],
            'currency_identification' => $params['currency_identification'],
            'placement' => $params['placement'],
            'official_website' => $params['official_website'],
            'white_paper_link' => $params['white_paper_link'],
            'currency_circulation' => $params['currency_circulation'],
            'coin_turnover' => $params['coin_turnover'],
            'coin_allocation_proportion' => $params['coin_allocation_proportion'],
            'cash_people_counting' => $params['cash_people_counting'],
            'online_bourse' => $params['online_bourse'],
            'private_cemetery_price' => $params['private_cemetery_price'],
            'block_network_type' => $params['block_network_type'],
            'currency_issue_date' => $params['currency_issue_date'],
            'blockchain_browser' => $params['blockchain_browser'],
            'official_wallet_address' => $params['official_wallet_address'],
            'contract_address' => $params['contract_address'],
            'twitter_link' => $params['twitter_link'],
            'telegram_link' => $params['telegram_link'],
            'facebook_link' => $params['facebook_link'],
            'listing_fee_budget' => $params['listing_fee_budget'],
            'market_currency_quantity' => $params['market_currency_quantity'],
            'currency_chinese_introduction' => $params['currency_chinese_introduction'],
            'currency_english_introduction' => $params['currency_english_introduction'],
            'remarks' => $params['remarks'],
            'white_paper' => $params['white_paper'],
            'referrer_mechanism_code' => $params['referrer_mechanism_code'],
        ]);
        if ($entrust) {
            return api_response()->success("SUCCESS", true);
        } else {
            return api_response()->error(100, false);
        }

    }


//    public function subscribeRelease($user_id)
//    {
//        $user_wallet=UserWallet::query()->where(['user_id'=>$user_id,'coin_name'=>'STAI'])->first();
//        $user_subscribe=UserSubscribe::query()->where(['id'=>1])->first();
//        $announce_results_time=$user_subscribe[''];
//
//
//    }
    #市场币种添加
    public function marketTokenAdd($user_id)
    {
        $coins = Coins::query()->where(['status' => 1])->get();
        foreach ($coins as $coin) {
            $result = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin['coin_name']])->first();
            if (blank($result)) {
                UserWallet::query()->create([
                    'user_id' => $user_id,
                    'coin_id' => $coin['coin_id'],
                    'coin_name' => $coin['coin_name'],
                ]);
            }
        }
    }

    public function tradingPairCurrency($symbol)
    {
        $inside = InsideTradePair::query()->where(['symbol' => $symbol])->first();
        return api_response()->success('SUCCESS', $inside);
    }


    public function createWalletAddress($user_id)
    {
//        $omni_usdt = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => "USDT", 'omni_wallet_address' => ""])->first();
//        $userUsd = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => "USDT", 'wallet_address' => ""])->first();
//        $userEth = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => "ETH", 'wallet_address' => ""])->first();
//        if ($omni_usdt || $userUsd || $userEth) {
//            $user_account = UserWallet::query()->where(['user_id' => $user_id])->get();
//            foreach ($user_account as $user) {
//                switch ($user['coin_name']) {
//                    case "BTC" :
//                        $address = createWalletAddress($user_id, $user['coin_name']);
//                        if ($address !== false) {
//                            UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $user['coin_id']])->update([
//                                'wallet_address' => $address,
//                            ]);
//                        }
//                        break;
//                    case "ETH" :
//                        $address = createWalletAddress($user_id, $user['coin_name']);
//                        if ($address !== false) {
//                            UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $user['coin_id']])->update([
//                                'wallet_address' => $address
//                            ]);
//                        }
//                        break;
//                    case "USDT" :
//                        $address = createWalletAddress($user_id, $user['coin_name']);
//                        $address_omni = createWalletAddress($user_id, "BTC");
//                        if ($address !== false && $address_omni !== false) {
//                            UserWallet::query()->where(['user_id' => $user_id, 'coin_id' => $user['coin_id']])->update([
//                                'omni_wallet_address' => $address_omni,
//                                'wallet_address' => $address
//                            ]);
//                        }
//                        break;
//                    default :
//                        break;
//                }
//            }
//        }
    }

    #充币记录
    public function appDepositHistory($user_id, $coin_name)
    {
        $result = Recharge::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    #用户提币
    public function appWithdrawalRecord($user_id, $coin_name)
    {
        $result = Withdraw::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    #钱包资金划转记录
    public function appTransferRecord($user_id, $coin_name)
    {
        $result = TransferRecord::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->orderBy("id", 'desc')->paginate();
        return api_response()->success('SUCCESS', $result);
    }

    public function appFundsTransfer($user_id, $coin_name, $amount, $first_account, $last_account)
    {
        #UserWallet CoinAccount   SustainableAccount OptionAccount
//        global $result1,$result2,$result3,$first,$last,$time,$direction;

        global $draw_out_direction, $into_direction;
        DB::beginTransaction();
        try {
            #资金划转期权账户开始#
            if ($first_account != null && $last_account != null) {

                switch ($first_account) {
                    case "UserWallet":
                        $first = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();
                        break;

                    case "ContractAccount":
                        $first = SustainableAccount::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();
                        break;
                    case "LeverageAccount":

                        break;
                    case "FinancialAccount":

                        break;


                }
                switch ($last_account) {
                    case "UserWallet":
                        $last = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();
                        break;

                    case "ContractAccount":
                        $last = SustainableAccount::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->firstOrFail();
                        break;
                    case "LeverageAccount":

                        break;
                    case "FinancialAccount":

                        break;
                }

                #可用余额
                $usable_balance = $first['usable_balance'];
                if ($amount > $usable_balance) {
                    return api_response()->error(100, "超出可划转余额,请重新输入");
                }

            }
            switch ($first_account) {
                case "UserWallet":
                    $draw_out_direction = "UserWallet";
                    break;
                case "ContractAccount":
                    $draw_out_direction = "ContractAccount";
                    break;
                case "LeverageAccount":

                    break;
                case "FinancialAccount":

                    break;
            }
            #期权划转资金账户结束#
            switch ($last_account) {
                case "UserWallet":
                    $into_direction = "UserWallet";
                    break;
                case "ContractAccount":
                    $into_direction = "ContractAccount";
                    break;
                case "LeverageAccount":

                    break;
                case "FinancialAccount":

                    break;
            }


            $time = time();
            $coinId = Coins::query()->where(['coin_name' => $coin_name])->first();
            $result3 = TransferRecord::query()->insert([
                'user_id' => $user_id,
                'coin_id' => $coinId['coin_id'],
                'coin_name' => $coin_name,
                'amount' => $amount,
                'draw_out_direction' => $draw_out_direction,
                'into_direction' => $into_direction,
                'datetime' => $time,
                'status' => 1,
            ]);
            $user = User::query()->find($user_id);
            if ($draw_out_direction == "UserWallet") {
                $result1 = $user->update_wallet_and_log($first['coin_id'], 'usable_balance', -$amount, UserWallet::asset_account, 'fund_transfer');
                $result2 = $user->update_wallet_and_log($first['coin_id'], 'usable_balance', $amount, UserWallet::sustainable_account, 'fund_transfer');
            } else {
                $result1 = $user->update_wallet_and_log($first['coin_id'], 'usable_balance', -$amount, UserWallet::sustainable_account, 'fund_transfer');
                $result2 = $user->update_wallet_and_log($first['coin_id'], 'usable_balance', $amount, UserWallet::asset_account, 'fund_transfer');
            }
            if ($result1 && $result2 && $result3) {

                DB::commit();
                return api_response()->successString('SUCCESS', true);

            }

        } catch (\Exception $e) {
            DB::rollBack();

            return api_response()->error(100, "该币种不可划转");
        }
    }

    public function appTokenAssets($user_id, $coin_name)
    {
        $result_data = [];
        $result = UserWallet::query()->where(['user_id' => $user_id, 'coin_name' => $coin_name])->first();
        $usable_balance = $result['usable_balance'];
        $freeze_balance = $result['freeze_balance'];
        $total_assets = $usable_balance + $freeze_balance;
        $result_data['usable_balance'] = $result['usable_balance'];
        $result_data['freeze_balance'] = $result['freeze_balance'];
        $result_data['total_assets'] = $total_assets;
        return api_response()->success("SUCCESS", $result_data);

    }

    public function walletPaymentMethod($user_id, $coin_name, $address_type)
    {
        return $user_id . $coin_name . $address_type;

    }

    public function collectionType($user_id)
    {
        $result_data = [];
        $result = UserDepositAddress::query()->get();
        foreach ($result as $key => $res) {
            $result_data[$key]['id'] = $res['id'];
            $result_data[$key]['coin_name'] = $res['coin_name'];
            $result_data[$key]['wallet_address'] = $res['wallet_address'];
            $result_data[$key]['wallet_address_image'] = $res['wallet_address'];

        }

        return api_response()->success("SUCCESS", $result_data);
    }


}
