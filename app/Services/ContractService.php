<?php


namespace App\Services;


use App\Exceptions\ApiException;
use App\Handlers\ContractTool;
use App\Jobs\HandleContractEntrust;
use App\Jobs\HandleFlatPosition;
use App\Models\Coins;
use App\Models\ContractEntrust;
use App\Models\ContractOrder;
use App\Models\ContractPair;
use App\Models\ContractPosition;
use App\Models\ContractShare;
use App\Models\ContractStrategy;
use App\Models\SustainableAccount;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\UserWalletLog;
use Carbon\Carbon;
use Doctrine\DBAL\DBALException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use phpDocumentor\Reflection\Types\False_;

class ContractService
{
    public function positionShare($user, $params)
    {
        $position = ContractPosition::query()
            ->where('user_id', $user['user_id'])
            ->where('symbol', $params['symbol'])
            ->where('side', $params['position_side'])
            ->where('hold_position', '>', 0)
            ->first();
        if (blank($position)) {
            $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
            $data['price1'] = '--';
            $data['price2'] = $realtime_price;
            $data['symbol'] = '--';
            $data['lever_rate'] = '--';
            $data['profit'] = 0;
            $data['profitRate'] = '0%';
        } else {
            $contract = ContractPair::query()->find($position['contract_id']);
            // 获取最新一条成交记录 即实时最新价格
            $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
            $unRealProfit = ContractTool::unRealProfit($position, $contract, $realtime_price);
            $data['price1'] = empty($position['avg_price']) ? '--' : $position['avg_price'];
            $data['price2'] = $realtime_price;
            $data['symbol'] = $position['symbol'] ?? '--';
            $data['lever_rate'] = $position['lever_rate'] ?? 100;
            $data['profit'] = $unRealProfit;
            $data['profitRate'] = PriceCalculate($unRealProfit, '/', $position['position_margin'], 4) * 100 . '%';
        }

        $items = ContractShare::query()->where('status', 1)->get()->map(function ($v, $k) {
            $m['bg_img'] = getFullPath($v['bg_img']);
            $m['text_img'] = getFullPath($v['text_img']);
            $m['peri_img'] = getFullPath($v['peri_img']);
            return $m;
        });
        $data['share_imgs'] = $items;
        return $data;
    }

    public function entrustShare($user, $params)
    {
        $entrust = ContractEntrust::query()->where('order_type', 2)->where('user_id', $user['user_id'])->where('id', $params['entrust_id'])->first();
        if (blank($entrust)) throw new ApiException('参数错误');

        $position_side = $entrust['side'] == 1 ? 2 : 1;
        $open_position_price = ContractPosition::query()->where(['user_id' => $entrust['user_id'], 'contract_id' => $entrust['contract_id'], 'side' => $position_side])->value('avg_price');
        $data['price1'] = empty($open_position_price) ? '--' : $open_position_price;
        $data['price2'] = $entrust['avg_price'] ?? '--';
        $data['symbol'] = $entrust['symbol'] ?? '--';
        $data['lever_rate'] = $entrust['lever_rate'] ?? 100;
        $data['profit'] = $entrust['profit'];
        $data['profitRate'] = PriceCalculate($entrust['profit'], '/', $entrust['margin'], 4) * 100 . '%';

        $items = ContractShare::query()->where('status', 1)->get()->map(function ($v, $k) {
            $m['bg_img'] = getFullPath($v['bg_img']);
            $m['text_img'] = getFullPath($v['text_img']);
            $m['peri_img'] = getFullPath($v['peri_img']);
            return $m;
        });
        $data['share_imgs'] = $items;
        return $data;
    }

    public function getSymbolDetail($params)
    {
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        return $pair;
    }

//    public function contractAccountList($user)
//    {
//        $data = [];
//        $totalAssetsUsd = 0;
//        $totalUnRealProfit = 0;
//        $data['accountList'] = [];
//        $contracts = ContractPair::query()->where('status',1)->get();
//        $wallet = SustainableAccount::query()->where('user_id' , $user['user_id'])->first();
//        foreach ($contracts as $k => $contract){
//            // 获取最新一条成交记录 即实时最新价格
//            $trade_detail = Cache::store('redis')->get('swap:' . 'trade_detail_' . $contract['symbol']);
//            $realtime_price = $trade_detail['price'];
//            $buy_position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $contract['id'], 'side' => 1]);
//            $sell_position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $contract['id'], 'side' => 2]);
//            $buy_position_profit = ContractTool::unRealProfit($buy_position,$contract,$realtime_price); // 多仓盈亏
//            $sell_position_profit = ContractTool::unRealProfit($sell_position,$contract,$realtime_price); // 空仓盈亏
//
//            $account = [];
//            $account['contract_id'] = $contract['id'];
//            $account['symbol'] = $contract['symbol'];
//            $account['symbolName'] = $contract['symbol'] . '/' . $contract['type'];
//            $account['icon'] = Coins::icon($contract['symbol']);
//            $account['usable_balance'] = $wallet['usable_balance'];
//            $account['used_balance'] = $wallet['used_balance'];
//            $account['freeze_balance'] = $wallet['freeze_balance'];
//            $account['unRealProfit'] = $buy_position_profit + $sell_position_profit; // 未实现盈亏
//            $account['account_equity'] = custom_number_format($account['usable_balance'] + $account['used_balance'] + $account['freeze_balance'] + $account['unRealProfit'],4); // 永续账户权益 = 账户可用余额 + 持仓保证金 + 委托冻结保证金 + 未实现盈亏
//            // 风险率 用以衡量当前合约账户风险程度的指标。风险率越低，账户风险越高，当风险率=10.0%时，将会被强制平仓。风险率=账户权益/（持仓保证金+委托冻结）*100%
//            $riskRate = ContractTool::riskRate($account);
//            $account['riskRate'] = $riskRate * 100 . '%';
//            // 预估强平价 合约账户风险率=10.0%时的预估价格。此价格仅供参考，实际强平价以发生强平事件时成交的价格为准
//            $account['flatPrice'] = ContractTool::flatPrice($account,$buy_position,$sell_position,$contract);
//            $account['lever_rate'] = $buy_position['lever_rate'];
//            $account['buy_hold_position'] = $buy_position['hold_position'];
//            $account['sell_hold_position'] = $sell_position['hold_position'];
//
//            $data['accountList'][$k] = $account;
//
//            $totalUnRealProfit += $account['unRealProfit'];
//        }
//        $totalAssetsUsd = ($wallet['usable_balance'] + $wallet['used_balance'] + $wallet['freeze_balance']);
//        $data['totalAssetsUsd'] = $totalAssetsUsd;
//        $data['totalUnRealProfit'] = $totalUnRealProfit;
//        $data['symbol'] = 'USDT';
//        return $data;
//    }

    public function contractAccountFlow($user, $params)
    {
        $builder = UserWalletLog::query()
            ->where('user_id', $user['user_id'])
            ->where('rich_type', 'usable_balance')
            ->where('account_type', UserWallet::sustainable_account);

        if (!empty($params['symbol'])) {
            $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
            if (!blank($pair)) {
                $builder->where('sub_account', $pair['id']);
            }
        }

        return $builder->orderByDesc('id')->paginate();
    }

//    public function contractAccount($user,$params)
//    {
//        $pair = ContractPair::query()->where('symbol',$params['symbol'])->first();
//        if(blank($pair)) throw new ApiException('合约不存在');
//        // 合约保证金账户
//        $wallet = SustainableAccount::query()->where(['user_id' => $user->user_id,'coin_id' => $pair['margin_coin_id']])->first();
//        if(blank($wallet)) throw new ApiException('账户类型错误');
//
//        // 获取最新一条成交记录 即实时最新价格
//        $trade_detail = Cache::store('redis')->get('swap:' . 'trade_detail_' . $pair['symbol']);
//        $realtime_price = $trade_detail['price'];
//        $buy_position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => 1]);
//        $sell_position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => 2]);
//        $buy_position_profit = ContractTool::unRealProfit($buy_position,$pair,$realtime_price); // 多仓盈亏
//        $sell_position_profit = ContractTool::unRealProfit($sell_position,$pair,$realtime_price); // 空仓盈亏
//
//        $account = [];
//        $account['contract_id'] = $pair['id'];
//        $account['usable_balance'] = $wallet['usable_balance'];
//        $account['used_balance'] = $wallet['used_balance'];
//        $account['freeze_balance'] = $wallet['freeze_balance'];
//        $account['unRealProfit'] = $buy_position_profit + $sell_position_profit; // 未实现盈亏
//        $account['account_equity'] = custom_number_format($account['usable_balance'] + $account['used_balance'] + $account['freeze_balance'] + $account['unRealProfit'],4); // 永续账户权益 = 账户可用余额 + 持仓保证金 + 委托冻结保证金 + 未实现盈亏
//        // 风险率 用以衡量当前合约账户风险程度的指标。风险率越低，账户风险越高，当风险率=10.0%时，将会被强制平仓。风险率=账户权益/（持仓保证金+委托冻结）*100%
//        $riskRate = ContractTool::riskRate($account);
//        $account['riskRate'] = $riskRate * 100 . '%';
//        // 预估强平价 合约账户风险率=10.0%时的预估价格。此价格仅供参考，实际强平价以发生强平事件时成交的价格为准
//        $account['flatPrice'] = ContractTool::flatPrice($account,$buy_position,$sell_position,$pair);
//        $account['lever_rate'] = $buy_position['lever_rate'];
//
//        return $account;
//    }

