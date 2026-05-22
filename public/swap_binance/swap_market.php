<?php
require "../index.php";

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;
use GatewayWorker\Lib\Gateway;

$worker = new Worker();
$worker->count = 1;
$worker->onWorkerStart = function ($worker) {

    Gateway::$registerAddress = '127.0.0.1:1238';
    $url = getBinanceSubUrL('@miniTicker');
    $context = [
        'ssl' => [
            'verify_peer' => true, // 仍验证证书有效性
            'verify_peer_name' => false, // 禁用域名验证
            'allow_self_signed' => false,
        ],
    ];
    echo '订阅链接:'.$url."\r\n";
    $con = new AsyncTcpConnection($url,$context);

    // 设置以ssl加密方式访问，使之成为wss
    $con->transport = 'ssl';

    $con->onConnect = function ($con) {
        echo '链接成功...';
    };

    $con->onMessage = function ($con, $data) {
        echo "接收到数据".$data."\r\n";
        try{
            $data = json_decode($data, true);
            if (isset($data['ping'])) {
                $msg = ["pong" => $data['ping']];
                $con->send(json_encode($msg));
            }
            //将币安数据转成火币格式
            $data = marketToHuobi($data);
            if (isset($data['ch'])) {
                $ch = $data['ch'];
                $pattern_detail = '/^market\.(.*?)\.detail$/'; //市场概要
                if (preg_match($pattern_detail, $ch, $match_detail)) {
                    $match = $match_detail[1];
                    $symbol = str_before($match, '.');
                    $symbol = str_before($symbol, '-');
                    $symbol = symbolMap($symbol, false);
                    $after = str_after($match, '.');
                    if ($after != 'trade') {
                        //市场概况
                        $cache_data = $data['tick'];

                        $se = new \App\Services\ContractService();
                        $cache_data['close'] = $se->taskPrice($symbol, $cache_data['close']);
                        $cache_data['open'] = $se->taskPrice($symbol, $cache_data['open']);
                        $cache_data['high'] = $se->taskPrice($symbol, $cache_data['high']);
                        $cache_data['low'] = $se->taskPrice($symbol, $cache_data['low']);

                        if (isset($cache_data['open']) && $cache_data['open'] != 0) {
                            // 获取1dayK线 计算$increase
                            $day_kline = Cache::store('redis')->get('swap:' . $symbol . '_kline_' . '1day');
                            if (blank($day_kline)) {
                                $increase = PriceCalculate(($cache_data['close'] - $cache_data['open']), '/', $cache_data['open'], 4);
                            } else {
                                $increase = PriceCalculate(($cache_data['close'] - $day_kline['open']), '/', $day_kline['open'], 4);
                            }
                        } else {
                            $increase = 0;
                        }
                        $cache_data['increase'] = $increase;
                        $flag = $increase >= 0 ? '+' : '';
                        $cache_data['increaseStr'] = $increase == 0 ? '+0.00%' : $flag . $increase * 100 . '%';

                        $key = 'swap:' . $symbol . '_detail';
                        Cache::store('redis')->put($key, $cache_data);
                    }
                }
            }
        }catch (Exception $exception){
            \Illuminate\Support\Facades\Log::info('市场行情数据处理失败'.$exception->getTraceAsString());
        }
    };

    $con->onClose = function ($con) {

        echo '断线重连中...';
//        dd($con);
        //这个是延迟断线重连，当服务端那边出现不确定因素，比如宕机，那么相对应的socket客户端这边也链接不上，那么可以吧1改成适当值，则会在多少秒内重新，我也是1，也就是断线1秒重新链接
        $con->reConnect(1);
    };

    $con->onError = function ($con, $code, $msg) {
        echo "error $code $msg\n";
    };

    $con->connect();
};

Worker::runAll();
