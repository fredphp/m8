<?php

namespace App\Console\Commands;

use App\Models\Recharge;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserWallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Erc20Listen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erc20-listen {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监听以太坊链上的USDT到账';

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
        $contracts = ['0xdAC17F958D2ee523a2206206994597C13D831ec7', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48']; //USDT USDC
        $user_id = $this->argument('user_id');
        $addressList = UserAddress::query()->where('user_id', '!=', 0);
        if (!empty($user_id)) {
            $addressList = $addressList->where('user_id', $user_id);
        }
        $addressList = $addressList->get();
        foreach ($addressList as $address) {
            //循环代币
            $data = [];
            foreach ($contracts as $contract) {
                $url = 'https://eth-blockbook.nownodes.io/api/v2/address/' . $address->eth_address . '?details=txs&contract=' . $contract;
                $res = $this->reqNode($url);
                if (!$res) continue;
                $data = array_merge($data, $res);
            }
            foreach ($data as $item) {
                //查看数据库入库没有
                if (Recharge::where('txid', $item['txid'])->exists()) continue;
                $rechargeAddress = $address->eth_address;
                if (strtolower($item['tokenTransfers'][0]['to']) != strtolower($rechargeAddress)){//只要入账的
                    continue;
                }
                $amount = bcdiv($item['tokenTransfers'][0]['value'], bcpow(10, $item['tokenTransfers'][0]['decimals']), 6);
                //上账处理
                DB::beginTransaction();
                try {
                    // 更新用户余额
                    $user = User::query()->findOrFail($address->user_id);
                    $user->update_wallet_and_log(1, 'usable_balance', $amount, UserWallet::asset_account, 'recharge');
                    // 记录日志
                    Recharge::query()->create([
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'coin_id' => 1,
                        'coin_name' => 'USDT',
                        'datetime' => time(),
                        'address' => $rechargeAddress,
                        'txid' => $item['txid'],
                        'amount' => $amount,
                        'status' => Recharge::status_pass,
                        'note' => '充值到账',
                    ]);
                    DB::commit();
                } catch (\Exception $exception) {
                    Log::info('USDT上账失败 交易哈希:' . $item['txid'] . '错误信息:' . $exception->getMessage());
                    DB::rollback();
                }
            }
        }
    }


    public function reqNode($url)
    {
        try {
            sleep(mt_rand(1, 3));
            $keyList = DB::table('tron_keys')->pluck('key')->toArray();
            $randomKey = array_rand($keyList); // 随机键
            $useKey = $keyList[$randomKey]; // 随机值
            $client = new \GuzzleHttp\Client(['verify' => false, 'headers' => ['api-key' => $useKey]]);
            $response = $client->request('GET', $url);
            $res = json_decode($response->getBody()->getContents(), true);
            if (array_key_exists('transactions', $res)) {
                return $res['transactions'];
            }
            return false;
        } catch (\Exception $e) {
            Log::info('波场请求报错:' . $e->getMessage());
            return false;
        }
    }
}
