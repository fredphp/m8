<?php

namespace App\Console\Commands;

use App\Models\Mongodb\TrxusdtTransactions;
use App\Models\Recharge;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserWallet;
use App\Services\CoinService\TronService;
use IEXBase\TronAPI\Support\Utils;
use IEXBase\TronAPI\TronAwareTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TrxMonitor extends Command
{
    use TronAwareTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trx-monitor {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $start = $this->argument('start');
        $end = $this->argument('end');
        if (!empty($start)) {
            $this->sync($start, $end);
        } else {
            $this->listening();
        }
    }

    public function listening()
    {
        $tron = new TronService();
        $blockNumKey = 'trx:blockNum';
        $startBlock = Redis::get($blockNumKey) ?? 75789394;
        $endBlock = $tron->getCurrentBlock();
//        $startBlock = 75786216;
//        $endBlock = 75786217;
        for ($i = $startBlock + 1; $i <= $endBlock; $i++) {
            $block = $tron->getBlockByNumber($i);
            if (empty($block)) {
                continue;
            }
            if (count($block['transactions']) == 0) continue;
            $transactions = $block['transactions'] ?? [];
            echo 'blockNum: --- ' . $i . ' --- trans: --- ' . count($transactions) . ' -- ' . date('Y-m-d H:i:s') . "\r\n";
            foreach ($transactions as $transaction) {
                try {
                    $hash = $transaction['txID'];
                    //判断 数据库 txId 有 就不用往下继续了
                    if (Recharge::query()->where('txid', $hash)->exists()) {
                        continue;
                    }
                    $tx = $transaction;
                    if ($tx['ret'][0]['contractRet'] !== "SUCCESS") continue;
                    $type = $tx['raw_data']['contract'][0]['type'];
                    if ($type !== "TriggerSmartContract") continue;
                    //合约地址转账
                    $data = $tx['raw_data']['contract'][0]['parameter']['value']['data'];
                    $func = substr($data, 0, 8);
                    if ($func !== "a9059cbb") continue; //不是转账方法
                    //合约地址
                    $contract_address = $this->fromHex($tx['raw_data']['contract'][0]['parameter']['value']['contract_address']);
                    //合约地址不是USDT 和 USDC
                    if ($contract_address != "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t" && $contract_address != "TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8") continue;
                    $dataStr = substr($data, 8);
                    $strList = str_split($dataStr, 64);
                    if (count($strList) != 2) {
                        continue;
                    }
                    $to_address = substr($strList[0], 24);
                    if (!(strpos($to_address, "41") === 0)) {
                        $to_address = '41' . $to_address;
                    }
                    $to_address = $this->fromHex($to_address);
                    //不是我们的地址跳过
                    $userAddress = UserAddress::query()->where(['omni_address' => $to_address,'status' => 1])->first();
                    if (!$userAddress) continue;
                    $amountStr = ltrim($strList[1], '0');
                    $amount = custom_number_format(bcMath(hexdec($amountStr), bcpow(10, 6), '/'), 8);
                    //进行上账操作
                    $this->dealErcRecord(['txid' => $hash, 'number' => $amount, 'address' => $to_address], 1);
                } catch (\Exception $e) {
                    info($e);
                    continue;
                }
            }
            // 更新区块高度
            Redis::set($blockNumKey, $i);
        }
    }

    public function sync($startBlock, $endBlock)
    {
        $tron = new TronService();
        for ($i = $startBlock; $i <= $endBlock; $i++) {
            $block = $tron->getBlockByNumber($i);
            if (empty($block)) {
                continue;
            }
            if (count($block['transactions']) == 0) continue;
            $transactions = $block['transactions'] ?? [];
            echo 'blockNum: --- ' . $i . ' --- trans: --- ' . count($transactions) . ' -- ' . date('Y-m-d H:i:s') . "\r\n";
            foreach ($transactions as $transaction) {
                try {
                    $hash = $transaction['txID'];
                    //判断 数据库 txId 有 就不用往下继续了
                    if (Recharge::query()->where('txid', $hash)->exists()) {
                        continue;
                    }
                    $tx = $transaction;
                    if ($tx['ret'][0]['contractRet'] !== "SUCCESS") continue;
                    $type = $tx['raw_data']['contract'][0]['type'];
                    if ($type !== "TriggerSmartContract") continue;
                    //合约地址转账
                    $data = $tx['raw_data']['contract'][0]['parameter']['value']['data'];
                    $func = substr($data, 0, 8);
                    if ($func !== "a9059cbb") continue; //不是转账方法
                    //合约地址
                    $contract_address = $this->fromHex($tx['raw_data']['contract'][0]['parameter']['value']['contract_address']);
                    //合约地址不是USDT 和 USDC
                    if ($contract_address != "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t" && $contract_address != "TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8") continue;
                    $dataStr = substr($data, 8);
                    $strList = str_split($dataStr, 64);
                    if (count($strList) != 2) {
                        continue;
                    }
                    $to_address = substr($strList[0], 24);
                    if (!(strpos($to_address, "41") === 0)) {
                        $to_address = '41' . $to_address;
                    }
                    $to_address = $this->fromHex($to_address);
                    //不是我们的地址跳过
                    $userAddress = UserAddress::query()->where(['omni_address' => $to_address,'status' => 1])->first();
                    if (!$userAddress) continue;
                    $amountStr = ltrim($strList[1], '0');
                    $amount = custom_number_format(bcMath(hexdec($amountStr), bcpow(10, 6), '/'), 8);
                    //进行上账操作
                    $this->dealErcRecord(['txid' => $hash, 'number' => $amount, 'address' => $to_address], 1);
                } catch (\Exception $e) {
                    info($e);
                    continue;
                }
            }
        }
    }


    //处理erc20 上账
    public function dealErcRecord($data, $coin_id)
    {
        $is_exist = Recharge::query()->where(['txid' => $data['txid'], 'address' => $data['address']])->first();
        if ($is_exist) return;
        DB::beginTransaction();
        try {
            $userAddress = UserAddress::where('omni_address', $data['address'])->first();
            // 更新用户余额
            $user = User::query()->findOrFail($userAddress->user_id);
            $user->update_wallet_and_log($coin_id, 'usable_balance', $data['number'], UserWallet::asset_account, 'recharge');
            $cur_price = 1;
            // 记录日志
            Recharge::query()->create([
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'coin_id' => $coin_id,
                'coin_name' => 'USDT',
                'datetime' => time(),
                'address' => $data['address'],
                'txid' => $data['txid'],
                'amount' => $data['number'],
                'status' => Recharge::status_pass,
                'note' => '充值到账',
                'cur_price' => $cur_price

            ]);
            DB::commit();
        } catch (\Exception $exception) {
            Log::info('以太坊上账失败 交易哈希:' . $data['txid'] . '错误信息:' . $exception->getTraceAsString());
            DB::rollback();
        }
    }

}
