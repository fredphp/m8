<?php

namespace App\Console\Commands;

use App\Models\Recharge;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserWallet;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BtcListen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'btc-listen';

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
        $addressArr = UserAddress::where('status', 1)->pluck('omni_address')->toArray();
        if (empty($addressArr)) return;
        $data = $this->requestBlock($addressArr);
        if (!$data) return;
        foreach ($data as $datum) {
            if ($datum['result'] < 0) continue;
            // 判断是否存在
            if (Recharge::where('txid', $datum['hash'])->exists()) continue;
            $rechargeAddress = $datum['out'][0]['addr'];
            $amount = custom_number_format($datum['result']/ pow(10,8), 8);
            //上账处理
            DB::beginTransaction();
            try {

                $userAddress = UserAddress::where('omni_address', $rechargeAddress)->first();
                // 更新用户余额
                $user = User::query()->findOrFail($userAddress->user_id);
                $user->update_wallet_and_log(2, 'usable_balance', $amount, UserWallet::asset_account, 'recharge');

                $cachePrice = Cache::store('redis')->get('swap:trade_detail_BTC') ?? ['price' => 0];
                $cur_price = $cachePrice['price'];

                // 记录日志
                Recharge::query()->create([
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'coin_id' => 2,
                    'coin_name' => 'BTC',
                    'datetime' => time(),
                    'address' => $rechargeAddress,
                    'txid' => $datum['hash'],
                    'amount' => $amount,
                    'status' => Recharge::status_pass,
                    'note' => '充值到账',
                    'cur_price' => $cur_price
                ]);
                DB::commit();
            } catch (\Exception $exception) {
                Log::info('比特币上账失败 交易哈希:'.$datum['hash'].'错误信息:'.$exception->getMessage());
                DB::rollback();
            }
        }
    }

    public function requestBlock($addressArr)
    {
        try {
            $str = implode('|', $addressArr);
            $url = 'https://blockchain.info/multiaddr?active=' . $str;
            $client = new Client(['verify' => false]);
            $response = $client->get($url);
            $result = json_decode($response->getBody()->getContents(), true);
            return $result['txs'];
        } catch (\Exception $exception) {
            Log::info('BTC 记录查询失败' . $exception->getMessage());
            return false;
        }
    }
}
