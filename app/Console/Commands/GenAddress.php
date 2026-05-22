<?php

namespace App\Console\Commands;

use App\Models\UserAddress;
use App\Models\UserWalletAddress;
use App\Services\CoinService\Libs\BitcoinClient;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen-address {type?}';

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

        $type = $this->argument('type') ?? 'eth';
        if ($type == 'eth') {
            for ($i = 1; $i <= 2500; $i++) {
                $newAddress = new UserAddress();
                $newAddress->eth_address = $this->getEthAddress();
                $newAddress->user_id = 0;
                $newAddress->status = 0;
                $newAddress->save();
                $this->info('生成地址成功', $newAddress->eth_address);
            }
        } else {
            $addressList = UserAddress::where('omni_address', '')->get();
            foreach ($addressList as $item) {
                $btcAddress = $this->getBtcAddress();
                if (!$btcAddress) continue;
                $item->omni_address = $btcAddress;
                $item->save();
                $this->info('生成地址成功', $item->omni_address);
            }
        }

    }

    public function getEthAddress()
    {
        try {
            $client = new Client(['verity' => false]);
            $params = [];
            $params['method'] = 'personal_newAccount';
            $params['params'] = [''];
            $params['id'] = 1;
            $params['jsonrpc'] = '2.0';
            $url = '47.90.162.60:7856';
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($params)
            ]);
            $res = json_decode($response->getBody()->getContents(), true);
            return $res['result'];
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function getBtcAddress(){
        $client = new BitcoinClient(env('OMNICORE_USERNAME'), env('OMNICORE_UPASSWORD'), env('OMNICORE_HOST'), env('OMNICORE_PORT'));
        $res = $client->getnewaddress();
        if (!$res){
            return false;
        }
        return $res;
    }
}