    public function contractAccount($user, $params)
    {
        // 合约保证金账户
        $wallet = SustainableAccount::getContractAccount($user['user_id']);
        if (blank($wallet)) throw new ApiException('账户类型错误');

        $account = [];
        $totalUnrealProfit = 0;
        $positions = ContractPosition::query()->where('user_id', $user['user_id'])->where('hold_position', '>', 0)->get();
        foreach ($positions as $position) {
            $contract = ContractPair::query()->find($position['contract_id']);
            // 获取最新一条成交记录 即实时最新价格
            $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
            $unRealProfit = ContractTool::unRealProfit($position, $contract, $realtime_price);
            $totalUnrealProfit += $unRealProfit;
        }

        $account['usable_balance'] = $wallet['usable_balance'];
        $account['used_balance'] = $wallet['used_balance'];
        $account['freeze_balance'] = $wallet['freeze_balance'];
        $account['totalUnrealProfit'] = custom_number_format($totalUnrealProfit, 5);
        $account['account_equity'] = custom_number_format($account['usable_balance'] + $account['used_balance'] + $account['totalUnrealProfit'], 4); // 永续账户权益 = 账户可用余额 + 持仓保证金 + 委托冻结保证金 + 未实现盈亏
        // 风险率 用以衡量当前合约账户风险程度的指标。风险率越低，账户风险越高，当风险率=10.0%时，将会被强制平仓。风险率=账户权益/（持仓保证金+委托冻结）*100%
        $riskRate = ContractTool::riskRate($account);
        $account['riskRate'] = $riskRate * 100 . '%';

        if (!empty($params['symbol'])) {
            $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
            $default_lever = $pair['default_lever'] ?? 100;
            $symbolPosition = ContractPosition::query()->where('user_id', $user['user_id'])->where('symbol', $params['symbol'])->first();
            $account['lever_rate'] = blank($symbolPosition) ? $default_lever : $symbolPosition['lever_rate'];
        } else {
            $account['lever_rate'] = 100;
        }

        return $account;
    }

    // 获取用户持仓信息
    public function holdPosition($user, $params)
    {
        $builder = ContractPosition::query()->where('user_id', $user['user_id'])->where('hold_position', '>', 0)->where('status', 1);
        if (!empty($params['symbol'])) {
            $builder->where('symbol', $params['symbol']);
        }
        $positions = $builder->get();

        // 合约保证金账户
        $account = SustainableAccount::getContractAccount($user['user_id']);
        foreach ($positions as $position) {
            $contract = ContractPair::query()->find($position['contract_id']);
            // 获取最新一条成交记录 即实时最新价格
            $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
            $position['pair_name'] = $contract['symbol'] . '/' . $contract['type'];
            $position['unRealProfit'] = ContractTool::unRealProfit($position, $contract, $realtime_price);
            $position['profitRate'] = PriceCalculate($position['unRealProfit'], '/', $position['position_margin'], 4) * 100 . '%';
            $position['flatPrice'] = ContractTool::getFlatPrice($account, $contract);
            $position['realtimePrice'] = $realtime_price;
            $strategy = ContractStrategy::query()
                ->where('position_id', $position['id'])
//                ->where('user_id', $user['user_id'])
//                ->where('status', 1)
//                ->where('contract_id', $position['contract_id'])
//                ->where('position_side', $position['side'])
                ->first();
            $position['tpPrice'] = $strategy['tp_trigger_price'] ?? '';
            $position['slPrice'] = $strategy['sl_trigger_price'] ?? '';
        }

        return $positions;
    }

    public function holdPosition2($user, $params)
    {
//        $builder = ContractPosition::query()->where('user_id', $user['user_id'])->where('hold_position', '>', 0);
//        if (!empty($params['symbol'])) {
//            $builder->where('symbol', $params['symbol']);
//        }
//        $positions = $builder->get();
//
//        // 合约保证金账户
//        $account = SustainableAccount::getContractAccount($user['user_id']);
//        foreach ($positions as $position) {
//            $contract = ContractPair::query()->find($position['contract_id']);
//            // 获取最新一条成交记录 即实时最新价格
//            $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
//            $position['pair_name'] = $contract['symbol'] . '/' . $contract['type'];
//            $position['unRealProfit'] = ContractTool::unRealProfit2($position, $contract, $realtime_price);
//            $position['profitRate'] = PriceCalculate($position['unRealProfit'], '/', $position['position_margin'], 4) * 100 . '%';
//            $position['flatPrice'] = ContractTool::getFlatPrice($account, $contract);
//            $position['realtimePrice'] = $realtime_price;
//            $strategy = ContractStrategy::query()
//                ->where('user_id', $user['user_id'])
//                ->where('status', 1)
//                ->where('contract_id', $position['contract_id'])
//                ->where('position_side', $position['side'])
//                ->first();
//            $position['tpPrice'] = $strategy['tp_trigger_price'] ?? '';
//            $position['slPrice'] = $strategy['sl_trigger_price'] ?? '';
//        }
//
//        return $positions;
    }

//    public function holdPosition($user,$params)
//    {
//        $pair = ContractPair::query()->where('symbol',$params['symbol'])->first();
//        if(blank($pair)) throw new ApiException('合约不存在');
//        // 获取最新一条成交记录 即实时最新价格
//        $trade_detail = Cache::store('redis')->get('swap:' . 'trade_detail_' . $pair['symbol']);
//        $realtime_price = $trade_detail['price'];
//        $buy_position = ContractPosition::getPosition(['user_id'=>$user['user_id'],'contract_id'=>$pair['id'],'side'=>1]);
//        $sell_position = ContractPosition::getPosition(['user_id'=>$user['user_id'],'contract_id'=>$pair['id'],'side'=>2]);
//        $buy_position['symbol'] = $sell_position['symbol'] = $pair['symbol'];
//        $buy_position['type'] = $sell_position['type'] = $pair['type'];
//        $buy_position['pair_name'] = $sell_position['pair_name'] = $pair['contract_coin_name'] . '/' . $pair['type'];
//        $buy_position['unRealProfit'] = ContractTool::unRealProfit($buy_position,$pair,$realtime_price);
//        $sell_position['unRealProfit'] = ContractTool::unRealProfit($sell_position,$pair,$realtime_price);
//        $buy_position['profitRate'] = PriceCalculate($buy_position['unRealProfit'] ,'/', $buy_position['position_margin'],4) * 100 . '%';
//        $sell_position['profitRate'] = PriceCalculate($sell_position['unRealProfit'] ,'/', $sell_position['position_margin'],4) * 100 . '%';
//
//        // 合约保证金账户
//        $account = SustainableAccount::getContractAccount($user['user_id']);
//        $flatPrice = ContractTool::flatPrice($account,$buy_position,$sell_position,$pair);
//        $buy_position['flatPrice'] = $sell_position['flatPrice'] = $flatPrice;
//
//        $data[0] = $buy_position;
//        $data[1] = $sell_position;
//        return $data;
//    }

    public function openNum($user, $params)
    {
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        // 合约保证金账户
        $wallet = SustainableAccount::query()->where(['user_id' => $user->user_id, 'coin_id' => $pair['margin_coin_id']])->first();
        if (blank($wallet)) throw new ApiException('账户类型错误');
        $balance = $wallet->usable_balance; // 可用余额
        if ($balance == 0) return 0;
        $cacheKey = 'swap:trade_detail_' . $pair['symbol'];
        $cacheData = Cache::store('redis')->get($cacheKey);
        if (blank($cacheData)) return 0;
        $price = $cacheData['price']; // 61630
        // 单张合约总保证金 = 单张保证金 + 单张手续费

        // 单张保证金 = (价格 * 手数 * 一手几个币) / 杠杆倍数
        $unit_margin = ($price * $pair['unit_amount'] * 1) / $params['lever_rate'];

        // 单张手续费 = 单张保证金 * (0.0375 * 杠杆倍数 / 25)
        $unit_fee = PriceCalculate($unit_margin, '*', $this->getNewFeeScale($params['lever_rate']), 5); // 单张合约手续费

        $unit_total_margin = PriceCalculate($unit_margin, '+', $unit_fee, 5);

        // 可开张数 = （余额/单张合约成交所需保证金）
        $open_num = floor($balance / $unit_total_margin);
        return $open_num;
    }

    //获取最新手续费比例 按杠杆倍数增加25倍就多增加0.0375(3.75%)
    public function getNewFeeScale($lever_rate)
    {
        return 0.025 * $lever_rate / 25;
    }

