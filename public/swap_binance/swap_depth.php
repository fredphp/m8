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
    $url = getBinanceSubUrL('@depth20@100ms');

    echo '订阅链接:'.$url."\r\n";
    $con = new AsyncTcpConnection($url);

    // 设置以ssl加密方式访问，使之成为wss
    $con->transport = 'ssl';

    $con->onConnect = function ($con) {
        echo 'depth 连接成功...';
    };

    $con->onMessage = function ($con, $data) {
        try{
            $data = json_decode($data, true);
            if (isset($data['ping'])) {
                $msg = ["pong" => $data['ping']];
                $con->send(json_encode($msg));
            }
            //将币安数据转成火币格式
            $data = depthToHuobi($data);
            if (isset($data['ch'])) {
                $ch = $data['ch'];
                $pattern_depth = '/^market\.(.*?)\.depth\.step6$/'; //深度
                if (preg_match($pattern_depth, $ch, $match_depth)) {
                    //深度数据
                    $symbol = $match_depth[1];
                    $symbol = str_before($symbol, '-');
                    $symbol = symbolMap($symbol, false);
                    // 获取风控任务
                    $risk_key = contract_risk_key($symbol);
                    $risk = json_decode(Redis::get($risk_key), true);
                    $enabled = $risk['enabled'] ?? 0;
                    $se = new \App\Services\ContractService();


                    $buyList = $data['tick']['bids'] ?? [];
                    $cacheBuyList = [];
                    $buyFirstAmount = 1; //改变深度amount 数据
                    foreach ($buyList as $key1 => $item1) {
                        $cacheBuyList[$key1]['id'] = Str::uuid()->toString();
                        //改变深度amount 数据
                        $buyFirstAmount = changeAmount($item1[0],$buyFirstAmount);
                        $cacheBuyList[$key1]['amount'] = $buyFirstAmount;
                        if (!blank($risk) && $enabled == 1) {
                            // 修改买盘价格
                            $original_price = $item1[0];
                            $cacheBuyList[$key1]['price'] = $se->taskPrice($symbol, $original_price,'depth');
                        } else {
                            $cacheBuyList[$key1]['price'] = $item1[0];
                        }
                    }

                    $sellList = $data['tick']['asks'] ?? [];
                    $cacheSellList = [];
                    $sellFirstAmount = 1; //改变深度amount 数据
                    foreach ($sellList as $key2 => $item2) {
                        $cacheSellList[$key2]['id'] = Str::uuid()->toString();
                        $sellFirstAmount = changeAmount($item1[0],$sellFirstAmount);
                        $cacheSellList[$key2]['amount'] = $sellFirstAmount;
                        if (!blank($risk) && $enabled == 1) {
                            // 修改卖盘价格
                            $original_price = $item2[0];
                            $cacheSellList[$key2]['price'] = $se->taskPrice($symbol, $original_price,'depth');
                        } else {
                            $cacheSellList[$key2]['price'] = $item2[0];
                        }
                    }
//                    if ($symbol == 'BTC'){
//                        echo '接受到深度数据 symbol:'.$symbol.'数据信息====买单:'.json_encode($cacheBuyList)."\r\n";
//                    }
                    Cache::store('redis')->put('swap:' . $symbol . '_depth_buy', $cacheBuyList);
                    Cache::store('redis')->put('swap:' . $symbol . '_depth_sell', $cacheSellList);

                    if ($swap_buy = Cache::store('redis')->get('swap_buyList_' . $symbol)) {
                        Cache::store('redis')->forget('swap_buyList_' . $symbol);
                        array_unshift($cacheBuyList, $swap_buy);
                    }
                    if ($swap_sell = Cache::store('redis')->get('swap_sellList_' . $symbol)) {
                        Cache::store('redis')->forget('swap_sellList_' . $symbol);
                        array_unshift($cacheSellList, $swap_sell);
                    }

                    $group_id1 = 'swapBuyList_' . $symbol;
                    $group_id2 = 'swapSellList_' . $symbol;
                    if (Gateway::getClientIdCountByGroup($group_id1) > 0) {
//                        if ($symbol == 'BTC'){
//                            echo '发送时间'.date('Y-m-d H:i:s',time()).'深度处理之后的买单数据:'.json_encode($cacheBuyList)."\r\n";
//                        }
                        Gateway::sendToGroup($group_id1, json_encode(['code' => 0, 'msg' => 'success', 'data' => $cacheBuyList, 'sub' => $group_id1]));
                        Gateway::sendToGroup($group_id2, json_encode(['code' => 0, 'msg' => 'success', 'data' => $cacheSellList, 'sub' => $group_id2]));
                    }
                }

            }
        }catch (Exception $exception){
            \Illuminate\Support\Facades\Log::info('订阅深度接受失败.'.$exception->getTraceAsString());
        }
    };

    $con->onClose = function ($con) {
        //这个是延迟断线重连，当服务端那边出现不确定因素，比如宕机，那么相对应的socket客户端这边也链接不上，那么可以吧1改成适当值，则会在多少秒内重新，我也是1，也就是断线1秒重新链接
        $con->reConnect(1);
    };

    $con->onError = function ($con, $code, $msg) {
        echo "error $code $msg\n";
    };

    $con->connect();
};


function changeAmount($price, $amount)
{
    if ($price > 10000){
        $amount = $amount + mt_rand(1,10);
    }elseif ($price > 1000){
        $amount = $amount + mt_rand(1,10);
    } elseif ($price > 500){
        $amount = $amount + mt_rand(100,500);
    } elseif ($price > 100){
        $amount = $amount + mt_rand(500,800);
    } elseif ($price > 10){
        $amount = $amount + mt_rand(800,1200);
    } elseif ($price > 1){
        $amount = $amount + mt_rand(1200,2000);
    }elseif ($price > 0.5){
        $amount = $amount + mt_rand(1000,2000);
    }else{
        $amount = $amount + mt_rand(2000,3000);
    }
    return $amount;
}


Worker::runAll();
