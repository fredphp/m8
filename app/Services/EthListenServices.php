<?php
/**
 * 以太坊系监听到账
 */

namespace App\Services;


use App\Models\Recharge;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserWallet;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class EthListenServices
{
    //以太坊服务器IP
    protected $ip;
    //以太坊钱包端口
    protected $port;

    public function __construct()
    {
        $this->ip = env('GETH_HOST');
        $this->port = env('GETH_PORT');
    }

    //监听方法
    public function listening()
    {
        $startBlock = Cache::get('ethStartBlock', 23375722);
        $newBlock = $this->getNewBlock();
        $log = new Logger('eth');
        $log->pushHandler(new RotatingFileHandler(storage_path('/logs/eth/eth.log')));
        if (!$newBlock) return;
        for ($i = $startBlock + 1; $i <= $newBlock; $i++) {//循环每个区块
            $transactions = $this->getBlockByNumber(dechex($i));
            $log->info('===================================开始处理区块号:' . $i . '=============================================');
            foreach ($transactions['transactions'] as $transaction) {
                $log->info('处理哈希:' . $transaction['hash']);
                if ($transaction['value'] == '0x0') { //合约转账
//                    //获取我们监听的合约
                    if (strtolower($transaction['to']) != strtolower('0xdAC17F958D2ee523a2206206994597C13D831ec7') && strtolower($transaction['to']) != strtolower('0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48')) { //这里还要看下是不是其他合约转过来的
                        continue;
                    }
                    //检测收币地址是不是我们钱包地址
                    $acceptAddress = '0x' . substr($transaction['input'], 34, 40);
                    if (!UserAddress::where(['eth_address' => $acceptAddress, 'status' => 1])->exists()) continue; //不是我们钱包地址  直接跳过
                    //检测该交易是否成功
//                    $checkSuccess = $this->rpcRequest('eth_getTransactionReceipt', [$transaction['hash']]);
//                    if (!$checkSuccess || $checkSuccess['status'] !== '0x1') continue; //交易失败的直接跳过
                    $number = hexdec(substr($transaction['input'], 74)) / pow(10, 6);
                    $log->info('监听到合约到账记录:' . json_encode(['txid' => $transaction['hash'], 'number' => $number, 'address' => $acceptAddress]));
                    //交易所做上账处理
                    $this->dealErcRecord(['txid' => $transaction['hash'], 'number' => $number, 'address' => $acceptAddress], 1);
                }
            }
            $log->info('================================区块号' . $i . '扫描完成==================================================');
            //扫描一个区块 更新最新区块号
            Cache::forever('ethStartBlock', $newBlock);
        }
    }

    //同步指定区间区块号的转账记录
    public function sync($startBlock, $endBlock)
    {
        $log = new Logger('eth');
        $log = $log->pushHandler(new RotatingFileHandler(storage_path('/logs/eth/eth.log')));
        for ($i = $startBlock; $i <= $endBlock; $i++) {//循环每个区块
            $transactions = $this->getBlockByNumber(dechex($i));
            $log->info('===================================开始处理区块号:' . $i . '=============================================');
            foreach ($transactions['transactions'] as $transaction) {
                $log->info('处理哈希:' . $transaction['hash']);
                if ($transaction['value'] == '0x0') { //合约转账
//                    //获取我们监听的合约
                    if (strtolower($transaction['to']) != strtolower('0xdAC17F958D2ee523a2206206994597C13D831ec7') && strtolower($transaction['to']) != strtolower('0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48')) { //这里还要看下是不是其他合约转过来的
                        continue;
                    }
                    //检测收币地址是不是我们钱包地址
                    $acceptAddress = '0x' . substr($transaction['input'], 34, 40);
                    if (!UserAddress::where(['eth_address' => $acceptAddress, 'status' => 1])->exists()) continue; //不是我们钱包地址  直接跳过
                    //检测该交易是否成功
//                    $checkSuccess = $this->rpcRequest('eth_getTransactionReceipt', [$transaction['hash']]);
//                    if (!$checkSuccess || $checkSuccess['status'] !== '0x1') continue; //交易失败的直接跳过
                    $number = hexdec(substr($transaction['input'], 74)) / pow(10, 6);
                    $log->info('监听到合约到账记录:' . json_encode(['txid' => $transaction['hash'], 'number' => $number, 'address' => $acceptAddress]));
                    //交易所做上账处理
                    $this->dealErcRecord(['txid' => $transaction['hash'], 'number' => $number, 'address' => $acceptAddress], 1);
                }
            }
            $log->info('================================区块号' . $i . '扫描完成==================================================');
        }
    }

    //处理erc20 上账
    public function dealErcRecord($data, $coin_id)
    {
        $is_exist = Recharge::query()->where(['txid' => $data['txid'], 'address' => $data['address']])->first();
        if ($is_exist) return;
        DB::beginTransaction();
        try {
            $userAddress = UserAddress::where('eth_address', $data['address'])->first();
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

    //处理以太坊上账
    public function dealEthRecord($data)
    {
        if (count($data) > 0) {
            $coin_id = Coin::where('name', 'ETH')->value('id');
            $account = Account::where(['coin_id' => $coin_id, 'address' => $data['address']])->first();
            if (!$account) return;
            (new CoinServices())->dealRecord([$data], $account);
        }
    }

//    //获取我们监听的合约
//    public function getContract()
//    {
//        $contracts = Coin::where('contract', '!=', '')->pluck('digits', 'contract')->toArray();
//        return $contracts;
//    }

    //获取最新区块
    public function getNewBlock()
    {
        $newBlock = $this->rpcRequest('eth_blockNumber');
        if ($newBlock) {
            return hexdec($newBlock);
        }
        return false;
    }

    //通过区块号获取该区块的所有交易
    public function getBlockByNumber($blockNumber)
    {
        $transactions = $this->rpcRequest('eth_getBlockByNumber', ['0x' . "$blockNumber", true]);
        return $transactions;
    }

    /**
     * rpc请求
     * @param $method
     * @param $params
     * @return bool
     */
    public function rpcRequest($method, $data = [])
    {
        $url = 'http://' . $this->ip . ':' . $this->port;
        $client = new Client(['verify' => false,'timeout' => 30]);
        $params = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'method' => $method,
                'params' => $data,
                'id' => 1,
                'jsonrpc' => '2.0'
            ]),
        ];
        try {
            $response = $client->post($url, $params);
            $content = $response->getBody()->getContents();
            return json_decode($content, true)['result'];
        } catch (\Exception $exception) {
            Log::info('请求以太坊服务器失败' . $exception->getMessage());
            return false;
        }
    }
}