    /**
     * 合约开仓
     * @param $user  object 用户对象
     * @param $params
     * side---买卖方向 1买入开多 2卖出开空
     * type---委托方式 1限价交易 2市价交易 3止盈止损
     * symbol---合约名称 参数格式：BTC
     * entrust_price---委托价格
     * trigger_price---触发价
     * amount---委托数量(张)
     * lever_rate---杠杆倍数
     * tp_price --- 止损价
     * sl_price --- 止盈价
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws ApiException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function openPosition($user, $params)
    {
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        if (($can_store = $pair->can_store()) !== true) throw new ApiException($can_store);

        // 查看当前是否已有仓位 已有仓位情况下 不可更换杠杆倍数
        // 持仓信息  TODO::v2版本此处做更改 不同杠杆也能开单
//        $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => $params['side']]);
//        if ($position['hold_position'] > 0 && $position['lever_rate'] != $params['lever_rate']) throw new ApiException('持有仓位时不可更改杠杆倍数');

        // 查看当前是否已有同类型未成交的开仓委托 存在时不可更换杠杆倍数  TODO::v2版本此处做更改 不同杠杆也能开单
//        $exist_entrust = ContractEntrust::query()
//            ->where(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => $params['side'], 'order_type' => 1])
//            ->whereIn('status', [ContractEntrust::status_wait, ContractEntrust::status_trading])
//            ->first();
//        if (!blank($exist_entrust) && $exist_entrust['lever_rate'] != $params['lever_rate']) throw new ApiException('持有仓位时不可更改杠杆倍数');

        // 合约保证金账户
        $wallet = SustainableAccount::getContractAccount($user['user_id']);
        if (blank($wallet)) throw new ApiException('账户类型错误');
        $balance = $wallet->usable_balance;

        // 旧版暂时屏蔽
        // 单张合约成交所需保证金 = 单张合约面值（USDT）+ 单张合约成交所需手续费
        //       $unit_amount = $pair['unit_amount']; // 一手几个币
//        $unit_fee = PriceCalculate($pair['unit_amount'] ,'*', $pair['maker_fee_rate'],5); // 单张合约手续费
//        // 计算出所需保证金
//        $freeze_balance = PriceCalculate(($params['amount'] * $unit_amount) ,'/', $params['lever_rate'],5);
//        $freeze_fee = PriceCalculate($params['amount'] ,'*', $unit_fee,5);


        if ($params['type'] == 1) { //限价委托
            $entrust_price = $params['entrust_price'];
            if ($entrust_price <= 0) throw new ApiException('请输入价格');
            $trigger_price = null;
            $amount = $params['amount'];
            $hang_status = 1; // 1-表示立即添加到撮合队列
        } elseif ($params['type'] == 2) { // 市价委托
            $cacheKey = 'swap:trade_detail_' . $params['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            $entrust_price = blank($cacheData) ? null : $cacheData['price'];
            $trigger_price = null;
            $amount = $params['amount'];
            $hang_status = 1;
        } else { // 计划委托
            $entrust_price = $params['entrust_price'];
            if ($entrust_price <= 0) throw new ApiException('请输入价格');
            $trigger_price = $params['trigger_price'];
            $amount = $params['amount'];
            $hang_status = 0; //计划委托 由价格变动触发
        }
        //单张总保证金 = 单张保证金 + 单张手续费
        //计算保证金  (计算公式: (张数x一手几个币x开仓币价)/杠杆倍数)
        // 收手续费 = 保证金 * 费率
        $unit_amount = $pair['unit_amount']; // 一手几个币
        //总保证金
        $freeze_balance = PriceCalculate($params['amount'] * $unit_amount * $entrust_price, '/', $params['lever_rate'], 5); //总保证金
        $freeze_fee = PriceCalculate($freeze_balance, '*', $this->getNewFeeScale($params['lever_rate']), 5); // 手续费
        if ($balance < ($freeze_balance + $freeze_fee)) {
            throw new ApiException('账户保证金不足');
        }

        $log_type = 'open_position'; // 合约开仓冻结
        $log_type2 = 'open_position_fee'; // 合约开仓手续费

        DB::beginTransaction();
        try {
            //创建订单
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $pair['id'],
                'contract_coin_id' => $pair['contract_coin_id'],
                'margin_coin_id' => $pair['margin_coin_id'],
                'symbol' => $pair['symbol'],
                'unit_amount' => $pair['unit_amount'],
                'order_type' => 1,
                'side' => $params['side'],
                'type' => $params['type'],
                'entrust_price' => $entrust_price,
                'amount' => $amount,
                'lever_rate' => $params['lever_rate'],
                'margin' => $freeze_balance, // 保证金
                'fee' => $freeze_fee, // 手续费
                'hang_status' => $hang_status,
                'trigger_price' => $trigger_price,
                'ts' => time(),
                'tp_price' => $params['tp_price'],
                'sl_price' => $params['sl_price']
            ];
            $entrust = ContractEntrust::query()->create($order_data);

            //扣除用户可用资产 冻结保证金
            //保证金  可用余额减少
            $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', -$freeze_balance, UserWallet::sustainable_account, $log_type, '', $entrust['contract_id']);
            //保证金  冻结余额增加
            $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', $freeze_balance, UserWallet::sustainable_account, $log_type, '', $entrust['contract_id']);
            // 手续费  可用余额减少
            $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', -$freeze_fee, UserWallet::sustainable_account, $log_type2, '', $entrust['contract_id']);
            // 手续费  冻结余额增加
            $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', $freeze_fee, UserWallet::sustainable_account, $log_type2, '', $entrust['contract_id']);

            DB::commit();

            //添加待处理委托Job
            if ($entrust['hang_status'] == 1) HandleContractEntrust::dispatch($entrust)->onQueue('handleContractEntrust');

        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }

        return $entrust;
    }

    // 合约平仓
    public function closePosition($user, $params)
    {
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        if (($can_store = $pair->can_store()) !== true) throw new ApiException($can_store);

        // 持仓信息
//        if ($params['side'] == 1) {
//            $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => 2]);
//        } else {
//            $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => 1]);
//        }
        $position = ContractPosition::getPositionById($params['position_id']);
        if (blank($position)) throw new ApiException();
        $avail_position = $position->avail_position; // 可平数量

        if ($params['amount'] <= 0) {
            throw new ApiException();
        }
        if ($params['amount'] > $avail_position) {
            throw new ApiException('平仓数量大于可平数量');
        }
        if ($params['type'] == 1) { //限价平仓
            $entrust_price = $params['entrust_price'];
            if ($entrust_price <= 0) throw new ApiException('请输入价格');
            $trigger_price = null;
            $amount = $params['amount'];
            $hang_status = 1;
        } elseif ($params['type'] == 2) { // 市价平仓
            $cacheKey = 'swap:trade_detail_' . $params['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (!blank($cacheData)) {
                $entrust_price = $cacheData['price'];
            } else {
                $entrust_price = null;
            }
            $trigger_price = null;
            $amount = $params['amount'];
            $hang_status = 1;
        } else {
            $entrust_price = $params['entrust_price'];
            if ($entrust_price <= 0) throw new ApiException('请输入价格');
            $trigger_price = $params['trigger_price'];
            $amount = $params['amount'];
            $hang_status = 0;
        }
        // 记录仓位保证金(平仓时直接抵消掉)
        $margin = ($position['position_margin'] / $position['hold_position']) * $params['amount'];
//        $fee = PriceCalculate($params['amount'] * $pair['maker_fee_rate'] * $pair['unit_amount'] ,'*', $entrust_price,5);
//        $unit_fee = PriceCalculate($position['unit_amount'] ,'*', $pair['maker_fee_rate'],5); // 单张合约手续费
        $fee = PriceCalculate($margin, '*', $this->getNewFeeScale($position['lever_rate']), 5);

        DB::beginTransaction();
        try {
            //创建订单
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $pair['id'],
                'contract_coin_id' => $pair['contract_coin_id'],
                'margin_coin_id' => $pair['margin_coin_id'],
                'symbol' => $pair['symbol'],
                'unit_amount' => $position['unit_amount'],
                'order_type' => 2,
                'side' => $params['side'],
                'type' => $params['type'],
                'entrust_price' => $entrust_price,
                'amount' => $amount,
                'lever_rate' => $position['lever_rate'],
                'margin' => $margin,
                'fee' => $fee,
                'hang_status' => $hang_status,
                'trigger_price' => $trigger_price,
                'ts' => time(),
                'position_id' => $params['position_id']
            ];
            $entrust = ContractEntrust::query()->create($order_data);

            // 冻结持仓数量
            $position->update([
                'avail_position' => $position->avail_position - $amount,
                'freeze_position' => $position->freeze_position + $amount,
            ]);

            DB::commit();

            //添加待处理委托Job
            if ($entrust['hang_status'] == 1) HandleContractEntrust::dispatch($entrust)->onQueue('handleContractEntrust');

        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }

        return $entrust;
    }

    public function closeAllPosition($user, $params)
    {
        return false;
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        if (($can_store = $pair->can_store()) !== true) throw new ApiException($can_store);

        // 持仓信息
        $position_side = $params['side'] == 1 ? 2 : 1;
//        $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => $position_side]);
        if (blank($position)) throw new ApiException();
        if ($position['avail_position'] == 0) throw new ApiException('当前仓位张数为0');

        // 记录仓位保证金(平仓时直接抵消掉)
        $margin = ($position['position_margin'] / $position['hold_position']) * $position['avail_position'];
//        $unit_fee = PriceCalculate($position['unit_amount'] ,'*', $pair['maker_fee_rate'],5); // 单张合约手续费
//        $fee = PriceCalculate($position['avail_position'] ,'*', $unit_fee,5);

        DB::beginTransaction();
        try {

//            $hold_position = $position->hold_position; // 总持仓张数
            $avail_position = $position->avail_position; // 可平数量
//            $freeze_position = $position->freeze_position; // 冻结数量

            //创建订单
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $pair['id'],
                'contract_coin_id' => $pair['contract_coin_id'],
                'margin_coin_id' => $pair['margin_coin_id'],
                'symbol' => $pair['symbol'],
                'unit_amount' => $position['unit_amount'],
                'order_type' => 2,
                'side' => $params['side'],
                'type' => 2, // 市价
                'entrust_price' => null,
                'amount' => $avail_position,
                'lever_rate' => $position['lever_rate'],
                'margin' => $margin,
//                'fee' => $fee,
                'hang_status' => 1,
                'trigger_price' => null,
                'ts' => time(),
            ];
            $entrust = ContractEntrust::query()->create($order_data);

            // 冻结持仓数量
            $position->update([
                'avail_position' => $position->avail_position - $avail_position,
                'freeze_position' => $position->freeze_position + $avail_position,
            ]);

            //添加待处理委托Job
            HandleContractEntrust::dispatch($entrust)->onQueue('handleContractEntrust');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }

        return api_response()->success('Success');
    }

    // 一键全平
    public function onekeyAllFlat($user_id)
    {
        $user = User::query()->find($user_id);
        if (blank($user)) return api_response()->error(0, 'error');

        DB::beginTransaction();
        try {
            // 撤销未完成的平仓委托
            $entrusts = ContractEntrust::query()
                ->where('user_id', $user_id)
                ->where('order_type', 2)
                ->whereIn('status', [ContractEntrust::status_wait, ContractEntrust::status_trading])
                ->get();
            if (!blank($entrusts)) {
                foreach ($entrusts as $entrust) {
                    //更新委托
                    $entrust->update([
                        'status' => 0,
                        'cancel_time' => time(),
                    ]);

                    // 平仓方向
                    $position_side = $entrust['side'] == 1 ? 2 : 1;
                    // 持仓信息
//                    $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $entrust['contract_id'], 'side' => $position_side]);
                    $position = ContractPosition::getPositionById($entrust['position_id']);
                    if (blank($position)) throw new ApiException();
                    // 回退持仓数量
                    $position->update([
                        'avail_position' => $position->avail_position + $entrust['amount'],
                        'freeze_position' => $position->freeze_position - $entrust['amount'],
                    ]);
                }
            }

            // 查询所有持仓仓位列表
            $positions = ContractPosition::query()->where('user_id', $user_id)->where('hold_position', '>', 0)->get();
            if (!blank($positions)) {
                // 平仓
                HandleFlatPosition::dispatch($positions)->onQueue('HandleFlatPosition');
            }

            DB::commit();

            return api_response()->success('Processed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            info($e);
            return api_response()->error(0, 'error');
        }
    }

    public function onekeyReverse($user, $params)
    {
        return false;
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        if (($can_store = $pair->can_store()) !== true) throw new ApiException($can_store);

        // 先平仓
        try {
            DB::beginTransaction();

            // 持仓信息
            $position_side = $params['position_side'];
//            $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => $position_side]);
            if (blank($position)) throw new ApiException();
            if ($position['avail_position'] == 0) throw new ApiException('当前仓位张数为0');

            // 撤销未完成的平仓委托
            $entrust_side = $position['side'] == 1 ? 2 : 1;
            $entrusts = ContractEntrust::query()
                ->where('user_id', $user['user_id'])
                ->where('order_type', 2)
                ->where('contract_id', $pair['id'])
                ->where('side', $entrust_side)
                ->whereIn('status', [ContractEntrust::status_wait, ContractEntrust::status_trading])
                ->get();
            if (!blank($entrusts)) {
                foreach ($entrusts as $entrust) {
                    //更新委托
                    $entrust->update([
                        'status' => 0,
                        'cancel_time' => time(),
                    ]);

                    // 回退持仓数量
                    $position->update([
                        'avail_position' => $position->avail_position + $entrust['amount'],
                        'freeze_position' => $position->freeze_position - $entrust['amount'],
                    ]);
                }
//                foreach ($entrusts as $entrust){
//                    $params2 = ['entrust_id'=>$entrust['id'],'symbol'=>$entrust['symbol']];
//                    (new ContractService())->cancelEntrust($user,$params2);
//                }
            }

            // 记录仓位保证金(平仓时直接抵消掉)
            $margin = ($position['position_margin'] / $position['hold_position']) * $position['avail_position'];
            $unit_fee = PriceCalculate($position['unit_amount'], '*', $this->getNewFeeScale($position['lever_rate']), 5); // 单张合约手续费
            $fee = PriceCalculate($position['avail_position'], '*', $unit_fee, 5);

            $avail_position = $position->avail_position; // 可平数量

            //创建平仓委托
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $pair['id'],
                'contract_coin_id' => $pair['contract_coin_id'],
                'margin_coin_id' => $pair['margin_coin_id'],
                'symbol' => $pair['symbol'],
                'unit_amount' => $position['unit_amount'],
                'order_type' => 2,
                'side' => $position_side == 1 ? 2 : 1,
                'type' => 2, // 市价
                'entrust_price' => null,
                'amount' => $avail_position,
                'lever_rate' => $position['lever_rate'],
                'margin' => $margin,
                'fee' => $fee,
                'hang_status' => 1,
                'trigger_price' => null,
                'ts' => time(),
            ];
            $flat_entrust = ContractEntrust::query()->create($order_data);

            // 冻结持仓数量
            $position->update([
                'avail_position' => $position->avail_position - $avail_position,
                'freeze_position' => $position->freeze_position + $avail_position,
            ]);

            if ($flat_entrust['order_type'] == 2 && $flat_entrust['side'] == 1) {
                // 买入平空
                $this->handleFlatBuyOrder($flat_entrust);
            } elseif ($flat_entrust['order_type'] == 2 && $flat_entrust['side'] == 2) {
                // 卖出平多
                $this->handleFlatSellOrder($flat_entrust);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
//            throw $e;
            return api_response()->error(0, 'error.');
        }

        // 反向开仓
        try {
            DB::beginTransaction();
            // 再开反向仓位
            // 合约保证金账户
            $wallet = SustainableAccount::getContractAccount($user['user_id']);
            if (blank($wallet)) throw new ApiException('账户类型错误');
            $balance = $wallet->usable_balance;

            // 计算出所需保证金
            $freeze_balance = PriceCalculate(($avail_position * $position['unit_amount']), '/', $position['lever_rate'], 5);
            $freeze_fee = PriceCalculate($avail_position, '*', $unit_fee, 5);

            if ($balance < ($freeze_balance + $freeze_fee)) {
                throw new ApiException('账户保证金不足');
            }

            //创建开仓委托
            $order_data2 = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $pair['id'],
                'contract_coin_id' => $pair['contract_coin_id'],
                'margin_coin_id' => $pair['margin_coin_id'],
                'symbol' => $pair['symbol'],
                'unit_amount' => $pair['unit_amount'],
                'order_type' => 1,
                'side' => $position_side == 1 ? 2 : 1,
                'type' => 2,
                'entrust_price' => null,
                'amount' => $avail_position,
                'lever_rate' => $position['lever_rate'],
                'margin' => $freeze_balance,
                'fee' => $freeze_fee,
                'hang_status' => 1,
                'trigger_price' => null,
                'ts' => time(),
            ];
            $open_entrust = ContractEntrust::query()->create($order_data2);

            $log_type = 'open_position'; // 合约开仓冻结
            $log_type2 = 'open_position_fee'; // 合约开仓手续费
            //扣除用户可用资产 冻结保证金
            $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', -$freeze_balance, UserWallet::sustainable_account, $log_type, '', $open_entrust['contract_id']);
            $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', $freeze_balance, UserWallet::sustainable_account, $log_type, '', $open_entrust['contract_id']);
            $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', -$freeze_fee, UserWallet::sustainable_account, $log_type2, '', $open_entrust['contract_id']);
            $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', $freeze_fee, UserWallet::sustainable_account, $log_type2, '', $open_entrust['contract_id']);

            if ($open_entrust['order_type'] == 1 && $open_entrust['side'] == 1) {
                // 买入开多
                $this->handleOpenBuyOrder($open_entrust);
            } elseif ($open_entrust['order_type'] == 1 && $open_entrust['side'] == 2) {
                // 卖出开空
                $this->handleOpenSellOrder($open_entrust);
            }

            DB::commit();

            return api_response()->success('success.');
        } catch (\Exception $e) {
            DB::rollBack();
//            throw $e;
            return api_response()->error(0, 'error.');
        }

    }

    public function setStrategy($user, $params)
    {
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        // 获取最新一条成交记录 即实时最新价格
        $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $pair['symbol'])['price'] ?? null;

        if (blank($params['tp_trigger_price']) && blank($params['sl_trigger_price'])) {
            throw new ApiException('请设置止盈价/止损价');
        }

        if ($params['position_side'] == 1) {
            // 多仓
            if (!blank($params['tp_trigger_price']) && $params['tp_trigger_price'] < $realtime_price) throw new ApiException('止盈触发价需大于最新成交价');
            if (!blank($params['sl_trigger_price']) && $params['sl_trigger_price'] > $realtime_price) throw new ApiException('止损触发价需小于最新成交价');
        } else {
            // 空仓
            if (!blank($params['tp_trigger_price']) && $params['tp_trigger_price'] > $realtime_price) throw new ApiException('止盈触发价需小于最新成交价');
            if (!blank($params['sl_trigger_price']) && $params['sl_trigger_price'] < $realtime_price) throw new ApiException('止损触发价需大于最新成交价');
        }

        DB::beginTransaction();
        try {

            $where = [
                'user_id' => $user['user_id'],
                'contract_id' => $pair['id'],
                'symbol' => $pair['symbol'],
                'position_side' => $params['position_side'],
                'status' => 1,
            ];
            $create_data = [
//                'user_id' => $user['user_id'],
//                'contract_id' => $pair['id'],
//                'symbol' => $pair['symbol'],
//                'position_side' => $params['position_side'],
                'current_price' => $realtime_price,
                'sl_trigger_price' => $params['sl_trigger_price'],
                'sl_trigger_type' => 2,
                'tp_trigger_price' => $params['tp_trigger_price'],
                'tp_trigger_type' => 2,
            ];
            ContractStrategy::query()->updateOrCreate($where, $create_data);

            DB::commit();

            return api_response()->success('Success');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }

    public function expectProfit($user, $params)
    {
        return false;
        $pair = ContractPair::query()->where('symbol', $params['symbol'])->first();
        if (blank($pair)) throw new ApiException('合约不存在');
        // 获取最新一条成交记录 即实时最新价格
        $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $pair['symbol'])['price'] ?? null;

        if ($params['position_side'] == 1) {
            // 多仓
//            $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => $params['position_side']]);
        } else {
            // 空仓
//            $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $pair['id'], 'side' => $params['position_side']]);
        }
        $expect_profit = ContractTool::unRealProfit($position, $pair, $realtime_price);
        $data['expect_profit'] = $expect_profit;
        return api_response()->success('success', $data);
    }

    public function cancelEntrust($user, $params)
    {
        return false;
        $entrust = ContractEntrust::query()->where(['user_id' => $user['user_id'], 'symbol' => $params['symbol'], 'id' => $params['entrust_id']])->firstOrFail();
        if (!$entrust->can_cancel()) throw new ApiException('当前委托不可撤销');

        DB::beginTransaction();
        try {
            //更新委托
            $res = $entrust->update([
                'status' => 0,
                'cancel_time' => time(),
            ]);

            if ($entrust['order_type'] == 1) {
                // 开仓方向
                // 合约保证金账户
                $wallet = SustainableAccount::query()->where(['user_id' => $user->user_id])->first();
                if (blank($wallet)) throw new ApiException('账户类型错误');
                $log_type = 'cancel_open_position'; // 撤销合约委托
                $log_type2 = 'cancel_open_position_fee'; // 撤销合约委托
                //退还用户可用资产 冻结保证金
                $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', $entrust['margin'], UserWallet::sustainable_account, $log_type, '', $entrust['contract_id']);
                $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', -$entrust['margin'], UserWallet::sustainable_account, $log_type, '', $entrust['contract_id']);
                $user->update_wallet_and_log($wallet['coin_id'], 'usable_balance', $entrust['fee'], UserWallet::sustainable_account, $log_type2, '', $entrust['contract_id']);
                $user->update_wallet_and_log($wallet['coin_id'], 'freeze_balance', -$entrust['fee'], UserWallet::sustainable_account, $log_type2, '', $entrust['contract_id']);
            } else {
                // 平仓方向
                $position_side = $entrust['side'] == 1 ? 2 : 1;
                // 持仓信息
//                $position = ContractPosition::getPosition(['user_id' => $user['user_id'], 'contract_id' => $entrust['contract_id'], 'side' => $position_side]);
                if (blank($position)) throw new ApiException();
                // 回退持仓数量
                $position->update([
                    'avail_position' => $position->avail_position + $entrust['amount'],
                    'freeze_position' => $position->freeze_position - $entrust['amount'],
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }

        return $res;
    }

    public function batchCancelEntrust($user, $params)
    {
        $builder = ContractEntrust::query()
            ->where('user_id', $user['user_id'])
            ->whereIn('status', [ContractEntrust::status_wait, ContractEntrust::status_trading]);

        if (isset($params['symbol'])) {
            $builder->where('symbol', $params['symbol']);
        }
        $entrusts = $builder->get();
        if (blank($entrusts)) throw new ApiException('暂无委托');

        DB::beginTransaction();
        try {

            foreach ($entrusts as $entrust) {
                $params2 = ['entrust_id' => $entrust['id'], 'symbol' => $entrust['symbol']];
                $this->cancelEntrust($user, $params2);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }

        return api_response()->success('撤单成功');
    }

    public function getCurrentEntrust($user, $params)
    {
        $builder = ContractEntrust::query()
            ->where('user_id', $user['user_id'])
            ->whereIn('status', [ContractEntrust::status_wait, ContractEntrust::status_trading]);

        if (isset($params['symbol'])) $builder->where('symbol', $params['symbol']);
        if (isset($params['type'])) $builder->where('type', $params['type']);
        if (isset($params['order_type'])) $builder->where('order_type', $params['order_type']);
        if (isset($params['side'])) $builder->where('side', $params['side']);

        return $builder->orderByDesc('id')->paginate();
    }

    public function getHistoryEntrust($user, $params)
    {
        $builder = ContractEntrust::query()
            ->where('user_id', $user['user_id'])
            ->whereIn('status', [ContractEntrust::status_cancel, ContractEntrust::status_completed]);

        if (isset($params['symbol'])) $builder->where('symbol', $params['symbol']);
        if (isset($params['type'])) $builder->where('type', $params['type']);
        if (isset($params['order_type'])) $builder->where('order_type', $params['order_type']);
        if (isset($params['side'])) $builder->where('side', $params['side']);
        return $builder->orderByDesc('id')->paginate();
    }

    public function getEntrustDealList($user_id, $params)
    {
        $entrust = ContractEntrust::query()->where(['user_id' => $user_id, 'symbol' => $params['symbol'], 'id' => $params['entrust_id']])->firstOrFail();
        if ($entrust['side'] == 1) {
            return ContractOrder::query()->where('buy_id', $entrust['id'])->get();
        } else {
            return ContractOrder::query()->where('sell_id', $entrust['id'])->get();
        }
    }

    public function getDealList($user_id, $params)
    {
        $builder = ContractOrder::query()->where(function ($q) use ($user_id) {
            $q->where('buy_user_id', $user_id)->orWhere('sell_user_id', $user_id);
        });

        if (isset($params['symbol'])) $builder->where('symbol', $params['symbol']);
        if (isset($params['order_type'])) $builder->where('order_type', $params['order_type']);
        return $builder->orderByDesc('created_at')->paginate();
    }

    // 买入开多
    public function handleOpenBuyOrder($entrust)
    {
        $pair = ContractPair::query()->where('symbol', $entrust['symbol'])->first();
        if (blank($pair)) return;
        $surplus_amount = $entrust['amount'] - $entrust['traded_amount']; // 剩余可成交手数
        $margin = $entrust['margin']; // 保证金
        if ($entrust['type'] == 1 || $entrust['type'] == 3) {
            // 限价单 成交价格取买单委托价
            $entrust_price = $entrust['entrust_price'];
            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (!blank($cacheData) && $entrust_price > $cacheData['price']) {
                $entrust_price = $cacheData['price'];
            }
        } else {
            // TODO 市价单 成交价格取当前市场实时成交价 或者卖一价
//            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
//            $cacheData = Cache::store('redis')->get($cacheKey);
//            if(blank($cacheData)) return ;
//            $entrust_price = $cacheData['price'];
            //TODO 此处按照用户委托价 立即成交
            $entrust_price = $entrust['entrust_price'];
        }
        if ($surplus_amount <= 0) return;
        // 获取随机一个系统账户 挂对手单 促成撮合
        $user = User::getOneSystemUser();
        if (blank($user)) return;
        DB::beginTransaction();
        try {
            //创建对手盘订单
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $entrust['contract_id'],
                'contract_coin_id' => $entrust['contract_coin_id'],
                'margin_coin_id' => $entrust['margin_coin_id'],
                'symbol' => $entrust['symbol'],
                'unit_amount' => $entrust['unit_amount'],
                'order_type' => 1,
                'side' => 2,
                'type' => 1,
                'entrust_price' => $entrust_price,
                'amount' => $surplus_amount,
                'lever_rate' => $entrust['lever_rate'],
                'margin' => $margin,
                'fee' => 0,
                'hang_status' => 1,
                'trigger_price' => null,
                'ts' => time(),
            ];
            $sell = ContractEntrust::query()->create($order_data);
            // 处理撮合交易
            $this->openDeal($entrust, $sell, 'buy');

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // 处理卖出开空订单
    public function handleOpenSellOrder($entrust)
    {
        $pair = ContractPair::query()->where('symbol', $entrust['symbol'])->first();
        if (blank($pair)) return;
        $surplus_amount = $entrust['amount'] - $entrust['traded_amount'];
        $margin = $entrust['margin'];
        if ($entrust['type'] == 1 || $entrust['type'] == 3) {
            // 限价单 成交价格取买单委托价
            $entrust_price = $entrust['entrust_price'];
            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (!blank($cacheData) && $entrust_price < $cacheData['price']) {
                $entrust_price = $cacheData['price'];
            }
        } else {
            // TODO 市价单 成交价格取当前市场实时成交价 或者卖一价
//            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
//            $cacheData = Cache::store('redis')->get($cacheKey);
//            if(blank($cacheData)) return ;
//            $entrust_price = $cacheData['price'];
            $entrust_price = $entrust['entrust_price'];
        }
        if ($surplus_amount <= 0) return;
        $user = User::getOneSystemUser(); // 获取随机一个系统账户
        if (blank($user)) return;

        DB::beginTransaction();
        try {
            //创建订单
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $entrust['contract_id'],
                'contract_coin_id' => $entrust['contract_coin_id'],
                'margin_coin_id' => $entrust['margin_coin_id'],
                'symbol' => $entrust['symbol'],
                'unit_amount' => $entrust['unit_amount'],
                'order_type' => 1,
                'side' => 1,
                'type' => 1,
                'entrust_price' => $entrust_price,
                'amount' => $surplus_amount,
                'lever_rate' => $entrust['lever_rate'],
                'margin' => $margin,
                'fee' => 0,
                'hang_status' => 1,
                'trigger_price' => null,
                'ts' => time(),
            ];
            $buy = ContractEntrust::query()->create($order_data);

            $this->openDeal($buy, $entrust, 'sell');

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // 处理撮合交易
    public function openDeal($buy, $sell, $side = 'buy')
    {
        $pair = ContractPair::query()->find($buy['contract_id']);
        if (blank($pair)) return;
        if ($side == 'buy') { // 处理买入开多订单
            $exchange_amount = $sell['amount']; // 手数
            $unit_price = $sell['entrust_price']; // 委托价格
            $unit_amount = $buy['unit_amount']; // 一手几个币
            $unit_fee = PriceCalculate($buy['fee'], '/', $buy['amount'], 5); // 一手的手续费
            $buy_fee = PriceCalculate($exchange_amount, '*', $unit_fee, 5); // 成交手续费
            $sell_fee = 0;
        } else {  // 处理卖出开空订单
            $exchange_amount = $buy['amount']; // 成交手数
            $unit_price = $buy['entrust_price']; // 成交价格
            $unit_amount = $sell['unit_amount']; // 一手几个币
            $unit_fee = PriceCalculate($sell['fee'], '/', $sell['amount'], 5); // 一手的手续费
            $buy_fee = 0;
            $sell_fee = PriceCalculate($exchange_amount, '*', $unit_fee, 5);
        }
        $buy_traded_amount = $buy['traded_amount'] + $exchange_amount; // 买入订单成交手数增加
        $sell_traded_amount = $sell['traded_amount'] + $exchange_amount; // 卖出订单成交手数增加

        //计算保证金  (计算公式: (手数x一手几个币x开仓币价)/杠杆倍数)
        $buy_margin = $sell_margin = PriceCalculate($exchange_amount * $unit_amount * $unit_price, '/', $sell['lever_rate'], 5);
        //增加委托成交匹配记录
        ContractOrder::query()->create([
            'contract_id' => $buy['contract_id'],
            'symbol' => $buy['symbol'],
            'unit_amount' => $buy['unit_amount'],
            'order_type' => $buy['order_type'],
            'lever_rate' => $buy['lever_rate'],
            'buy_id' => $buy['id'],
            'sell_id' => $sell['id'],
            'buy_user_id' => $buy['user_id'],
            'sell_user_id' => $sell['user_id'],
            'unit_price' => $unit_price,
            'trade_amount' => $exchange_amount,
            'trade_buy_fee' => $buy_fee,
            'trade_sell_fee' => $sell_fee,
            'ts' => time(),
        ]);

        $position_id = 0;
        if ($side == 'buy') { // 处理买家账户余额
            $buy_user = User::query()->find($buy['user_id']);
            if (!blank($buy_user)) {
                // 买家 更新用户合约账户保证金
                $buy_user->update_wallet_and_log($buy['margin_coin_id'], 'used_balance', $buy_margin, UserWallet::sustainable_account, 'contract_deal', '', $buy['contract_id']);
                $buy_user->update_wallet_and_log($buy['margin_coin_id'], 'freeze_balance', -$buy_margin, UserWallet::sustainable_account, 'contract_deal', '', $buy['contract_id']);
                $buy_user->update_wallet_and_log($buy['margin_coin_id'], 'freeze_balance', -$buy_fee, UserWallet::sustainable_account, 'contract_deal', '', $buy['contract_id']);
                // 更新用户持仓信息  TODO::此处需要更改为一个订单一个仓位 不在进行仓位合并 所以每次都要新建仓位
//                $buy_position = ContractPosition::getPosition(['user_id' => $buy['user_id'], 'contract_id' => $buy['contract_id'], 'side' => $buy['side']]);
//                $buy_position->update([
//                    'hold_position' => $buy_position['hold_position'] + $exchange_amount,
//                    'avail_position' => $buy_position['avail_position'] + $exchange_amount,
//                    'lever_rate' => $buy['lever_rate'],
//                    'unit_amount' => $buy['unit_amount'],
//                    'position_margin' => $buy_position['position_margin'] + $buy_margin,
//                    'avg_price' => ($buy_position['avg_price'] * $buy_position['hold_position'] + $unit_price * $exchange_amount) / ($buy_position['hold_position'] + $exchange_amount),
//                ]);
                $buy_position = ContractPosition::createPosition(['user_id' => $buy['user_id'], 'contract_id' => $buy['contract_id'], 'side' => $buy['side'], 'open_order_id' => $buy['id']]);
                $buy_position->update([
                    'hold_position' => $buy_position['hold_position'] + $exchange_amount,
                    'avail_position' => $buy_position['avail_position'] + $exchange_amount,
                    'lever_rate' => $buy['lever_rate'],
                    'unit_amount' => $buy['unit_amount'],
                    'position_margin' => $buy_position['position_margin'] + $buy_margin,
                    'avg_price' => ($buy_position['avg_price'] * $buy_position['hold_position'] + $unit_price * $exchange_amount) / ($buy_position['hold_position'] + $exchange_amount),
                ]);
                $position_id = $buy_position->id;

                //看下有没有止盈止损
                if (!blank($buy['sl_price']) || !blank($buy['tp_price'])) {
                    $create_data = [
                        'user_id' => $buy['user_id'],
                        'contract_id' => $buy['contract_id'],
                        'symbol' => $buy['symbol'],
                        'position_side' => $buy['side'],
                        'status' => 1,
                        'current_price' => $buy['entrust_price'],
                        'sl_trigger_price' => $buy['tp_price'], //此处反过来传  sl_trigger_price --对应-- $buy['tp_price'] 止损价
                        'sl_trigger_type' => 2,
                        'tp_trigger_price' => $buy['sl_price'], //此处反过来传  tp_trigger_price --对应-- $buy['sl_price']  止盈价
                        'tp_trigger_type' => 2,
                        'position_id' => $position_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                    ContractStrategy::query()->create($create_data);
                }

            }
        } else { //处理卖家账户余额
            $sell_user = User::query()->find($sell['user_id']);
            if (!blank($sell_user)) {
                //卖家 更新用户合约账户保证金
                $sell_user->update_wallet_and_log($sell['margin_coin_id'], 'used_balance', $sell_margin, UserWallet::sustainable_account, 'contract_deal', '', $sell['contract_id']);
                $sell_user->update_wallet_and_log($sell['margin_coin_id'], 'freeze_balance', -$sell_margin, UserWallet::sustainable_account, 'contract_deal', '', $sell['contract_id']);
                $sell_user->update_wallet_and_log($sell['margin_coin_id'], 'freeze_balance', -$sell_fee, UserWallet::sustainable_account, 'contract_deal', '', $sell['contract_id']);
                // 更新用户持仓信息  TODO::此处需要更改为一个订单一个仓位 不在进行仓位合并
//                $sell_position = ContractPosition::getPosition(['user_id' => $sell['user_id'], 'contract_id' => $sell['contract_id'], 'side' => $sell['side']]);
//                $sell_position->update([
//                    'hold_position' => $sell_position['hold_position'] + $exchange_amount,
//                    'avail_position' => $sell_position['avail_position'] + $exchange_amount,
//                    'lever_rate' => $sell['lever_rate'],
//                    'unit_amount' => $sell['unit_amount'],
//                    'position_margin' => $sell_position['position_margin'] + $sell_margin,
//                    'avg_price' => ($sell_position['avg_price'] * $sell_position['hold_position'] + $unit_price * $exchange_amount) / ($sell_position['hold_position'] + $exchange_amount),
//                ]);

                $sell_position = ContractPosition::createPosition(['user_id' => $sell['user_id'], 'contract_id' => $sell['contract_id'], 'side' => $sell['side'], 'open_order_id' => $sell['id']]);
                $sell_position->update([
                    'hold_position' => $sell_position['hold_position'] + $exchange_amount,
                    'avail_position' => $sell_position['avail_position'] + $exchange_amount,
                    'lever_rate' => $sell['lever_rate'],
                    'unit_amount' => $sell['unit_amount'],
                    'position_margin' => $sell_position['position_margin'] + $sell_margin,
                    'avg_price' => ($sell_position['avg_price'] * $sell_position['hold_position'] + $unit_price * $exchange_amount) / ($sell_position['hold_position'] + $exchange_amount),
                ]);
                $position_id = $sell_position->id;

                //看下有没有止盈止损
                if (!blank($sell['sl_price']) || !blank($sell['tp_price'])) {
                    $create_data = [
                        'user_id' => $sell['user_id'],
                        'contract_id' => $sell['contract_id'],
                        'symbol' => $sell['symbol'],
                        'position_side' => $sell['side'],
                        'status' => 1,
                        'current_price' => $sell['entrust_price'],
                        'sl_trigger_price' => $sell['tp_price'], //此处反过来传  sl_trigger_price --对应-- $buy['tp_price'] 止损价
                        'sl_trigger_type' => 2,
                        'tp_trigger_price' => $sell['sl_price'],
                        'tp_trigger_type' => 2,
                        'position_id' => $position_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                    ContractStrategy::query()->create($create_data);
                }
            }
        }

//        委托成交后 有设置止盈止损的添加止盈止损记录

        //更新买卖委托
        $buy_avg_price = blank($buy['avg_price']) ? $unit_price : PriceCalculate(($buy['traded_amount'] * $buy['avg_price'] + $exchange_amount * $unit_price), '/', $buy_traded_amount);
        if ($buy_traded_amount == $buy['amount']) { //完全成交
            $buy->update(['traded_amount' => $buy_traded_amount, 'avg_price' => $buy_avg_price, 'status' => ContractEntrust::status_completed, 'position_id' => $position_id]);
        } else { // 部分成交
            $buy->update(['traded_amount' => $buy_traded_amount, 'avg_price' => $buy_avg_price, 'status' => ContractEntrust::status_trading, 'position_id' => $position_id]);
        }
        $sell_avg_price = blank($sell['avg_price']) ? $unit_price : PriceCalculate(($sell['traded_amount'] * $sell['avg_price'] + $exchange_amount * $unit_price), '/', $sell_traded_amount);
        if ($sell_traded_amount == $sell['amount']) {
            $sell->update(['traded_amount' => $sell_traded_amount, 'avg_price' => $sell_avg_price, 'status' => ContractEntrust::status_completed, 'position_id' => $position_id]);
        } else {
            $sell->update(['traded_amount' => $sell_traded_amount, 'avg_price' => $sell_avg_price, 'status' => ContractEntrust::status_trading, 'position_id' => $position_id]);
        }

    }

    //处理平多
    public function handleFlatBuyOrder($entrust, $system = 0)
    {
        $surplus_amount = $entrust['amount'] - $entrust['traded_amount'];
        if ($entrust['type'] == 1 || $entrust['type'] == 3) {
            // 限价单 成交价格取买单委托价
            $entrust_price = $entrust['entrust_price'];
            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (!blank($cacheData) && $entrust_price > $cacheData['price']) {
                $entrust_price = $cacheData['price'];
            }
        } else {
            // TODO 市价单 成交价格取当前市场实时成交价 或者卖一价
            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (blank($cacheData)) return;
            $entrust_price = $cacheData['price'];
//            $entrust_price = $entrust['entrust_price'];
        }
        if ($surplus_amount <= 0) return;
        $user = User::getOneSystemUser(); // 获取随机一个系统账户
        if (blank($user)) return;

        DB::beginTransaction();
        try {
            //创建订单
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $entrust['contract_id'],
                'contract_coin_id' => $entrust['contract_coin_id'],
                'margin_coin_id' => $entrust['margin_coin_id'],
                'symbol' => $entrust['symbol'],
                'unit_amount' => $entrust['unit_amount'],
                'order_type' => 2,
                'side' => 2,
                'type' => 1,
                'entrust_price' => $entrust_price,
                'amount' => $surplus_amount,
                'lever_rate' => $entrust['lever_rate'],
                'ts' => time(),
                'system' => $system,
                'position_id' => $entrust['position_id']
            ];
            $sell = ContractEntrust::query()->create($order_data);

            $this->flatDeal($entrust, $sell, 'buy', $system);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }

    public function handleFlatSellOrder($entrust, $system = 0)
    {
        $surplus_amount = $entrust['amount'] - $entrust['traded_amount'];
        if ($entrust['type'] == 1 || $entrust['type'] == 3) {
            // 限价单 成交价格取买单委托价
            $entrust_price = $entrust['entrust_price'];
            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (!blank($cacheData) && $entrust_price < $cacheData['price']) {
                $entrust_price = $cacheData['price'];
            }
        } else {
            // TODO 市价单 成交价格取当前市场实时成交价 或者卖一价
            $cacheKey = 'swap:trade_detail_' . $entrust['symbol'];
            $cacheData = Cache::store('redis')->get($cacheKey);
            if (blank($cacheData)) return;
            $entrust_price = $cacheData['price'];
        }
        if ($surplus_amount <= 0) return;
        $user = User::getOneSystemUser(); // 获取随机一个系统账户
        if (blank($user)) return;

        DB::beginTransaction();
        try {
            //创建对手委托
            $order_data = [
                'user_id' => $user['user_id'],
                'order_no' => get_order_sn('PCB'),
                'contract_id' => $entrust['contract_id'],
                'contract_coin_id' => $entrust['contract_coin_id'],
                'margin_coin_id' => $entrust['margin_coin_id'],
                'symbol' => $entrust['symbol'],
                'unit_amount' => $entrust['unit_amount'],
                'order_type' => 2,
                'side' => 1,
                'type' => 1,
                'entrust_price' => $entrust_price,
                'amount' => $surplus_amount,
                'lever_rate' => $entrust['lever_rate'],
                'ts' => time(),
                'system' => $system,
                'position_id' => $entrust['position_id'] //仓位ID
            ];
            $buy = ContractEntrust::query()->create($order_data);
            $this->flatDeal($buy, $entrust, 'sell', $system);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }

    //撮合平仓订单
    public function flatDeal($buy, $sell, $side = 'buy', $system = 0)
    {
        $pair = ContractPair::query()->find($buy['contract_id']);
        if (blank($pair)) return;
        if ($side == 'buy') {
            $exchange_amount = $sell['amount'];
            $unit_price = $sell['entrust_price'];
            $unit_amount = $sell['unit_amount']; // 一手几个币
            $unit_fee = PriceCalculate($unit_amount * $unit_price / $buy['lever_rate'], '*', $this->getNewFeeScale($buy['lever_rate']), 5); // 一手的手续费

            $buy_fee = PriceCalculate($exchange_amount, '*', $unit_fee, 5);
            $sell_fee = 0;
        } else {
            $exchange_amount = $buy['amount'];
            $unit_price = $buy['entrust_price'];
            $unit_amount = $buy['unit_amount']; // 单张合约面值
            $unit_fee = PriceCalculate($unit_amount * $unit_price / $sell['lever_rate'], '*', $this->getNewFeeScale($sell['lever_rate']), 5); // 单张合约手续费

            $buy_fee = 0;
            $sell_fee = PriceCalculate($exchange_amount, '*', $unit_fee, 5);
        }

        $buy_traded_amount = $buy['traded_amount'] + $exchange_amount;
        $sell_traded_amount = $sell['traded_amount'] + $exchange_amount;
//        $buy_margin = PriceCalculate(($exchange_amount * $unit_amount) ,'/', $buy['lever_rate'],5);
//        $sell_margin = PriceCalculate(($exchange_amount * $unit_amount) ,'/', $sell['lever_rate'],5);
        //计算保证金  (计算公式: (手数x一手几个币x开仓币价)/杠杆倍数)
//        $buy_margin = $sell_margin = PriceCalculate($exchange_amount * $unit_amount * $unit_price,'/',$sell['lever_rate'],5);
//        dd($unit_price,$buy_margin);
        //增加委托成交匹配记录
        ContractOrder::query()->create([
            'contract_id' => $buy['contract_id'],
            'symbol' => $buy['symbol'],
            'unit_amount' => $unit_amount,
            'order_type' => $buy['order_type'],
            'lever_rate' => $buy['lever_rate'],
            'buy_id' => $buy['id'],
            'sell_id' => $sell['id'],
            'buy_user_id' => $buy['user_id'],
            'sell_user_id' => $sell['user_id'],
            'unit_price' => $unit_price,
            'trade_amount' => $exchange_amount,
            'trade_buy_fee' => $buy_fee,
            'trade_sell_fee' => $sell_fee,
            'ts' => time(),
        ]);

        // 解冻合约账户保证金 & 扣除平仓手续费
        $log_type = $system == 1 ? 'system_close_position' : 'close_position';
        $log_type2 = $system == 1 ? 'system_close_position_fee' : 'close_position_fee';
        if ($side == 'buy') {
            // 买入平空
            $buy_user = User::query()->find($buy['user_id']);

            // 空仓
//            $empty_position = ContractPosition::getPosition(['user_id' => $buy['user_id'], 'contract_id' => $buy['contract_id'], 'side' => 2], true);
            $empty_position = ContractPosition::getPositionById($buy['position_id'], true);
            $buy_margin = ($empty_position['position_margin'] / $empty_position['hold_position']) * $exchange_amount;

            $empty_position->update([
                'hold_position' => $empty_position['hold_position'] - $exchange_amount,
                'freeze_position' => $empty_position['freeze_position'] - $exchange_amount,
                'position_margin' => $empty_position['position_margin'] - $buy_margin,
                'close_order_id' => $buy['id'] . ',', //记录平仓委托订单
                'status' => $empty_position['hold_position'] - $exchange_amount == 0 ? 2 : 1, // 全部平完
            ]);

            $buy_user->update_wallet_and_log($buy['margin_coin_id'], 'usable_balance', $buy_margin, UserWallet::sustainable_account, $log_type, '', $buy['contract_id']);
            $buy_user->update_wallet_and_log($buy['margin_coin_id'], 'used_balance', -$buy_margin, UserWallet::sustainable_account, $log_type, '', $buy['contract_id']);
            $buy_user->update_wallet_and_log($buy['margin_coin_id'], 'usable_balance', -$buy_fee, UserWallet::sustainable_account, $log_type2, '', $buy['contract_id']);
            // TODO 合约账户盈亏结算
            /*
             * 未实现盈亏计算
             * 未实现盈亏，是用户当前持有的仓位的盈亏，未实现盈亏会随着最新成交价格变动而变化。
             * 多仓盈亏 =（平仓均价-开仓均价）* 多仓合约张数 * 合约面值/开仓均价
             * 空仓盈亏 =（开仓均价-平仓均价）* 空仓合约张数 * 合约面值/开仓均价
             * 例：如用户持有1000张BTC永续合约多仓仓位（合约面值为1USD），持仓均价为5000USD/BTC. 若当前最新价格为8000USD/BTC，则现有的未实现盈亏= ( 8000-5000 ) * 1000*1 /5000 = 600USDT。
             * */
            $log_type4 = $system == 1 ? 'system_close_short_position' : 'close_short_position';
            $empty_position_profit = ContractTool::unRealProfit($empty_position, $pair, $unit_price, $exchange_amount);
//                dd($empty_position_profit,$buy_fee,$exchange_amount,$buy_margin);
            $buy_user->update_wallet_and_log($pair['margin_coin_id'], 'usable_balance', $empty_position_profit, UserWallet::sustainable_account, $log_type4, '', $pair['id']);

            // 平仓 更新委托盈亏记录
            $many_profit = null;
            $empty_profit = blank($buy['profit']) ? $empty_position_profit : $buy['profit'] + $empty_position_profit;
        } else {
            // 卖出平多
            $sell_user = User::query()->find($sell['user_id']);
            // 多仓
//            $many_position = ContractPosition::getPosition(['user_id' => $sell['user_id'], 'contract_id' => $sell['contract_id'], 'side' => 1], true);
            $many_position = ContractPosition::getPositionById($sell['position_id'], true);
            $sell_margin = ($many_position['position_margin'] / $many_position['hold_position']) * $exchange_amount;
            $many_position->update([
                'hold_position' => $many_position['hold_position'] - $exchange_amount,
                'freeze_position' => $many_position['freeze_position'] - $exchange_amount,
                'position_margin' => $many_position['position_margin'] - $sell_margin,
                'close_order_id' => $sell['id'] . ',',//记录平仓委托订单
                'status' => $many_position['hold_position'] - $exchange_amount == 0 ? 2 : 1, // 全部平完
            ]);

            $sell_user->update_wallet_and_log($sell['margin_coin_id'], 'usable_balance', $sell_margin, UserWallet::sustainable_account, $log_type, '', $sell['contract_id']);
            $sell_user->update_wallet_and_log($sell['margin_coin_id'], 'used_balance', -$sell_margin, UserWallet::sustainable_account, $log_type, '', $sell['contract_id']);
            $sell_user->update_wallet_and_log($sell['margin_coin_id'], 'usable_balance', -$sell_fee, UserWallet::sustainable_account, $log_type2, '', $sell['contract_id']);

            // TODO 合约账户盈亏结算
            /*
             * 未实现盈亏计算
             * 未实现盈亏，是用户当前持有的仓位的盈亏，未实现盈亏会随着最新成交价格变动而变化。
             * 多仓盈亏 =（平仓均价-开仓均价）* 多仓合约张数 * 合约面值/开仓均价
             * 空仓盈亏 =（开仓均价-平仓均价）* 空仓合约张数 * 合约面值/开仓均价
             * 例：如用户持有1000张BTC永续合约多仓仓位（合约面值为1USD），持仓均价为5000USD/BTC. 若当前最新价格为8000USD/BTC，则现有的未实现盈亏= ( 8000-5000 ) * 1000*1 /5000 = 600USDT。
             * */
            $log_type3 = $system == 1 ? 'system_close_long_position' : 'close_long_position';
            $many_position_profit = ContractTool::unRealProfit($many_position, $pair, $unit_price, $exchange_amount);
//                dd($many_position_profit,$sell_fee,$exchange_amount,$sell_margin);
            $sell_user->update_wallet_and_log($pair['margin_coin_id'], 'usable_balance', $many_position_profit, UserWallet::sustainable_account, $log_type3, '', $pair['id']);

            // 平仓 更新委托盈亏记录
            $many_profit = blank($sell['profit']) ? $many_position_profit : $sell['profit'] + $many_position_profit;
            $empty_profit = null;
        }

        //更新买卖委托
        $buy_avg_price = blank($buy['avg_price']) ? $unit_price : PriceCalculate(($buy['traded_amount'] * $buy['avg_price'] + $exchange_amount * $unit_price), '/', $buy_traded_amount);
        if ($buy_traded_amount == $buy['amount']) {
            $buy->update(['traded_amount' => $buy_traded_amount, 'avg_price' => $buy_avg_price, 'profit' => $empty_profit, 'status' => ContractEntrust::status_completed, 'fee' => $buy_fee]);
        } else {
            $buy->update(['traded_amount' => $buy_traded_amount, 'avg_price' => $buy_avg_price, 'profit' => $empty_profit, 'status' => ContractEntrust::status_trading, 'fee' => $buy_fee]);
        }
        $sell_avg_price = blank($sell['avg_price']) ? $unit_price : PriceCalculate(($sell['traded_amount'] * $sell['avg_price'] + $exchange_amount * $unit_price), '/', $sell_traded_amount);
        if ($sell_traded_amount == $sell['amount']) {
            $sell->update(['traded_amount' => $sell_traded_amount, 'avg_price' => $sell_avg_price, 'profit' => $many_profit, 'status' => ContractEntrust::status_completed, 'fee' => $sell_fee]);
        } else {
            $sell->update(['traded_amount' => $sell_traded_amount, 'avg_price' => $sell_avg_price, 'profit' => $many_profit, 'status' => ContractEntrust::status_trading, 'fee' => $sell_fee]);
        }

    }

    // 风控任务统一处理
    public function taskPrice($symbol, $price, $type = 'all')
    {
        try {
            $risk_key = contract_risk_key($symbol);
            $risk = json_decode(Redis::get($risk_key), true);
            $tmp = explode('.', $price);
            if (sizeof($tmp) > 1) {
                $decemil = strlen(end($tmp));
            } else {
                $decemil = 0;
            }
//            GENA缓存数据{"start_price":65,"target_price":"68","float":"1","enabled":1,"one_status":1}
//[2024-07-12 23:48:36] local.INFO: GENA原价格62.96000
//[2024-07-12 23:48:36] local.INFO: GENA后价格68.00000
            if (!blank($risk) && $risk['enabled'] == 1) { // 开启了 控制
                if ($type == 'trade' && $risk['one_status'] == 0) { // 第一次触发 直接到达target_price
                    $risk['one_status'] = 1;
                    Redis::set($risk_key, json_encode($risk));
                    $price = custom_number_format($risk['target_price'], $decemil);

                } elseif ($type != 'trade' && $risk['one_status'] == 0) { //没有交易的时候 不要更行其他价格

                    $target_price = Cache::store('redis')->get('swap:trade_detail_' . $symbol)['price'];
                    if ($type == 'depth') { // 深度价格得按比例去扩大
                        $real_cache_price = Cache::store('redis')->get('swap:trade_real_detail_' . $symbol);
                        $real_price = $real_cache_price['price'];
                        $scale = round(($target_price - $real_price) / $real_price, 2) + 1;
                        $price = custom_number_format($price * $scale, $decemil);
                        if ($symbol == 'GENA') {
                            Log::info($symbol . '深度depth 真实价格：' . $real_price);
                        }
                    } else { // 其他的浮动价格
                        $base_float = $target_price * $risk['float'] / 100 * pow(10, 6);
                        $rand = rand(0, $base_float) / pow(10, 6);
                        $rand = rand(1, 2) == 1 ? -$rand : $rand; //随机正负
                        $price = custom_number_format($target_price + $rand, $decemil);
                    }
                } else { // one_status=1 已经达到过目标价格
                    if ($type == 'depth') { // 深度价格得按比例去扩大
                        $real_cache_price = Cache::store('redis')->get('swap:trade_real_detail_' . $symbol);
                        $real_price = $real_cache_price['price'];
                        $scale = round(($risk['target_price'] - $real_price) / $real_price, 2) + 1;
                        $price = custom_number_format($price * $scale, $decemil);

                    } else { // 其他的浮动价格
                        $base_float = $risk['target_price'] * $risk['float'] / 100 * pow(10, 6);
                        $rand = rand(0, $base_float) / pow(10, 6);
                        $rand = rand(1, 2) == 1 ? -$rand : $rand; //随机正负
                        $price = custom_number_format($risk['target_price'] + $rand, $decemil);

                    }
                }
            }
        } catch (\Exception $exception) {
            Log::info('调整价格币种错误' . $exception->getMessage());
            return $price;
        }
        return $price;
    }

    // 风控任务统一处理
    public function taskPrice_bak($symbol, $price, $type = 'all')
    {
        //波动插针版
        //{"minUnit":"100","count":"0","enabled":0,"c_count":0,"side":1,"tmp":0}
        $risk_key = contract_risk_key($symbol);
        $risk = json_decode(Redis::get($risk_key), true);
        if (!blank($risk) && $risk['enabled'] == 1 && $risk['minUnit'] != 0 && $type == 'trade') {
            if ($risk['c_count'] <= $risk['count'] && $risk['side'] == 1) { // 正向任务
                $risk['c_count'] += 1;
                $risk['side'] = $risk['c_count'] >= $risk['count'] ? 2 : 1;
            } else { // 逆向任务
                $risk['c_count'] -= 1;
                $risk['enabled'] = $risk['c_count'] == 0 ? 0 : 1;
            }
            $risk['tmp'] = $risk['minUnit'] * $risk['c_count'];
            Redis::set($risk_key, json_encode($risk));
        }
        return $price + $risk['tmp'];
    }

    public function mergePrice($symbol, $t, $price)
    {
        $cacheKey = 'swap:merge_price_' . $symbol . '_' . $t;
        $cacheData = Cache::store('redis')->get($cacheKey);
        if (!$cacheData) { //第一笔交易
            $cacheData = [$price];
            Cache::store('redis')->put($cacheKey, $cacheData, 60);
            return true;
        } else { //判断是否有相同价格
            if (in_array($price, $cacheData)) {
                return false;
            } else {
                array_push($cacheData, $price);
                Cache::store('redis')->put($cacheKey, $cacheData, 60);
                return true;
            }
        }
    }

}
